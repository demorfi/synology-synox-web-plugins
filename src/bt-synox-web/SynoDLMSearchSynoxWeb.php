<?php

/**
 * Synology Download Station BT.
 * Search files via SynoX Web.
 *
 * @author  demorfi <demorfi@gmail.com>
 * @version 1.0
 * @php-dsm >=5.6
 * @uses    https://github.com/demorfi/synox-web
 * @source https://github.com/demorfi/synology-synox-web-plugins
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class SynoDLMSearchSynoxWeb
{
    const CURL_TIMEOUT = 30;

    const SOCKET_TIMEOUT = 15;

    const SOCKET_TIMEOUT_MESSAGE = 70;

    const USER_AGENT = 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/535 (KHTML, like Gecko) Chrome/14 Safari/535';

    const URN_FORMAT_PING = '%s/packages';

    const URN_FORMAT_ID = '%s/content/fetch/?id=%s&fetchId=%s';

    const URN_FORMAT_START = '%s/search/start';

    const LOG_FILE = __DIR__ . '/debug.log';

    /**
     * @var array
     */
    private static $categories = ['Audio', 'Video', 'Application', 'Game'];

    /**
     * Username/Password uses for set url.
     * For set a custom url address set the username field value to "custom url" (Example: http://synox.synology.loc/).
     *
     * @var string
     */
    private $url = '';

    /**
     * Username/Password uses for enable debug mode.
     * For enable debug mode set the username or password field value to "test".
     *
     * @var bool
     */
    private $debug = false;

    public function __construct()
    {
        $this->loadFileINFO();
    }

    /**
     * @return void
     */
    private function loadFileINFO()
    {
        if (!is_file(__DIR__ . '/INFO')
            || empty($content = file_get_contents(__DIR__ . '/INFO'))) {
            return;
        }

        $json = @json_decode($content);
        if (isset($json->debug)) {
            $this->setDebugMode($json->debug);
        }

        if (isset($json->url)) {
            $this->setUrl($json->url);
        }

        $this->logger('Loaded INFO: ' . $content);
    }

    /**
     * @param string $url
     * @return void
     */
    public function setUrl($url)
    {
        if (!empty($url) && stripos($url, 'http') === 0) {
            $url = rtrim(trim($url), '/') . '/api';
            if ($this->url !== $url) {
                $this->url = $url;
                $this->logger('Set api entrypoint: ' . $url);
            }
        }
    }

    /**
     * @param bool $mode
     * @return void
     */
    public function setDebugMode($mode = false)
    {
        $mode = (bool)$mode;
        if ($this->debug !== $mode) {
            $this->logger('Set debug mode: ' . ($mode ? 'enable' : 'disable'));
            $this->debug = $mode;
        }
    }

    /**
     * @param string $string
     * @return void
     */
    public function stringAsConfig($string)
    {
        if (stripos($string, 'test') === 0) {
            $this->setDebugMode(true);
            $this->logger('String as config debug: ' . $string);
        }

        if (stripos($string, 'http') === 0) {
            $this->setUrl($string);
            $this->logger('String as config url: ' . $string);
        }
    }

    /**
     * @param string $message
     * @return void
     */
    private function logger($message)
    {
        if (!$this->debug) {
            return;
        }

        if (function_exists('LogInfo')) {
            LogInfo($message);
        }

        file_put_contents(self::LOG_FILE, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array $options
     * @return resource
     */
    private function curlInit($options = [])
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::CURL_TIMEOUT);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
        curl_setopt($curl, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        foreach ($options as $key => $value) {
            curl_setopt($curl, $key, $value);
        }

        return $curl;
    }

    /**
     * @param string $address
     * @param string $token
     * @return resource
     * @throws Exception
     */
    private function connectSocket($address, $token)
    {
        $context = stream_context_create(['ssl' => ['verify_peer' => false]]);
        $socket  = @stream_socket_client(
            $address,
            $errNo,
            $errMsg,
            self::SOCKET_TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket === false) {
            throw new \Exception(sprintf('Cannot connect to websocket: %d->%s', $errNo, $errMsg));
        }

        stream_set_timeout($socket, self::SOCKET_TIMEOUT, 0);

        $host   = substr($address, stripos($address, '://') + 3);
        $origin = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : parse_url($address, PHP_URL_HOST);
        $key    = base64_encode(openssl_random_pseudo_bytes(16));

        if (!fwrite(
            $socket,
            <<<EOD
GET /?token={$token} HTTP/1.1\r\n
Host: {$host}\r\n
Origin: {$origin}\r\n
Pragma: no-cache\r\n
User-Agent: syno-dlm-synox-web\r\n
Cache-Control: no-cache\r\n
Upgrade: WebSocket\r\n
Connection: Upgrade\r\n
Sec-WebSocket-Key: {$key}\r\n
Sec-WebSocket-Version: 13\r\n\r\n
EOD
        )) {
            throw new \Exception(sprintf('Cannot send upgrade header to websocket: %d->%s', $errNo, $errMsg));
        }

        $response = fread($socket, 1024);
        if (stripos($response, 'Sec-WebSocket-Accept: ') === false) {
            throw new \Exception(sprintf('Server did not accept connection upgrade to websocket: %s', $response));
        }

        return $socket;
    }

    /**
     * @param resource $socket
     * @return string
     * @throws Exception
     */
    public function readSocket($socket)
    {
        $message = '';
        do {
            $header = fread($socket, 2);
            if (!$header) {
                throw new \Exception('Failed to read header from websocket');
            }

            $masked = ord($header[1]) & 0x80;
            if ($masked) {
                $mask = fread($socket, 4);
                if (!$mask) {
                    throw new \Exception('Failed to read header mask from websocket');
                }
            }

            $length = ord($header[1]) & 0x7F;
            if ($length >= 0x7E) {
                $extLength = $length == 0x7F ? 8 : 2;
                $extHeader = fread($socket, $extLength);
                if (!$extHeader) {
                    throw new \Exception('Failed to read header extension from websocket');
                }

                $length = 0;
                for ($i = 0; $i < $extLength; $i++) {
                    $length += ord($extHeader[$i]) << ($extLength - $i - 1) * 8;
                }
            }

            $opcode = ord($header[0]) & 0x0F;
            switch ($opcode) {
                case ($opcode < 3): // payload data
                    $frameData = '';
                    while ($length > 0) {
                        $frame = fread($socket, $length);
                        if (!$frame) {
                            throw new \Exception('Failed to read payload from websocket');
                        }
                        $length    -= strlen($frame);
                        $frameData .= $frame;
                    }

                    if (!$masked) {
                        $message .= $frameData;
                        break;
                    }

                    $dataLength = strlen($frameData);
                    for ($i = 0; $i < $dataLength; $i++) {
                        $message .= $frameData[$i] ^ $mask[$i % 4];
                    }
                    break;

                case 8: // close socket
                    fclose($socket);
                    break;

                case 9: // ping-pong
                    fwrite($socket, "\x8A\x80" . pack("N", rand(1, 0x7FFFFFFF)));
                    break;
            }

            $end = ord($header[0]) & 0x80;
        } while (!$end);
        return $message;
    }

    /**
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function VerifyAccount($username = '', $password = '')
    {
        $this->stringAsConfig($username);
        $this->stringAsConfig($password);

        if (empty($this->url)) {
            $this->logger('Synox web url is empty!');
            return false;
        }

        $this->logger('Verify url: ' . $this->url);
        $curl = $this->curlInit([CURLOPT_URL => sprintf(self::URN_FORMAT_PING, $this->url)]);
        curl_exec($curl);

        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $this->logger('Returned status: ' . $code);
        return $code === 200;
    }

    /**
     * @param resource $curl
     * @param string   $query
     * @param string   $username
     * @param string   $password
     * @return void
     */
    public function prepare($curl, $query, $username = '', $password = '')
    {
        $this->stringAsConfig($username);
        $this->stringAsConfig($password);

        if (empty($this->url)) {
            $this->logger('Synox web url is empty!');
            return;
        }

        $payload = json_encode([
            'query'   => $query,
            'filters' => ['category' => self::$categories]
        ]);

        curl_setopt($curl, CURLOPT_URL, sprintf(self::URN_FORMAT_START, $this->url));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $this->logger('Prepare url: ' . curl_getinfo($curl, CURLINFO_EFFECTIVE_URL));
        $this->logger('Prepare payload: ' . $payload);
    }

    /**
     * @param object $plugin
     * @param string $response
     * @return int
     */
    public function parse($plugin, $response)
    {
        $total = 0;
        if (empty($response)) {
            $this->logger('Response is empty!');
            return $total;
        }

        $this->logger('Response: ' . $response);
        $response = @json_decode($response);
        if (!isset($response->host) || !isset($response->token)) {
            $this->logger('Response is invalid!');
            return $total;
        }

        $sTime   = time();
        $address = str_ireplace(['wss://', 'ws://'], ['ssl://', 'tcp://'], $response->host);
        $this->logger('Socket address: ' . $address);

        try {
            $connection = $this->connectSocket($address, $response->token);
            while (true) {
                $message = $this->readSocket($connection);
                $this->logger('Message: ' . $message);

                $json = @json_decode($message);
                if (empty($json)) {
                    $this->logger('Message is empty or incorrect!');
                    return $total;
                }

                if (isset($json->finished)) {
                    $this->logger('Connection finished!');
                    break;
                }

                if (isset($json->payload) && !empty($payload = $json->payload) && !empty($payload->fetchId)) {
                    $title    = sprintf('[%s] - %s', $payload->package, $payload->title);
                    $download = sprintf(self::URN_FORMAT_ID, $this->url, $payload->id, base64_encode($payload->fetchId));
                    $size     = (float)$payload->size;
                    $datetime = $payload->date;
                    $page     = $payload->pageUrl;
                    $seeds    = (int)$payload->seeds;
                    $peers    = (int)$payload->peers;
                    $category = $payload->category;

                    $plugin->addResult($title, $download, $size, $datetime, $page, '', $seeds, $peers, $category);
                    $total++;
                }

                if (($sTime - time()) >= self::SOCKET_TIMEOUT_MESSAGE) {
                    $this->logger('Exit by timeout!');
                    break;
                }
            }
        } catch (\Exception $e) {
            $this->logger('Throw: ' . $e->getMessage());
        }

        return $total;
    }
}