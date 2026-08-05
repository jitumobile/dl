<?php
/* Mobile-first YouTube -> video dashboard. API backend: ytdl.php */
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>YouTube &rarr; Video</title>
<style>
  :root {
    --bg: #0f1117;
    --card: #1a1e27;
    --border: #2a2f3a;
    --text: #e8eaf0;
    --muted: #9aa3b2;
    --accent: #ff3d3d;
    --accent-dark: #d32f2f;
    --ok: #4caf50;
    --err: #ff5252;
  }
  * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
  html, body { margin: 0; padding: 0; }
  body {
    background: var(--bg);
    color: var(--text);
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: env(safe-area-inset-top) 12px env(safe-area-inset-bottom);
  }
  .wrap { width: 100%; max-width: 640px; padding: 20px 0 40px; }

  h1 {
    font-size: 20px;
    font-weight: 700;
    letter-spacing: .2px;
    margin: 0 0 18px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  h1 .dot { width: 12px; height: 12px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 12px var(--accent); }

  .card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 16px;
  }

  .input-row { display: flex; gap: 10px; }
  .url-wrap { position: relative; flex: 1; min-width: 0; }
  #url {
    width: 100%;
    background: #12151d;
    border: 1.5px solid var(--border);
    border-radius: 14px;
    color: var(--text);
    font-size: 16px;
    padding: 14px 44px 14px 16px;
    outline: none;
  }
  #url:focus { border-color: var(--accent); }

  #clearBtn {
    position: absolute;
    top: 50%;
    right: 8px;
    transform: translateY(-50%);
    width: 28px;
    height: 28px;
    border: none;
    border-radius: 50%;
    background: var(--border);
    color: var(--text);
    font-size: 15px;
    line-height: 1;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 0;
  }
  #clearBtn:active { background: var(--muted); }
  #clearBtn.show { display: flex; }

  #playBtn {
    flex-shrink: 0;
    border: none;
    border-radius: 14px;
    background: var(--accent);
    color: #fff;
    font-size: 16px;
    font-weight: 700;
    padding: 0 22px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }
  #playBtn:active { background: var(--accent-dark); }
  #playBtn:disabled { opacity: .6; }

  .fmt-row { display: flex; gap: 8px; margin-top: 14px; }
  .fmt {
    flex: 1;
    background: #12151d;
    border: 1.5px solid var(--border);
    color: var(--muted);
    border-radius: 12px;
    padding: 10px 0;
    font-size: 14px;
    font-weight: 600;
    text-align: center;
    cursor: pointer;
  }
  .fmt.active { background: var(--accent); border-color: var(--accent); color: #fff; }

  .meta { margin-top: 18px; display: none; }
  .meta.show { display: block; }
  .meta .title { font-size: 15px; font-weight: 600; line-height: 1.4; margin-bottom: 8px; }
  .chip {
    display: inline-block;
    background: rgba(76,175,80,.15);
    color: var(--ok);
    border: 1px solid rgba(76,175,80,.4);
    border-radius: 999px;
    padding: 3px 12px;
    font-size: 12px;
    font-weight: 600;
  }

  .player { margin-top: 16px; display: none; }
  .player.show { display: block; }
  .player video, .player audio { width: 100%; border-radius: 12px; background: #000; }
  .player video { aspect-ratio: 16/9; }

  .dl-row { margin-top: 12px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
  .dl-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--card);
    border: 1.5px solid var(--border);
    color: var(--text);
    text-decoration: none;
    border-radius: 12px;
    padding: 12px 26px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
  }
  .dl-btn:active { border-color: var(--accent); color: var(--accent); }
  .dl-btn .ico { font-size: 16px; }
  .dl-btn:disabled { opacity: .6; }

  .msg { display: none; margin-top: 14px; font-size: 14px; padding: 12px 14px; border-radius: 12px; }
  .msg.err { display: block; background: rgba(255,82,82,.12); color: var(--err); border: 1px solid rgba(255,82,82,.35); }

  .loading { display: none; margin-top: 14px; align-items: center; gap: 10px; color: var(--muted); font-size: 14px; }
  .loading.show { display: flex; }
  .spinner {
    width: 18px; height: 18px;
    border: 2.5px solid var(--border);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin .8s linear infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  .hint { margin-top: 12px; color: var(--muted); font-size: 12px; line-height: 1.5; }
</style>
</head>
<body>
<div class="wrap">
  <h1><span class="dot"></span>YouTube &rarr; Video</h1>

  <div class="card">
    <div class="input-row">
      <div class="url-wrap">
        <input id="url" type="url" inputmode="url" placeholder="Paste YouTube link or video ID" autocomplete="off">
        <button id="clearBtn" type="button" aria-label="Clear">&times;</button>
      </div>
      <button id="playBtn">Play</button>
    </div>

    <div class="fmt-row" id="fmtRow">
      <button class="fmt active" data-f="mp4">mp4</button>
      <button class="fmt" data-f="720p">720p</button>
      <button class="fmt" data-f="audio">audio</button>
      <button class="fmt" data-f="best">best</button>
    </div>
  </div>

  <div class="loading" id="loading"><span class="spinner"></span><span>Resolving stream&hellip;</span></div>
  <div class="msg err" id="err"></div>

  <div class="meta card" id="meta">
    <div class="title" id="title"></div>
    <span class="chip" id="chip"></span>
  </div>

  <div class="player" id="player">
    <video id="video" controls playsinline></video>
    <audio id="audio" controls></audio>
    <div class="dl-row">
      <a class="dl-btn" id="dlBtn" href="#" download><span class="ico">&#8681;</span><span id="dlLabel">Download</span></a>
    </div>
  </div>

  <div class="hint" id="hint">Paste any YouTube URL or video ID. Video streams through this server, so it plays on any device/IP. Links auto-expire ~6h; hit Play again to refresh.</div>
</div>

<script>
(function () {
  const $ = (id) => document.getElementById(id);
  const input = $('url'), playBtn = $('playBtn'), clearBtn = $('clearBtn');
  const meta = $('meta'), title = $('title'), chip = $('chip');
  const player = $('player'), video = $('video'), audio = $('audio');
  const loading = $('loading'), errBox = $('err'), dlBtn = $('dlBtn'), dlLabel = $('dlLabel');
  let format = 'mp4';

  function setFmt(f) {
    format = f;
    document.querySelectorAll('.fmt').forEach(b => b.classList.toggle('active', b.dataset.f === f));
    localStorage.setItem('ytFmt', f);
  }
  document.querySelectorAll('.fmt').forEach(b => {
    b.addEventListener('click', () => setFmt(b.dataset.f));
  });

  input.addEventListener('keydown', (e) => { if (e.key === 'Enter') play(); });
  input.addEventListener('input', () => {
    clearBtn.classList.toggle('show', input.value.length > 0);
  });
  clearBtn.addEventListener('click', () => {
    input.value = '';
    input.focus();
    clearBtn.classList.remove('show');
  });
  playBtn.addEventListener('click', play);

  // restore last session
  const last = localStorage.getItem('ytUrl');
  if (last) input.value = last;
  const lastFmt = localStorage.getItem('ytFmt');
  if (['mp4','720p','audio','best'].includes(lastFmt)) setFmt(lastFmt);
  clearBtn.classList.toggle('show', input.value.length > 0);
  if (last) play();

  async function play() {
    const raw = input.value.trim();
    if (!raw) { showErr('Paste a YouTube link or video ID first.'); return; }
    hideErr();
    loading.classList.add('show');
    playBtn.disabled = true;
    meta.classList.remove('show');
    player.classList.remove('show');

    const url = encodeURIComponent(raw);
    const api = 'ytdl.php?url=' + url + '&format=' + format;

    try {
      const resp = await fetch(api);
      const data = await resp.json();
      if (!resp.ok || data.error) {
        showErr((data.error || 'Request failed') + (data.detail ? ' — ' + data.detail : ''));
        return;
      }
      title.textContent = data.title || '';
      chip.textContent = data.entries.map(e => e.format).join(' + ') || (data.mode || format);

      const isAudio = format === 'audio';
      video.style.display = isAudio ? 'none' : '';
      audio.style.display = isAudio ? '' : 'none';
      const el = isAudio ? audio : video;

      el.src = api + '&stream=1';
      if (format === 'audio') {
        dlBtn.href = api + '&stream=1&mp3=1';
        dlLabel.textContent = 'Download MP3';
      } else {
        dlBtn.href = api + '&stream=1&dl=1';
        dlLabel.textContent = 'Download';
      }
      player.classList.add('show');
      meta.classList.add('show');
      localStorage.setItem('ytUrl', raw);
      el.play().catch(() => {});
    } catch (ex) {
      showErr('Network error: ' + ex.message);
    } finally {
      loading.classList.remove('show');
      playBtn.disabled = false;
    }
  }

  function showErr(m) { errBox.textContent = m; errBox.style.display = 'block'; }
  function hideErr() { errBox.style.display = 'none'; }
})();
</script>
</body>
</html>
