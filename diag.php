<?php
header('Content-Type: text/plain; charset=utf-8');
echo "YT_COOKIES_B64 set: " . (getenv('YT_COOKIES_B64') !== false && getenv('YT_COOKIES_B64') !== '' ? 'yes (len ' . strlen((string) getenv('YT_COOKIES_B64')) . ')' : 'NO') . "\n";
$cf = '/etc/ytdl/cookies.txt';
echo "cookies file exists: " . (is_file($cf) ? 'yes' : 'no') . "\n";
if (is_file($cf)) { echo "cookies file size: " . filesize($cf) . "\n"; }
echo "PYTHON: " . (defined('PYTHON') ? PYTHON : getenv('PYTHON')) . "\n";
echo "---\n";
exec('python3 -m yt_dlp --version 2>&1', $o, $c);
echo "yt-dlp version: " . implode("\n", $o) . "\n";
exec('ls -la /etc/ytdl 2>&1', $o2, $c2);
echo "ls /etc/ytdl:\n" . implode("\n", $o2) . "\n";
