#!/bin/sh
set -e

if [ -n "$YT_COOKIES_B64" ]; then
  mkdir -p /etc/ytdl
  echo "$YT_COOKIES_B64" | base64 -d > /etc/ytdl/cookies.txt
  chmod 600 /etc/ytdl/cookies.txt
fi

exec "$@"
