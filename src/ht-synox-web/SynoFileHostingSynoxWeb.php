<?php

/**
 * Synology Download Station HT.
 * Download files via SynoX Web.
 *
 * @author  demorfi <demorfi@gmail.com>
 * @version 1.3
 * @php-dsm >=5.6
 * @uses    https://github.com/demorfi/synox-web
 * @source https://github.com/demorfi/synology-synox-web-plugins
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class SynoFileHostingSynoxWeb
{
    const CURL_TIMEOUT = 30;

    const ERROR_FILE_NO_EXIST = 114;

    const ERROR_INVALID_HOST = 3;

    const ERROR_BROKEN_LINK = 102;

    const STATUS_SUCCESS = 6;

    const STATUS_FAIL = 4;

    const DOWNLOAD_URL = 'downloadurl';

    const DOWNLOAD_ERROR = 'error';

    const DOWNLOAD_FILENAME = 'filename';

    const USER_AGENT = 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/535 (KHTML, like Gecko) Chrome/14 Safari/535';

    const URN_FORMAT_PING = '%s/packages';

    const URN_FORMAT_FETCH = '%s/content/fetch/packageId/%s';

    const URN_FORMAT_DOWNLOAD = '%s/content/download/name/%s/type/%s';

    const LOG_FILE = __DIR__ . '/debug.log';

    /**
     * Username/Password uses for set url.
     * For set a custom url address set the username field value to "custom url" (Example: http://synox.synology.loc/).
     *
     * @var string
     */
    private $url = '';

    /**
     * @var string
     */
    private $packageId;

    /**
     * @var string
     */
    private $fetchId;

    /**
     * Username/Password uses for enable debug mode.
     * For enable debug mode set the username or password field value to "test".
     *
     * @var bool
     */
    private $debug = false;

    /**
     * @param string  $url
     * @param string  $username
     * @param ?string $password
     */
    public function __construct($url, $username = '', $password = '')
    {
        $this->loadFileINFO();
        $this->stringAsConfig($username);
        $this->stringAsConfig($password);

        // Parse query to id package and fetch id
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->packageId = isset($query['id']) ? $query['id'] : '';
        $this->fetchId   = isset($query['fetchId']) ? base64_decode($query['fetchId']) : '';

        $this->logger('Set url: ' . $url);
        $this->logger('Prepare package id: ' . $this->packageId);
        $this->logger('Prepare fetch id: ' . $this->fetchId);
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
        if (is_string($string) && strlen($string) > 0) {
            if (stripos($string, 'test') === 0) {
                $this->setDebugMode(true);
                $this->logger('String as config debug: ' . $string);
                return;
            }

            if (stripos($string, 'http') === 0) {
                $this->setUrl($string);
                $this->logger('String as config url: ' . $string);
                return;
            }

            $array = $this->jsonDecode($string);
            foreach ($array as $key => $value) {
                switch (strtolower((string)$key)) {
                    case 'debug':
                        $this->setDebugMode((bool)$value);
                        $this->logger('JSON as config debug: ' . $string);
                        break;
                    case 'url':
                        $this->setUrl((string)$value);
                        $this->logger('JSON as config url: ' . $string);
                        break;
                }
            }
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
     * @param $string
     * @return array
     */
    private function jsonDecode($string)
    {
        if (is_string($string) && strlen($string) > 2) {
            $result = @json_decode($string, true);
            return json_last_error() === JSON_ERROR_NONE ? (array)$result : [];
        }
        return [];
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
     * @return int
     */
    public function Verify()
    {
        if (empty($this->url)) {
            $this->logger('Synox web url is empty!');
            return self::ERROR_INVALID_HOST;
        }

        if (empty($this->packageId) || empty($this->fetchId)) {
            $this->logger('Package id and fetch id not found in query!');
            return self::ERROR_BROKEN_LINK;
        }

        $this->logger('Verify url: ' . $this->url);
        $curl = $this->curlInit([CURLOPT_URL => sprintf(self::URN_FORMAT_PING, $this->url)]);
        curl_exec($curl);

        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $this->logger('Returned status: ' . $code);
        return $code === 200 ? self::STATUS_SUCCESS : self::STATUS_FAIL;
    }

    /**
     * @return array
     */
    public function GetDownloadInfo()
    {
        if ($this->Verify() !== self::STATUS_SUCCESS) {
            return [self::DOWNLOAD_ERROR => self::ERROR_BROKEN_LINK];
        }

        $payload = json_encode(['fetchId' => $this->fetchId]);
        $curl    = $this->curlInit([
            CURLOPT_URL        => sprintf(self::URN_FORMAT_FETCH, $this->url, $this->packageId),
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);

        $this->logger('Fetch url: ' . curl_getinfo($curl, CURLINFO_EFFECTIVE_URL));
        $this->logger('Fetch payload: ' . $payload);

        $response = curl_exec($curl);
        $this->logger('Response: ' . $response);
        curl_close($curl);

        if (empty($response)) {
            return [self::DOWNLOAD_ERROR => self::ERROR_FILE_NO_EXIST];
        }

        $json = @json_decode($response);
        if (isset($json->available, $json->name, $json->type) && $json->available) {
            $curl = $this->curlInit([
                CURLOPT_URL            => sprintf(self::URN_FORMAT_DOWNLOAD, $this->url, $json->name, $json->type),
                CURLOPT_FOLLOWLOCATION => false
            ]);

            $this->logger('Download url: ' . curl_getinfo($curl, CURLINFO_EFFECTIVE_URL));
            curl_exec($curl);

            $url = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
            $this->logger('Effective url: ' . $url);
            curl_close($curl);

            if (!empty($url)) {
                $info = [self::DOWNLOAD_URL => $url];

                if (isset($json->baseName)) {
                    $info[self::DOWNLOAD_FILENAME] = $json->baseName;
                }
                return $info;
            }
        }

        return [self::DOWNLOAD_ERROR => self::ERROR_FILE_NO_EXIST];
    }
}