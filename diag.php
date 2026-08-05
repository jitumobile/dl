<?php
header('Content-Type: text/plain; charset=utf-8');
echo "YT_COOKIES_B64 set: " . (getenv('YT_COOKIES_B64') !== false && getenv('YT_COOKIES_B64') !== '' ? 'yes (len ' . strlen((string) getenv('YT_COOKIES_B64')) . ')' : 'NO') . "\n";
$cf = '/etc/ytdl/cookies.txt';
echo "cookies file exists: " . (is_file($cf) ? 'yes' : 'no') . "\n";
if (is_file($cf)) { echo "cookies file size: " . filesize($cf) . "\n"; }
echo "---\n";
exec('python3 -m yt_dlp --version 2>&1', $o, $c);
echo "yt-dlp version: " . implode("\n", $o) . "\n";
exec('which ffprobe ffmpeg 2>&1', $w, $wc);
echo "ffprobe/ffmpeg: " . implode(' | ', $w) . "\n";
exec('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "https://test-videos.co.uk/vids/bigbuckbunny/mp4/h264/720/Big_Buck_Bunny_720_10s_1MB.mp4" 2>&1', $pf, $pc);
echo "ffprobe test: code=" . $pc . " out=" . implode(' | ', $pf) . "\n";
