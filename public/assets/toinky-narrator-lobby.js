/* Toinky — Voice Narrator (lobby preload).
 *
 * Runs on the game lobby page. Each player can turn narration on or off
 * here (that is just their own per-player setting, saved to the same
 * localStorage key the play page reads), and can pick their narrator
 * voice. Either way — on or off — the good-quality Kokoro voice model
 * (q8) downloads in the background while everyone waits in the lobby,
 * so it is cached and ready the moment the game starts, with no wait
 * at game start.
 *
 * Engine: Kokoro-82M (Apache 2.0 — model and all voices) via kokoro-js.
 * The model is fetched from HuggingFace and cached in this browser, so
 * the first lobby visit downloads it once (~165 MB for q8) and later
 * visits / the play page load it from cache.
 */
(function () {
  'use strict';

  var KOKORO_CDN = 'https://cdn.jsdelivr.net/npm/kokoro-js@1.2.1/+esm';
  var MODEL_ID = 'onnx-community/Kokoro-82M-v1.0-ONNX';
  var STORE_KEY = 'toinky.narrator.v1';

  // Same voice list as the play-page narrator (toinky-narrator.js).
  // am_adam is deliberately excluded (graded F+ in the kokoro-js table).
  var VOICES = [
    { id: 'af_heart', label: 'Heart — American female, warm', def: true },
    { id: 'af_bella', label: 'Bella — American female, warm & expressive' },
    { id: 'af_sarah', label: 'Sarah — American female, professional' },
    { id: 'af_sky', label: 'Sky — American female, bright' },
    { id: 'af_nicole', label: 'Nicole — American female, whisper' },
    { id: 'am_michael', label: 'Michael — American male, deep' },
    { id: 'am_fenrir', label: 'Fenrir — American male' },
    { id: 'bf_emma', label: 'Emma — British female, proper' },
    { id: 'bm_george', label: 'George — British male, formal' },
    { id: 'system', label: 'System voice — your browser’s built-in voice' }
  ];

  var DEFAULTS = {
    enabled: false,
    voice: 'af_heart',
    autoplay: true,
    waitForPlayer: false,
    rate: 1,
    volume: 1
  };

  function loadSettings() {
    var s = {};
    try { s = JSON.parse(localStorage.getItem(STORE_KEY) || '{}') || {}; } catch (e) { s = {}; }
    var out = {};
    Object.keys(DEFAULTS).forEach(function (k) {
      out[k] = (s[k] !== undefined) ? s[k] : DEFAULTS[k];
    });
    return out;
  }

  function saveSettings(s) {
    try { localStorage.setItem(STORE_KEY, JSON.stringify(s)); } catch (e) {}
  }

  var settings = loadSettings();
  var preloadState = 'idle'; // idle | loading | ready | failed
  var preloadPct = 0;

  function $(id) { return document.getElementById(id); }

  function render() {
    var statusEl = $('lobby-narrator-status');
    var toggle = $('lobby-narrator-enable');
    var voiceSel = $('lobby-narrator-voice');
    if (toggle) toggle.checked = !!settings.enabled;
    if (voiceSel && voiceSel.value !== settings.voice) voiceSel.value = settings.voice;
    if (!statusEl) return;
    if (preloadState === 'loading') {
      statusEl.textContent = 'Downloading narrator voice… ' + preloadPct +
        '% — it will be ready when the game starts.';
    } else if (preloadState === 'ready') {
      statusEl.textContent = settings.enabled
        ? 'Narrator voice ready. The story will be read aloud when the game starts.'
        : 'Narrator voice ready. Narration is off — turn it on any time, no waiting.';
    } else if (preloadState === 'failed') {
      statusEl.textContent = 'The narrator voice could not be downloaded. ' +
        'You can still play — if you turn narration on, it will use your ' +
        'browser’s built-in voice instead.';
    } else {
      statusEl.textContent = 'Preparing narrator voice…';
    }
  }

  function startPreload() {
    if (preloadState === 'loading' || preloadState === 'ready') return;
    preloadState = 'loading';
    render();
    import(KOKORO_CDN).then(function (mod) {
      return mod.KokoroTTS.from_pretrained(MODEL_ID, {
        dtype: 'q8',
        device: 'wasm',
        progress_callback: function (p) {
          if (p && p.status === 'progress' && p.progress !== undefined) {
            preloadPct = Math.round(p.progress);
            render();
          }
        }
      });
    }).then(function () {
      preloadState = 'ready';
      preloadPct = 100;
      render();
    }).catch(function () {
      preloadState = 'failed';
      render();
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var toggle = $('lobby-narrator-enable');
    var voiceSel = $('lobby-narrator-voice');
    if (!toggle && !voiceSel) return; // no narrator panel on this page
    if (voiceSel) {
      VOICES.forEach(function (v) {
        var opt = document.createElement('option');
        opt.value = v.id;
        opt.textContent = v.label;
        voiceSel.appendChild(opt);
      });
      voiceSel.value = settings.voice;
      voiceSel.addEventListener('change', function () {
        settings.voice = voiceSel.value;
        saveSettings(settings);
      });
    }
    if (toggle) {
      toggle.checked = !!settings.enabled;
      toggle.addEventListener('change', function () {
        settings.enabled = toggle.checked;
        saveSettings(settings);
        render();
      });
    }
    render();
    // Download the good-quality voice either way (narration on or off),
    // so it is cached and ready when the game starts.
    startPreload();
  });
})();
