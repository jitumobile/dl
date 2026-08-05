<?php
/**
 * hls.php — turn a JSON array of remote mp4 URLs into a single HLS m3u8 playlist.
 *
 * Usage:
 *   hls.php?json=<url-to-json>            (JSON must be a plain array of mp4 URLs)
 *   hls.php?json=<url>&probe=1            (use ffprobe for real segment durations)
 */

declare(strict_types=1);

header('Access-Control-Allow-Origin: *');

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$jsonUrl = trim((string) ($_GET['json'] ?? ''));
$probe = ($_GET['probe'] ?? '') === '1';

if ($jsonUrl === '') {
    json_out(['error' => 'missing json parameter', 'usage' => 'hls.php?json=<url-to-json>&probe=1'], 400);
}
if (!preg_match('~^https?://~i', $jsonUrl)) {
    json_out(['error' => 'json parameter must be an http(s) URL'], 400);
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
    json_out(['error' => 'failed to fetch json (HTTP ' . $code . ')'], 502);
}

$list = json_decode($body);
if (!is_array($list)) {
    json_out(['error' => 'json must be an array of mp4 URLs'], 400);
}

$urls = [];
foreach ($list as $entry) {
    if (is_string($entry) && preg_match('~^https?://~i', $entry)) {
        $urls[] = $entry;
    } elseif (is_object($entry) && isset($entry->url) && is_string($entry->url) && preg_match('~^https?://~i', $entry->url)) {
        $urls[] = $entry->url;
    }
}

if (count($urls) === 0) {
    json_out(['error' => 'no valid mp4 URLs found in json'], 400);
}

if (($_GET['list'] ?? '') === '1') {
    json_out(['count' => count($urls), 'urls' => $urls]);
}

function probe_duration(string $url): float
{
    $cmd = 'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($url);
    exec($cmd . ' 2>&1', $out, $code);
    $d = (float) trim(implode('', $out));
    return $d > 0 && $d < 10800 ? $d : 60.0;
}

$playlist = "#EXTM3U\n#EXT-X-VERSION:6\n#EXT-X-TARGETDURATION:60\n#EXT-X-PLAYLIST-TYPE:VOD\n#EXT-X-MEDIA-SEQUENCE:0\n";
$count = count($urls);
foreach ($urls as $i => $url) {
    $dur = $probe ? probe_duration($url) : 60.0;
    $playlist .= '#EXTINF:' . number_format($dur, 3, '.', '') . ",\n" . $url . "\n";
    if ($i < $count - 1) {
        $playlist .= "#EXT-X-DISCONTINUITY\n";
    }
}
$playlist .= "#EXT-X-ENDLIST\n";

http_response_code(200);
header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
header('Cache-Control: no-store');
echo $playlist;
