/* Toinky Voice Narrator — reads story text aloud on the play screen.
 *
 * Engine: Kokoro-82M (Apache 2.0 — model and all voices) via kokoro-js,
 * loaded lazily from the jsDelivr CDN. Falls back to the browser-native
 * Web Speech API when Kokoro cannot load. All synthesis runs client-side,
 * so each player's narration is independent and costs the server nothing.
 *
 * Settings are per player (localStorage, per browser): enable/disable,
 * voice, auto-play, wait-for-player, rate, volume.
 *
 * Scope: the narrator reads ONLY the current story beat (the Now card)
 * and, when the story ends, the epilogue. It deliberately does NOT read
 * the whole timeline history — narrating dozens of past entries back to
 * back blocks the page (neural synthesis runs on the main thread) and
 * makes the choice buttons unresponsive. Only the latest beat is ever
 * queued; older unplayed beats are dropped.
 */
(function () {
  'use strict';

  var MODEL_ID = 'onnx-community/Kokoro-82M-v1.0-ONNX';
  var KOKORO_CDN = 'https://cdn.jsdelivr.net/npm/kokoro-js@1.2.1/+esm';
  var STORE_KEY = 'toinky.narrator.v1';
  var POLL_MS = 2000;

  // Kokoro voices (name, gender/accent, tone). Quality grades are from the
  // kokoro-js voice table. am_adam is deliberately excluded (graded F+).
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
    { id: 'system', label: 'System voice — your browser\u2019s built-in voice' }
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
    try {
      s = JSON.parse(localStorage.getItem(STORE_KEY) || '{}') || {};
    } catch (e) { s = {}; }
    var out = {};
    Object.keys(DEFAULTS).forEach(function (k) {
      out[k] = (s[k] !== undefined) ? s[k] : DEFAULTS[k];
    });
    return out;
  }

  function saveSettings(s) {
    try { localStorage.setItem(STORE_KEY, JSON.stringify(s)); } catch (e) {}
  }

  function hash(str) {
    var h = 5381;
    for (var i = 0; i < str.length; i++) {
      h = ((h << 5) + h + str.charCodeAt(i)) | 0;
    }
    return 'h' + (h >>> 0).toString(36);
  }

  function cleanText(str) {
    return (str || '').replace(/\s+/g, ' ').trim();
  }

  var settings = loadSettings();
  var tts = null;            // KokoroTTS instance (once loaded)
  var engineLoading = null;  // in-flight Kokoro load promise
  var engineFailed = false;  // Kokoro could not load — use system voices
  var audioCtx = null;
  var gainNode = null;
  var currentSource = null;  // active AudioBufferSourceNode
  var currentUtterance = null; // active SpeechSynthesisUtterance
  var speaking = false;
  var waitingForPlayer = false;
  var pending = null;        // at most ONE queued beat (the latest)
  var lastBeatKey = '';      // fingerprint of the beat already spoken/queued
  var lastEpilogueKey = '';
  var pollTimer = null;
  var els = {};

  function $(id) { return document.getElementById(id); }

  function setStatus(text, isError) {
    if (els.status) {
      els.status.textContent = text;
      els.status.classList.toggle('narrator-error', !!isError);
    }
  }

  function updateButtons() {
    if (els.readNow) els.readNow.disabled = !settings.enabled || speaking;
    if (els.stopBtn) els.stopBtn.disabled = !speaking && !pending && !waitingForPlayer;
    if (els.continueBtn) els.continueBtn.hidden = !waitingForPlayer;
  }

  /* ---------- engines ---------- */

  function getAudioContext() {
    if (!audioCtx) {
      var AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return null;
      audioCtx = new AC();
      gainNode = audioCtx.createGain();
      gainNode.gain.value = settings.volume;
      gainNode.connect(audioCtx.destination);
    }
    if (audioCtx.state === 'suspended') audioCtx.resume();
    return audioCtx;
  }

  function ensureKokoro(progressCb) {
    if (tts) return Promise.resolve(tts);
    if (engineFailed) return Promise.reject(new Error('kokoro-unavailable'));
    if (!engineLoading) {
      engineLoading = import(KOKORO_CDN).then(function (mod) {
        return mod.KokoroTTS.from_pretrained(MODEL_ID, {
          dtype: 'q4f16',
          device: 'wasm',
          progress_callback: progressCb || null
        });
      }).then(function (instance) {
        tts = instance;
        return tts;
      }).catch(function (err) {
        engineFailed = true;
        engineLoading = null;
        throw err;
      });
    }
    return engineLoading;
  }

  function playKokoroAudio(audio) {
    return new Promise(function (resolve, reject) {
      var ctx = getAudioContext();
      if (!ctx) { reject(new Error('no-audio-context')); return; }
      var data = audio.data;
      var rate = audio.sampling_rate || 24000;
      var buffer;
      try {
        buffer = ctx.createBuffer(1, data.length, rate);
        buffer.getChannelData(0).set(data);
      } catch (e) { reject(e); return; }
      var src = ctx.createBufferSource();
      src.buffer = buffer;
      src.playbackRate.value = settings.rate;
      src.connect(gainNode);
      currentSource = src;
      src.onended = function () {
        if (currentSource === src) currentSource = null;
        resolve();
      };
      try { src.start(); } catch (e) { reject(e); }
    });
  }

  function speakKokoro(text) {
    return ensureKokoro(function (p) {
      if (p && p.status === 'progress' && p.progress !== undefined) {
        setStatus('Loading narrator voice… ' + Math.round(p.progress) + '%');
      }
    }).then(function (instance) {
      setStatus('Speaking…');
      return instance.generate(text, { voice: settings.voice, speed: settings.rate });
    }).then(function (audio) {
      return playKokoroAudio(audio);
    });
  }

  function speakSystem(text) {
    return new Promise(function (resolve) {
      if (!('speechSynthesis' in window)) { resolve(); return; }
      var u = new SpeechSynthesisUtterance(text);
      u.rate = settings.rate;
      u.volume = settings.volume;
      var voices = window.speechSynthesis.getVoices() || [];
      var en = null;
      for (var i = 0; i < voices.length; i++) {
        if (/^en/i.test(voices[i].lang || '')) { en = voices[i]; break; }
      }
      if (en) u.voice = en;
      currentUtterance = u;
      u.onend = function () { if (currentUtterance === u) currentUtterance = null; resolve(); };
      u.onerror = function () { if (currentUtterance === u) currentUtterance = null; resolve(); };
      setStatus('Speaking…');
      window.speechSynthesis.speak(u);
    });
  }

  function speak(text) {
    if (settings.voice === 'system' || engineFailed) return speakSystem(text);
    return speakKokoro(text).catch(function () {
      engineFailed = true;
      setStatus('Narrator voice unavailable — using your system voice.');
      return speakSystem(text);
    });
  }

  function stopPlayback() {
    if (currentSource) {
      try { currentSource.onended = null; currentSource.stop(); } catch (e) {}
      currentSource = null;
    }
    if (currentUtterance && 'speechSynthesis' in window) {
      try { window.speechSynthesis.cancel(); } catch (e) {}
      currentUtterance = null;
    }
    speaking = false;
  }

  /* ---------- current beat ---------- */

  // Read ONLY the current beat: the Now card (headline + event text +
  // decision prompt) or, when the story is complete, the epilogue.
  // Returns {key, text} or null when there is nothing new to read.
  function currentBeat() {
    var live = document.querySelector('#game-live');
    if (!live) return null;
    var now = live.querySelector('.now-card');
    if (now) {
      var ev = now.querySelector('.now-event');
      var text = ev ? cleanText(ev.textContent) : '';
      var dec = now.querySelector('.now-decision');
      if (dec) {
        var prompt = dec.querySelector('p');
        if (prompt) {
          var ptext = cleanText(prompt.textContent);
          if (ptext) text = text ? (text + ' Decision. ' + ptext) : ('Decision. ' + ptext);
        }
      }
      if (!text) return null;
      return { key: hash('beat:' + text), text: text };
    }
    // Story complete: read the epilogue once.
    var outcome = live.querySelector('.outcome');
    if (outcome) {
      var epi = outcome.querySelector('p');
      var etext = epi ? cleanText(epi.textContent) : '';
      if (!etext) return null;
      return { key: hash('epilogue:' + etext), text: etext, epilogue: true };
    }
    return null;
  }

  function enqueueNew() {
    if (!settings.enabled || !settings.autoplay) return;
    var beat = currentBeat();
    if (!beat) return;
    // Only the latest beat is ever queued. If a newer beat arrived while
    // one was waiting, the older one is dropped — the narrator never falls
    // behind the story, and the page never queues up synthesis work.
    if (beat.epilogue) {
      if (beat.key === lastEpilogueKey) return;
      lastEpilogueKey = beat.key;
    } else {
      if (beat.key === lastBeatKey) return;
      lastBeatKey = beat.key;
    }
    pending = beat;
    pump();
  }

  function pump() {
    if (speaking || waitingForPlayer) return;
    if (!settings.enabled) return;
    var next = pending;
    pending = null;
    if (!next) { updateButtons(); return; }
    speaking = true;
    updateButtons();
    speak(next.text).then(function () {
      speaking = false;
      if (settings.waitForPlayer && pending) {
        waitingForPlayer = true;
        setStatus('Paused — tap Continue when you\u2019re ready.');
        updateButtons();
        return;
      }
      setStatus('Ready.');
      updateButtons();
      pump();
    }).catch(function () {
      speaking = false;
      setStatus('Narration stopped.');
      updateButtons();
    });
  }

  function readNow() {
    if (!settings.enabled) return;
    var beat = currentBeat();
    if (!beat) return;
    // Read the current beat immediately, replacing anything pending.
    stopPlayback();
    waitingForPlayer = false;
    lastBeatKey = beat.key;
    if (beat.epilogue) lastEpilogueKey = beat.key;
    pending = beat;
    pump();
  }

  function stopAll() {
    stopPlayback();
    pending = null;
    waitingForPlayer = false;
    setStatus(settings.enabled ? 'Ready.' : 'Narration off.');
    updateButtons();
  }

  function continueNarration() {
    if (!waitingForPlayer) return;
    waitingForPlayer = false;
    setStatus('Ready.');
    updateButtons();
    pump();
  }

  /* ---------- settings / UI ---------- */

  function applySettingsToUI() {
    if (els.enable) els.enable.checked = !!settings.enabled;
    if (els.voice) els.voice.value = settings.voice;
    if (els.autoplay) els.autoplay.checked = !!settings.autoplay;
    if (els.waitForPlayer) els.waitForPlayer.checked = !!settings.waitForPlayer;
    if (els.rate) els.rate.value = String(settings.rate);
    if (els.rateVal) els.rateVal.textContent = settings.rate.toFixed(2).replace(/0$/, '') + '×';
    if (els.volume) els.volume.value = String(settings.volume);
    if (els.volumeVal) els.volumeVal.textContent = Math.round(settings.volume * 100) + '%';
    if (gainNode) gainNode.gain.value = settings.volume;
    setStatus(settings.enabled ? 'Ready.' : 'Narration off.');
    updateButtons();
  }

  function onEnableChange() {
    settings.enabled = !!els.enable.checked;
    saveSettings(settings);
    if (settings.enabled) {
      // Enabling is the user gesture that unlocks audio. Warm up the
      // engine in the background so the first narration starts fast.
      setStatus('Loading narrator voice…');
      getAudioContext();
      if (settings.voice !== 'system') {
        ensureKokoro(function (p) {
          if (p && p.status === 'progress' && p.progress !== undefined) {
            setStatus('Loading narrator voice… ' + Math.round(p.progress) + '%');
          }
        }).then(function () {
          setStatus('Ready.');
          enqueueNew();
        }).catch(function () {
          engineFailed = true;
          setStatus('Narrator voice unavailable — using your system voice.');
          enqueueNew();
        });
      } else {
        setStatus('Ready.');
        enqueueNew();
      }
    } else {
      stopAll();
    }
    applySettingsToUI();
  }

  function init() {
    els = {
      panel: $('narrator-panel'),
      enable: $('narrator-enable'),
      voice: $('narrator-voice'),
      autoplay: $('narrator-autoplay'),
      waitForPlayer: $('narrator-wait'),
      rate: $('narrator-rate'),
      rateVal: $('narrator-rate-val'),
      volume: $('narrator-volume'),
      volumeVal: $('narrator-volume-val'),
      readNow: $('narrator-read-now'),
      stopBtn: $('narrator-stop'),
      continueBtn: $('narrator-continue'),
      status: $('narrator-status')
    };
    if (!els.panel) return; // not a play page with the narrator panel

    // Populate the voice picker.
    if (els.voice) {
      VOICES.forEach(function (v) {
        var opt = document.createElement('option');
        opt.value = v.id;
        opt.textContent = v.label;
        els.voice.appendChild(opt);
      });
    }

    if (els.enable) els.enable.addEventListener('change', onEnableChange);
    if (els.voice) els.voice.addEventListener('change', function () {
      settings.voice = els.voice.value;
      saveSettings(settings);
      // Switching to a Kokoro voice after a failed load retries the engine.
      if (settings.voice !== 'system') engineFailed = false;
      // Stop whatever is playing in the old voice so the change is heard
      // immediately instead of after a queued backlog.
      stopPlayback();
      pending = null;
      waitingForPlayer = false;
      applySettingsToUI();
      if (settings.enabled) enqueueNew();
    });
    if (els.autoplay) els.autoplay.addEventListener('change', function () {
      settings.autoplay = !!els.autoplay.checked;
      saveSettings(settings);
      if (settings.autoplay) enqueueNew();
      applySettingsToUI();
    });
    if (els.waitForPlayer) els.waitForPlayer.addEventListener('change', function () {
      settings.waitForPlayer = !!els.waitForPlayer.checked;
      saveSettings(settings);
      applySettingsToUI();
    });
    if (els.rate) els.rate.addEventListener('input', function () {
      settings.rate = parseFloat(els.rate.value) || 1;
      saveSettings(settings);
      applySettingsToUI();
    });
    if (els.volume) els.volume.addEventListener('input', function () {
      settings.volume = parseFloat(els.volume.value) || 0;
      saveSettings(settings);
      applySettingsToUI();
    });
    if (els.readNow) els.readNow.addEventListener('click', readNow);
    if (els.stopBtn) els.stopBtn.addEventListener('click', stopAll);
    if (els.continueBtn) els.continueBtn.addEventListener('click', continueNarration);

    // Keep system voices fresh (they load async in some browsers).
    if ('speechSynthesis' in window) {
      window.speechSynthesis.getVoices();
      window.speechSynthesis.onvoiceschanged = function () {};
    }

    applySettingsToUI();
    if (settings.enabled && settings.autoplay) {
      enqueueNew();
    }
    pollTimer = setInterval(enqueueNew, POLL_MS);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
