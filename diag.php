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
echo "--- test run ---\n";
$cmd = "python3 -m yt_dlp --no-playlist --no-warnings --no-progress --socket-timeout 20 --cookies /etc/ytdl/cookies.txt --format best -J 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' 2>&1";
$out3 = shell_exec($cmd . ' | tail -c 2000');
echo $out3 . "\n";
echo "--- list formats tail ---\n";
$cmd2 = "python3 -m yt_dlp --no-playlist --no-warnings --no-progress --socket-timeout 20 --cookies /etc/ytdl/cookies.txt --extractor-args 'youtube:player_client=web_safari' --list-formats 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' 2>&1";
$out4 = shell_exec($cmd2 . ' | tail -n 15');
echo $out4 . "\n";
