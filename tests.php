<?php

/**
 * @param string $message
 * @param bool   $exit
 * @return void
 */
function LogInfo($message, $exit = false)
{
    print $message . PHP_EOL;
    if ($exit) {
        exit;
    }
}

interface SynoInterface
{
    /**
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function VerifyAccount($username = '', $password = '');

    /**
     * @param resource $curl
     * @param string   $query
     * @param string   $username
     * @param string   $password
     * @return bool
     */
    public function prepare($curl, $query, $username = '', $password = '');

    /**
     * @param SynoAbstract $plugin
     * @param string       $response
     * @return int
     */
    public function parse(SynoAbstract $plugin, $response);

    /**
     * @return array
     */
    public function GetDownloadInfo();

    /**
     * @param string       $artist
     * @param string       $title
     * @param SynoAbstract $plugin
     * @return int
     */
    public function getLyricsList($artist, $title, SynoAbstract $plugin);

    /**
     * @param string       $id
     * @param SynoAbstract $plugin
     * @return bool
     */
    public function getLyrics($id, SynoAbstract $plugin);
}

class SynoAbstract
{
    /**
     * @var string
     */
    private $lyricsId;

    /**
     * @return string
     */
    public function getLyricsId()
    {
        return $this->lyricsId;
    }

    /**
     * @param string $title    Title torrent
     * @param string $download Url to download torrent
     * @param float  $size     Size files in torrent
     * @param string $datetime Date create torrent
     * @param string $page     Url torrent page
     * @param string $hash     Hash item
     * @param int    $seeds    Count torrent seeds
     * @param int    $leeches  Count torrent leeches
     * @param string $category Torrent category
     * @return void
     */
    public function addResult($title, $download, $size, $datetime, $page, $hash, $seeds, $leeches, $category)
    {
        LogInfo(PHP_EOL);
        LogInfo('################# addResult ##################');
        LogInfo('Title: ' . $title);
        LogInfo('Category: ' . $category);
        LogInfo('Size: ' . $size);
        LogInfo('Datetime: ' . $datetime);
        LogInfo('Page: ' . $page);
        LogInfo('Hash: ' . $hash);
        LogInfo('Seeds: ' . $seeds);
        LogInfo('Leeches: ' . $leeches);
        LogInfo('Download: ' . $download);
        LogInfo('############### End addResult ################');
        LogInfo(PHP_EOL);
    }

    /**
     * @param string $artist        Artist song
     * @param string $title         Title song
     * @param string $id            Id song
     * @param string $partialLyrics Partial lyric song
     * @return void
     */
    public function addTrackInfoToList($artist, $title, $id, $partialLyrics = '')
    {
        $this->lyricsId = $id;

        LogInfo(PHP_EOL);
        LogInfo('############# addTrackInfoToList #############');
        LogInfo('Id: ' . $id);
        LogInfo('Artist: ' . $artist);
        LogInfo('Title: ' . $title);
        LogInfo('=========== Partial Lyric ==========');
        LogInfo($partialLyrics);
        LogInfo('########### End addTrackInfoToList ###########');
        LogInfo(PHP_EOL);
    }

    /**
     * @param string $lyric Lyric content
     * @param string $id    Lyric id
     * @return void
     */
    public function addLyrics($lyric, $id)
    {
        LogInfo(PHP_EOL);
        LogInfo('################## addLyrics #################');
        LogInfo('Id: ' . $id);
        LogInfo('============== Lyric ===============');
        LogInfo($lyric);
        LogInfo('############### End addLyrics ################');
        LogInfo(PHP_EOL);
    }
}

if (php_sapi_name() !== 'cli') {
    LogInfo('Only works through cli!', true);
}

$options = getopt('', ['command:', 'query:', 'url:', 'debug::', 'help::']);
if (!isset($options['command']) || !isset($options['query']) || isset($options['help'])) {
    LogInfo(
        <<<'EOT'
Search files: 
    --command bt --query "search query string" [--url "http://synox.synology.loc/"] [--debug]
    
Download file:
    --command ht --query "search result link" [--url "http://synox.synology.loc/"] [--debug]

Search texts: 
    --command au --query "artist song/title song" [--url "http://synox.synology.loc/"] [--debug]

Download text:
    --command hu --query "search result link" [--url "http://synox.synology.loc/"] [--debug]
EOT
        ,
        true
    );
}

if (!in_array($options['command'], ['bt', 'ht', 'au', 'hu'])) {
    LogInfo('Unknown command!', true);
}

if ($options['command'] === 'hu') {
    $options['command'] = 'au';
    $options['sub']     = 'hu';
}

define('SRC_PATH', realpath(__DIR__ . '/src'));
$path = SRC_PATH . '/' . $options['command'] . '-synox-web/';

if (!is_file($path . 'INFO')) {
    LogInfo('Missing INFO file!', true);
}

$info = json_decode(file_get_contents($path . 'INFO'));
if (!is_file($path . $info->module)) {
    LogInfo('Module file is not found!', true);
}

require_once($path . $info->module);

$curl = curl_init();
curl_setopt($curl, CURLOPT_HEADER, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
curl_setopt($curl, CURLOPT_TIMEOUT, 20);
curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/535 (KHTML, like Gecko) Chrome/14 Safari/535');

$query    = stripslashes($options['query']);
$username = isset($options['url']) ? $options['url'] : '';
$password = isset($options['debug']) ? 'test' : '';
$module   = new $info->class($query, $username, $password);

if (isset($options['debug'])) {
    $module->setDebugMode(true);
}

if (isset($options['url'])) {
    $module->setUrl($options['url']);
}

/* @var $module SynoInterface */
switch ($options['command']) {
    case ('bt'):
        $verify = $module->VerifyAccount($username, $password);

        LogInfo('Verify: ' . ($verify ? 'success' : 'failure'));
        if ($verify) {
            $module->prepare($curl, $query);
            LogInfo('Effective url: ' . curl_getinfo($curl, CURLINFO_EFFECTIVE_URL));

            $response = curl_exec($curl);
            curl_close($curl);
            LogInfo('Total found: ' . $module->parse(new SynoAbstract(), $response));
        }
        break;

    case ('ht'):
        $download = $module->GetDownloadInfo();
        if (isset($download['error']) || !isset($download['downloadurl'])) {
            LogInfo('Error: ' . isset($download['error']) ? $download['error'] : 'download url');
            break;
        }

        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_URL, $download['downloadurl']);
        $response = curl_exec($curl);

        LogInfo('Download: ' . (strpos($response, 'announce') !== false ? 'success' : 'failure'));
        curl_close($curl);
        break;

    case ('au'):
        $interface = new SynoAbstract();
        if (isset($options['sub']) && $options['sub'] === 'hu') {
            LogInfo('Lyric: ' . ($module->getLyrics($query, $interface) ? 'success' : 'failure'));
        } else {
            $title  = $query;
            $artist = '';
            if (strpos($query, '/') !== false) {
                list($artist, $title) = explode('/', $query);
            }
            LogInfo('Count: ' . $module->getLyricsList($artist, $title, $interface));
        }
        break;
}