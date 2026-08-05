<?php
declare(strict_types=1);

$jsonUrl = trim((string) ($_GET['json'] ?? ''));
$index = (int) ($_GET['i'] ?? -1);

if ($jsonUrl === '' || !preg_match('~^https?://~i', $jsonUrl)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'missing json parameter';
    exit;
}

$ch = curl_init($jsonUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_MAXREDIRS      => 5,
]);
$body = (string) curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200 || $body === '') {
    http_response_code(502);
    header('Content-Type: text/plain');
    echo 'failed to fetch json';
    exit;
}

$list = json_decode($body);
if (!is_array($list)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'json must be an array';
    exit;
}

$urls = [];
foreach ($list as $entry) {
    if (is_string($entry) && preg_match('~^https?://~i', $entry)) {
        $urls[] = $entry;
    } elseif (is_object($entry) && isset($entry->url) && is_string($entry->url) && preg_match('~^https?://~i', $entry->url)) {
        $urls[] = $entry->url;
    }
}

if (!isset($urls[$index])) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'segment not found';
    exit;
}

$src = $urls[$index];

set_time_limit(600);
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: video/mp4');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');

$cmd = 'ffmpeg -loglevel error -i ' . escapeshellarg($src)
    . ' -c copy -movflags frag_keyframe+empty_moov+default_base_moof -f mp4 pipe:1 2>/dev/null';

passthru($cmd, $rc);
exit($rc);
