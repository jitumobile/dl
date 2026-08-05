<?php
/**
 * ytdl.php — YouTube URL -> direct download/stream URL via yt-dlp
 *
 * Usage:
 *   ytdl.php?url=<youtube url>
 *   ytdl.php?url=<youtube url>&format=mp4|best|720p|audio
 *   ytdl.php?url=<youtube url>&redirect=1        (302 to the direct URL)
 *   ytdl.php?url=<youtube url>&stream=1          (server proxies/relays bytes to any viewer)
 *   ytdl.php?url=<youtube url>&nocache=1
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

const CACHE_TTL = 300;          // seconds; direct URLs are signed and expire (~6h)
const CMD_TIMEOUT = 120;        // seconds for one yt-dlp run
const MP3_TIMEOUT = 600;        // seconds for audio download + mp3 conversion

define('PYTHON', getenv('PYTHON') ?: 'python3');
define('FFMPEG_DIR', getenv('FFMPEG_DIR') ?: '');
define('YT_COOKIES', getenv('YT_COOKIES') ?: (is_file('/etc/ytdl/cookies.txt') ? '/etc/ytdl/cookies.txt' : ''));

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function extract_id(string $url): ?string
{
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
        return $url;
    }
    if (preg_match('~(?:v=|/watch/|youtu\.be/|/embed/|/shorts/|/live/)([A-Za-z0-9_-]{11})~', $url, $m)) {
        return $m[1];
    }
    return null;
}

function run_cmd(array $argv, int $timeout): array
{
    $cmd = implode(' ', array_map('escapeshellarg', $argv));
    $tmp = tempnam(sys_get_temp_dir(), 'ytdlout');
    if ($tmp === false) {
        return ['ok' => false, 'error' => 'could not create temp file', 'out' => '', 'err' => ''];
    }
    $full = $cmd . ' 1> ' . escapeshellarg($tmp) . ' 2>&1';
    exec($full, $dummy, $code);
    $out = (string) @file_get_contents($tmp);
    @unlink($tmp);
    return ['ok' => true, 'code' => $code, 'out' => trim($out), 'err' => ''];
}

function cache_path(string $key): string
{
    return rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'ytdl_' . md5($key) . '.json';
}

function fmt_label(object $f): string
{
    $label = $f->format_id ?? '';
    if (isset($f->height) && $f->height) {
        $label .= ' ' . $f->height . 'p';
    } elseif (isset($f->abr) && $f->abr) {
        $label .= ' ' . round((float) $f->abr) . 'kbps';
    }
    return $label;
}

$url = $_GET['url'] ?? '';
$format = strtolower($_GET['format'] ?? 'mp4');
$redirect = ($_GET['redirect'] ?? '') === '1';
$stream = ($_GET['stream'] ?? '') === '1';
$nocache = ($_GET['nocache'] ?? '') === '1';
$client = trim((string) ($_GET['client'] ?? ''));

if ($url === '') {
    json_out(['error' => 'missing url parameter', 'usage' => 'ytdl.php?url=<youtube url>&format=mp4|best|720p|audio'], 400);
}

$id = extract_id($url);
if ($id === null) {
    json_out(['error' => 'not a valid YouTube URL or video id', 'url' => $url], 400);
}

switch ($format) {
    case 'best':
        $fmt = 'bestvideo+bestaudio/best';
        break;
    case '720p':
        $fmt = 'bestvideo[ext=mp4][height<=720]+bestaudio[ext=m4a]/best[ext=mp4][height<=720]/best';
        break;
    case 'audio':
        $fmt = 'bestaudio/best';
        break;
    case 'mp4':
    default:
        $fmt = 'best[ext=mp4][height<=720]/best[ext=mp4]/best';
        break;
}

$cacheKey = $id . '|' . $format;
$cacheFile = cache_path($cacheKey);
$cached = null;
if (!$nocache && is_file($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_TTL) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cached)) {
        $cached['cached'] = true;
    }
}

if ($cached === null) {
    $watch = 'https://www.youtube.com/watch?v=' . $id;
    $cmd = [
        PYTHON, '-m', 'yt_dlp',
        '--no-playlist', '--no-warnings', '--no-progress',
        '--socket-timeout', '20',
    ];
    if ($client !== '') {
        $cmd[] = '--extractor-args';
        $cmd[] = 'youtube:player_client=' . $client;
    }
    if (defined('YT_COOKIES') && YT_COOKIES !== '' && is_file(YT_COOKIES) && ($_GET['nocookies'] ?? '') !== '1') {
        $cmd[] = '--cookies';
        $cmd[] = YT_COOKIES;
    }
    $cmd = array_merge($cmd, [
        '--format', $fmt,
        '-J',
        $watch,
    ]);
    $result = run_cmd($cmd, CMD_TIMEOUT);

    if (!$result['ok'] || $result['code'] !== 0) {
        $detail = $result['err'] !== '' ? $result['err'] : $result['out'];
        if (stripos($detail, 'is not a valid URL') !== false) {
            json_out(['error' => 'video not found or URL rejected by YouTube', 'detail' => $detail], 404);
        }
        json_out(['error' => 'yt-dlp failed', 'detail' => $detail], 502);
    }

    $j = json_decode($result['out']);
    if (!is_object($j) || !isset($j->title)) {
        json_out(['error' => 'could not parse yt-dlp output', 'detail' => $result['out']], 502);
    }

    $title = (string) $j->title;
    $entries = [];

    if (isset($j->requested_formats) && is_array($j->requested_formats)) {
        foreach ($j->requested_formats as $f) {
            if (isset($f->url) && $f->url) {
                $entries[] = [
                    'title'  => $title,
                    'url'    => (string) $f->url,
                    'format' => fmt_label($f),
                    'ext'    => (string) ($f->ext ?? ''),
                ];
            }
        }
    } elseif (isset($j->url) && $j->url) {
        $entries[] = [
            'title'  => $title,
            'url'    => (string) $j->url,
            'format' => fmt_label($j),
            'ext'    => (string) ($j->ext ?? ''),
        ];
    }

    if (count($entries) === 0) {
        json_out(['error' => 'no playable format found', 'detail' => $result['out']], 502);
    }

    $cached = [
        'id'           => $id,
        'query'        => $watch,
        'mode'         => $format,
        'title'        => $title,
        'url'          => $entries[0]['url'],
        'entries'      => $entries,
        'expires_hint' => '~6h (YouTube signed URL)',
        'cached'       => false,
    ];
    @file_put_contents($cacheFile, json_encode($cached, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

if ($redirect && isset($cached['url'])) {
    header('Location: ' . $cached['url'], true, 302);
    exit;
}

if ($stream && isset($cached['url'])) {
    $isHead = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD');
    $range = $_SERVER['HTTP_RANGE'] ?? '';

    if ($format === 'audio' && ($_GET['mp3'] ?? '') === '1') {
        $outBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ytmp3_' . $id;
        $mp3File = $outBase . '.mp3';
        if (!is_file($mp3File) || filesize($mp3File) < 10000) {
            $watch = 'https://www.youtube.com/watch?v=' . $id;
            $mp3Cmd = [
                PYTHON, '-m', 'yt_dlp',
                '--no-playlist', '--no-warnings', '--no-progress',
                '--socket-timeout', '20',
            ];
            if (FFMPEG_DIR !== '') {
                $mp3Cmd[] = '--ffmpeg-location';
                $mp3Cmd[] = FFMPEG_DIR;
            }
            if ($client !== '') {
                $mp3Cmd[] = '--extractor-args';
                $mp3Cmd[] = 'youtube:player_client=' . $client;
            }
            if (defined('YT_COOKIES') && YT_COOKIES !== '' && is_file(YT_COOKIES)) {
                $mp3Cmd[] = '--cookies';
                $mp3Cmd[] = YT_COOKIES;
            }
            $mp3Cmd = array_merge($mp3Cmd, [
                '-f', 'bestaudio/best',
                '-x', '--audio-format', 'mp3', '--audio-quality', '0',
                '-o', $outBase,
                $watch,
            ]);
            $result = run_cmd($mp3Cmd, MP3_TIMEOUT);
            if ($result['code'] !== 0 || !is_file($mp3File) || filesize($mp3File) < 10000) {
                @unlink($mp3File);
                json_out(['error' => 'mp3 conversion failed', 'detail' => $result['out']], 502);
            }
        }

        $size = (int) filesize($mp3File);
        $name = preg_replace('/[^A-Za-z0-9 _.\-]+/', '-', (string) ($cached['title'] ?? 'audio'));
        $name = trim($name, ' .-');
        if ($name === '') {
            $name = 'audio-' . $id;
        }
        http_response_code($range !== '' ? 206 : 200);
        header('Content-Type: audio/mpeg');
        header('Accept-Ranges: bytes');
        header('Content-Disposition: attachment; filename="' . $name . '.mp3"');
        header('Content-Length: ' . $size);
        if ($isHead) {
            exit;
        }

        $fp = fopen($mp3File, 'rb');
        if ($fp) {
            $start = 0;
            $end = $size - 1;
            if ($range !== '' && preg_match('/bytes=(\d+)-(\d*)/', $range, $m)) {
                $start = max(0, (int) $m[1]);
                if ($m[2] !== '') {
                    $end = min((int) $m[2], $size - 1);
                }
                header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
                header('Content-Length: ' . ($end - $start + 1));
            }
            fseek($fp, $start);
            $remaining = $end - $start + 1;
            while ($remaining > 0 && !feof($fp)) {
                $chunk = fread($fp, min(65536, $remaining));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                echo $chunk;
                $remaining -= strlen($chunk);
            }
            fclose($fp);
        }
        exit;
    }

    $src = $cached['url'];
    @ob_end_clean();
    $status = ($range !== '') ? 206 : 200;
    http_response_code($status);

    if (isset($_GET['dl']) && $_GET['dl'] === '1' && !$isHead) {
        $ext = $cached['entries'][0]['ext'] ?? ($format === 'audio' ? 'm4a' : 'mp4');
        $name = preg_replace('/[^A-Za-z0-9 _.\-]+/', '-', (string) ($cached['title'] ?? 'video'));
        $name = trim($name, ' .-');
        if ($name === '') {
            $name = 'youtube-' . $id;
        }
        header('Content-Disposition: attachment; filename="' . $name . '.' . $ext . '"');
    }

    $ch = curl_init($src);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_BUFFERSIZE     => 65536,
        CURLOPT_HTTPHEADER     => $range !== '' ? ['Range: ' . $range] : [],
        CURLOPT_NOBODY         => $isHead,
        CURLOPT_WRITEFUNCTION  => function ($c, $data) {
            echo $data;
            return strlen($data);
        },
        CURLOPT_HEADERFUNCTION => function ($c, $header) {
            $h = trim($header);
            $low = strtolower($h);
            foreach (['content-type:', 'content-length:', 'content-range:', 'accept-ranges:', 'etag:', 'last-modified:'] as $f) {
                if (str_starts_with($low, $f)) {
                    header($h);
                    break;
                }
            }
            return strlen($header);
        },
    ]);
    curl_exec($ch);
    curl_close($ch);
    exit;
}

json_out($cached);
