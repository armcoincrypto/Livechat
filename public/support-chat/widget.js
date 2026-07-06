/**
 * Healthy Spine live chat widget — plain operator chat (no AI).
 * SC_WIDGET_BUILD=20260706-healthy-spine-plain-operator-chat
 * Integration: <script src="/support-chat/widget.js" defer data-chat-enabled="1" data-api-base=""></script>
 */
(function () {
  'use strict';

  var LOCALE = (function () {
    var l = (navigator.language || 'en').toLowerCase();
    if (l.indexOf('hy') === 0) return 'hy';
    if (l.indexOf('ru') === 0) return 'ru';
    return 'en';
  })();

  var STR = {
    en: {
      brand: 'Healthy Spine',
      title: 'Healthy Spine',
      launcherOpen: 'Open chat with our team',
      launcherClose: 'Close chat',
      launcherTitle: 'Chat with our team',
      operator: 'Team',
      you: 'You',
      system: 'System',
    },
    ru: {
      brand: 'Healthy Spine',
      title: 'Healthy Spine',
      launcherOpen: 'Открыть чат с нашей командой',
      launcherClose: 'Закрыть чат',
      launcherTitle: 'Чат с нашей командой',
      operator: 'Команда',
      you: 'Вы',
      system: 'Система',
    },
    hy: {
      brand: 'Healthy Spine',
      title: 'Healthy Spine',
      launcherOpen: 'Բացել խոսել մեր թիմի հետ',
      launcherClose: 'Փակել չատը',
      launcherTitle: 'Խոսել մեր թիմի հետ',
      operator: 'Թիմ',
      you: 'Դուք',
      system: 'Համակարգ',
    },
  };

  function t(key) {
    var bucket = STR[LOCALE] || STR.en;
    return bucket[key] || STR.en[key] || key;
  }

  /**
   * deferred/async scripts often have document.currentScript === null.
   * Never trust currentScript unless src clearly matches this widget — wrong node => wrong data-api-base => requests to site origin, not app API.
   */
  function isWidgetScriptEl(el) {
    if (!el || typeof el.getAttribute !== 'function') return false;
    var en = el.getAttribute('data-chat-enabled');
    if (en == null || String(en).trim() !== '1') return false;
    var src = el.src || '';
    if (src.indexOf('support-chat/widget.js') === -1) return false;
    return true;
  }

  function getWidgetScriptEl() {
    var s = document.currentScript;
    if (isWidgetScriptEl(s)) {
      return s;
    }
    var nodes = document.getElementsByTagName('script');
    for (var i = nodes.length - 1; i >= 0; i--) {
      if (isWidgetScriptEl(nodes[i])) {
        return nodes[i];
      }
    }
    return null;
  }

  var script = getWidgetScriptEl();
  if (!script) {
    return;
  }
  if (document.getElementById('healthy-spine-support-chat-host')) {
    return;
  }

  var STORAGE_UUID = 'healthy_spine_support_chat_conversation_uuid';
  var STORAGE_TOKEN = 'healthy_spine_support_chat_access_token';

  function normalizeStoredSessionValue(raw) {
    if (raw == null) return null;
    var t = String(raw).trim();
    if (t === '' || t === 'null' || t === 'undefined') return null;
    return t;
  }

  var BASE_POLL_MS = 4000;
  var MAX_POLL_MS = 28000;
  /** px from bottom — user is "following" the thread */
  var SCROLL_NEAR_BOTTOM_PX = 72;

  function normalizeApiBase(raw) {
    var s = (raw || '').trim();
    if (!s) {
      return window.location.origin.replace(/\/$/, '');
    }
    return s.replace(/\/$/, '');
  }

  var API_BASE = normalizeApiBase(script.getAttribute('data-api-base'));
  var FALLBACK_TELEGRAM_URL = (script.getAttribute('data-telegram-url') || '').trim();
  var FALLBACK_WHATSAPP_URL = (script.getAttribute('data-whatsapp-url') || '').trim();
  var pollMsMin = BASE_POLL_MS;
  try {
    var p = parseInt(script.getAttribute('data-poll-ms') || '', 10);
    if (!isNaN(p) && p >= 2000 && p <= 60000) {
      pollMsMin = p;
    }
  } catch (e) {}

  var pollIntervalMs = pollMsMin;
  var SC_DEBUG =
    script.getAttribute('data-sc-debug') === '1' ||
    (typeof window !== 'undefined' && window.__HEALTHY_SPINE_SC_DEBUG === true);
  var ECHO_PREVIEW_GRACE_MS = 30000;

  function scDebug() {
    if (!SC_DEBUG || typeof console === 'undefined' || !console.log) return;
    var args = ['[healthy-spine-sc]'].concat(Array.prototype.slice.call(arguments));
    console.log.apply(console, args);
  }

  function scDebugBlobRef(url) {
    if (!url || typeof url !== 'string') return null;
    if (url.indexOf('blob:') === 0) return 'blob:…';
    if (url.indexOf('http://') === 0 || url.indexOf('https://') === 0) return 'http:…';
    return 'url:…';
  }

  function scDebugEchoSummary(echo) {
    if (!echo) return null;
    return {
      local_id: echo.local_id,
      attachment_id: echo.attachment_id,
      mime_type: echo.mime_type,
      original_name: echo.original_name,
      hasLocalPreview: !!echo.localPreviewUrl,
      localPreview: scDebugBlobRef(echo.localPreviewUrl),
      uploadedAgeMs: echo.uploadedAt ? Date.now() - echo.uploadedAt : null,
    };
  }

  function apiUrl(path) {
    return API_BASE + path;
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  /** Parse response body as JSON; null if empty or non-JSON (proxy/HTML/500 bodies). */
  function parseFetchResponse(r) {
    return r.text().then(function (text) {
      var j = null;
      var trimmed = (text || '').trim();
      if (trimmed !== '') {
        try {
          j = JSON.parse(trimmed);
        } catch (e) {
          j = null;
        }
      }
      return { ok: r.ok, status: r.status, json: j };
    });
  }

  var host = document.createElement('div');
  host.id = 'healthy-spine-support-chat-host';
  host.setAttribute('aria-live', 'polite');
  var shadow = host.attachShadow({ mode: 'open' });

  shadow.innerHTML =
    '<style>' +
    ':host{all:initial;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;' +
    '--sc-vh:100vh;--sc-vw:100vw;--sc-kb-offset:0px;--sc-purple:#7557F6;--sc-purple-deep:#5B42E0;--sc-lavender:#B9A7FF;--sc-violet-soft:#E8E2FF;' +
    '--sc-text-dark:#1F2433;--sc-text-muted:#8490A6;--sc-border:rgba(117,87,246,.18);touch-action:manipulation}' +
    '*{box-sizing:border-box}' +
    '@keyframes scPulse{0%,100%{box-shadow:0 10px 32px rgba(117,87,246,.42),0 0 0 0 rgba(117,87,246,.18)}55%{box-shadow:0 12px 36px rgba(117,87,246,.48),0 0 0 10px rgba(117,87,246,0)}}' +
    '@keyframes scPanelIn{from{opacity:0;transform:translateY(8px) scale(.985)}to{opacity:1;transform:translateY(0) scale(1)}}' +
    '@keyframes scShimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}' +
    '.sc-wrap{position:fixed;right:max(16px,env(safe-area-inset-right));bottom:max(16px,env(safe-area-inset-bottom));z-index:2147483647;width:58px;height:58px;pointer-events:none;overflow:visible}' +
    '.sc-launcher{position:absolute;right:0;bottom:0;width:58px;height:58px;border-radius:999px;border:none;cursor:pointer;z-index:2147483647;color:#fff;pointer-events:auto;touch-action:manipulation;-webkit-tap-highlight-color:transparent;' +
    'background:linear-gradient(145deg,#5B42E0 0%,#7557F6 52%,#9B87FF 100%);' +
    'box-shadow:0 10px 32px rgba(117,87,246,.45),0 0 0 1px rgba(255,255,255,.14) inset,0 0 36px rgba(117,87,246,.22);' +
    'display:flex;align-items:center;justify-content:center;transition:transform .22s cubic-bezier(.22,1,.36,1),box-shadow .22s ease,opacity .18s ease;animation:scPulse 3.2s ease-in-out infinite}' +
    '.sc-launcher:hover{transform:translateY(-3px) scale(1.05);box-shadow:0 14px 40px rgba(117,87,246,.52),0 0 0 1px rgba(255,255,255,.18) inset,0 0 48px rgba(117,87,246,.28)}' +
    '.sc-launcher:active{transform:translateY(0) scale(.97)}' +
    '.sc-launcher:focus-visible{outline:2px solid rgba(185,167,255,.95);outline-offset:3px}' +
    '.sc-launcher.sc-launcher--open{animation:none;opacity:.88;transform:scale(.94);box-shadow:0 6px 20px rgba(117,87,246,.32),0 0 0 1px rgba(255,255,255,.12) inset}' +
    '.sc-launcher::after{display:none}' +
    '.sc-launcher svg{width:27px;height:27px;opacity:.98;filter:drop-shadow(0 1px 2px rgba(23,22,34,.18))}' +
    '.sc-panel{position:fixed;left:auto;right:24px;bottom:calc(94px + var(--sc-kb-offset,0px));width:min(430px,calc(var(--sc-vw,100vw) - 24px));max-width:min(430px,calc(var(--sc-vw,100vw) - 24px));height:min(720px,calc(var(--sc-vh,100vh) - 96px));max-height:min(720px,calc(var(--sc-vh,100vh) - 96px));' +
    'box-sizing:border-box;min-width:0;z-index:2147483640;background:rgba(252,250,255,.82);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border-radius:24px;display:none;flex-direction:column;overflow:hidden;pointer-events:auto;touch-action:manipulation;' +
    'border:1px solid var(--sc-border);box-shadow:0 0 0 1px rgba(255,255,255,.78) inset,0 28px 70px rgba(20,18,40,.26),0 10px 32px rgba(117,87,246,.16)}' +
    '.sc-panel.open{display:flex;animation:scPanelIn .28s cubic-bezier(.22,1,.36,1) both}' +
    '.sc-head{padding:11px 14px 10px;border-bottom:1px solid rgba(117,87,246,.15);display:flex;align-items:center;justify-content:space-between;gap:8px;' +
    'background:linear-gradient(135deg,rgba(117,87,246,.16) 0%,rgba(255,255,255,.9) 48%,rgba(248,246,255,.97) 100%);flex-shrink:0;box-shadow:0 1px 0 rgba(255,255,255,.65) inset}' +
    '.sc-head-brand{display:flex;align-items:center;gap:10px;flex:1;min-width:0}' +
    '.sc-avatar{width:36px;height:36px;border-radius:12px;background:linear-gradient(145deg,#7557F6 0%,#9B87FF 100%);display:flex;align-items:center;justify-content:center;flex-shrink:0;' +
    'border:1px solid rgba(255,255,255,.42);box-shadow:0 6px 18px rgba(117,87,246,.34),0 1px 0 rgba(255,255,255,.35) inset}' +
    '.sc-avatar svg{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:1.85}' +
    '.sc-head-text{flex:1;min-width:0}' +
    '.sc-title-row{display:flex;align-items:center;gap:6px;min-width:0}' +
    '.sc-title{font-size:14px;font-weight:700;color:var(--sc-text-dark);letter-spacing:-.025em;line-height:1.12;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
    '.sc-online-dot{display:inline-block;width:7px;height:7px;border-radius:999px;background:#22c55e;box-shadow:0 0 0 2px rgba(255,255,255,.9);flex-shrink:0;margin-top:1px;align-self:flex-start}' +
    '.sc-sub{font-size:11px;color:var(--sc-text-muted);margin-top:3px;line-height:1.22;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;letter-spacing:.01em}' +
    '.sc-secure{display:none}' +
    '.sc-close{flex-shrink:0;width:36px;height:36px;min-width:36px;min-height:36px;display:flex;align-items:center;justify-content:center;border:none;border-radius:11px;' +
    'cursor:pointer;background:rgba(255,255,255,.58);color:var(--sc-text-muted);pointer-events:auto;touch-action:manipulation;-webkit-tap-highlight-color:transparent;transition:background .18s,color .18s,transform .12s,box-shadow .18s}' +
    '.sc-close:hover{background:rgba(117,87,246,.14);color:var(--sc-purple);box-shadow:0 3px 10px rgba(117,87,246,.14);transform:translateY(-1px)}' +
    '.sc-close:active{transform:scale(.96)}' +
    '.sc-close:focus-visible{outline:2px solid rgba(117,87,246,.55);outline-offset:2px}' +
    '.sc-body{flex:1;min-width:0;min-height:0;overflow-x:hidden;overflow-y:auto;padding:14px 14px 12px;display:flex;flex-direction:column;gap:0;position:relative;' +
    'background-image:url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2796%27 height=%2796%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%237557F6%27 stroke-opacity=%27.07%27 stroke-width=%271%27%3E%3Cpath d=%27M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z%27/%3E%3C/svg%3E"),' +
    'radial-gradient(circle at 20% 0%,rgba(117,87,246,.12),transparent 36%),radial-gradient(circle at 90% 15%,rgba(185,167,255,.16),transparent 34%),' +
    'radial-gradient(circle at 50% 92%,rgba(117,87,246,.08),transparent 40%),linear-gradient(180deg,rgba(250,248,255,.96),rgba(246,243,255,.94));' +
    'background-position:center 42%,0 0,0 0,0 0,0 0;background-size:96px 96px,auto,auto,auto,auto;background-repeat:no-repeat,no-repeat,no-repeat,no-repeat,no-repeat;' +
    '-webkit-overflow-scrolling:touch;overscroll-behavior:contain;touch-action:pan-y;pointer-events:auto}' +
    '.sc-body::before{content:"";position:absolute;inset:0;pointer-events:none;opacity:.45;' +
    'background:repeating-linear-gradient(0deg,transparent,transparent 3px,rgba(117,87,246,.012) 3px,rgba(117,87,246,.012) 4px);mask-image:linear-gradient(180deg,rgba(0,0,0,.55),transparent 88%)}' +
    '.sc-body > *{position:relative;z-index:1}' +
    '.sc-msg-row{display:flex;width:100%;margin-top:14px;transition:transform .15s ease,opacity .15s ease}' +
    '.sc-msg-row:first-child{margin-top:0}' +
    '.sc-msg-row--group{margin-top:4px}' +
    '.sc-msg-row--vis{justify-content:flex-end}' +
    '.sc-msg-row--op,.sc-msg-row--sys{justify-content:flex-start}' +
    '.sc-msg{max-width:78%;display:flex;flex-direction:column;align-items:stretch;gap:0}' +
    '.sc-msg-row--vis .sc-msg{max-width:78%;align-items:flex-end}' +
    '.sc-msg-bubble{padding:10px 14px 11px;border-radius:20px;font-size:13px;line-height:1.45;word-break:break-word;overflow-wrap:anywhere;white-space:pre-wrap;transition:transform .15s ease,box-shadow .15s ease}' +
    '.sc-msg-text{font-size:inherit;line-height:inherit;letter-spacing:.012em}' +
    '.sc-msg--vis .sc-msg-bubble{background:linear-gradient(135deg,#24263A 0%,#171B2D 52%,#2A2460 100%);color:#fff;border:1px solid rgba(185,167,255,.16);border-bottom-right-radius:7px;' +
    'box-shadow:0 12px 30px rgba(20,18,40,.22),inset 0 1px 0 rgba(255,255,255,.08)}' +
    '.sc-msg--vis .sc-msg-bubble:hover{transform:translateY(-1px);box-shadow:0 14px 34px rgba(20,18,40,.26),inset 0 1px 0 rgba(255,255,255,.1)}' +
    '.sc-msg--op .sc-msg-bubble,.sc-msg--sys .sc-msg-bubble{background:linear-gradient(180deg,rgba(255,255,255,.92),rgba(250,248,255,.82));color:#344054;' +
    'border:1px solid rgba(117,87,246,.14);border-bottom-left-radius:7px;box-shadow:0 14px 35px rgba(74,60,140,.12);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}' +
    '.sc-msg--op .sc-msg-bubble:hover,.sc-msg--sys .sc-msg-bubble:hover{transform:translateY(-1px);box-shadow:0 16px 38px rgba(74,60,140,.15)}' +
    '.sc-msg--echo .sc-msg-bubble{background:rgba(255,255,255,.86);color:var(--sc-text-muted);border:1px dashed rgba(117,87,246,.2);font-size:11.5px;padding:7px 11px;border-radius:16px;box-shadow:none}' +
    '.sc-msg-row--echo .sc-msg{max-width:85%}' +
    '.sc-msg-foot{display:flex;align-items:center;gap:4px;margin-top:5px;padding:0 3px;font-size:10px;color:#8490A6;line-height:1.15;font-weight:500;letter-spacing:.08em;opacity:.62}' +
    '.sc-msg-row--vis .sc-msg-foot{justify-content:flex-end}' +
    '.sc-msg-row--group .sc-msg-foot{opacity:0;margin-top:0;height:0;overflow:hidden;padding:0}' +
    '.sc-msg-badge{font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:#9aa5b8;opacity:.55}' +
    '.sc-msg-row--vis .sc-msg-badge{color:rgba(117,87,246,.32)}' +
    '.sc-msg-time{font-variant-numeric:tabular-nums;font-weight:500;color:inherit;opacity:1;letter-spacing:.08em}' +
    '.sc-msg-row--vis .sc-msg-time{color:inherit}' +
    '.sc-foot{display:flex;flex-direction:column;gap:0;width:100%;min-width:0;padding:12px 14px max(12px,env(safe-area-inset-bottom));' +
    'border-top:1px solid rgba(117,87,246,.12);background:linear-gradient(180deg,rgba(255,255,255,.88),rgba(248,246,255,.84));backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);box-sizing:border-box;flex-shrink:0;pointer-events:auto;touch-action:manipulation;box-shadow:0 -8px 24px rgba(74,60,140,.06)}' +
    '.sc-composer-shell{position:relative;display:flex;flex-direction:row;flex-wrap:nowrap;align-items:center;gap:5px;width:100%;min-width:0;box-sizing:border-box;' +
    'padding:4px 5px 4px 7px;border-radius:22px;border:1px solid rgba(117,87,246,.16);background:linear-gradient(180deg,rgba(255,255,255,.94),rgba(248,246,255,.88));' +
    'box-shadow:0 4px 20px rgba(74,60,140,.1),inset 0 1px 0 rgba(255,255,255,.92);transition:border-color .18s,box-shadow .18s,transform .18s}' +
    '.sc-composer-shell:focus-within{border-color:rgba(117,87,246,.42);box-shadow:0 6px 24px rgba(117,87,246,.14),inset 0 1px 0 rgba(255,255,255,.95),0 0 0 3px rgba(117,87,246,.14);transform:translateY(-1px)}' +
    '.sc-composer-field{flex:1 1 0;min-width:0;width:auto;max-width:100%}' +
    '.sc-composer-field textarea{display:block;width:100%;min-width:0;max-width:100%;min-height:38px;max-height:96px;padding:9px 12px;border:none;border-radius:14px;' +
    'font-size:13px;line-height:1.42;color:var(--sc-text-dark);white-space:pre-wrap;overflow-wrap:break-word;word-break:normal;background:transparent;resize:none;outline:none;box-sizing:border-box}' +
    '.sc-composer-field textarea::placeholder{color:#9aa5b8;opacity:.88}' +
    '.sc-attach{flex:0 0 auto;flex-shrink:0;width:36px;height:36px;border-radius:12px;border:none;background:transparent;color:#9aa5b8;opacity:.72;' +
    'display:inline-flex;align-items:center;justify-content:center;cursor:pointer;padding:0;transition:color .18s,background .18s,transform .12s,opacity .18s}' +
    '.sc-attach svg{width:18px;height:18px}' +
    '.sc-attach:hover{color:var(--sc-purple);background:rgba(117,87,246,.1);opacity:1}' +
    '.sc-attach:active{transform:scale(.94)}' +
    '.sc-attach:focus-visible{outline:2px solid rgba(117,87,246,.55);outline-offset:1px;opacity:1}' +
    '.sc-composer-shell .sc-btn.sc-send{flex:0 0 auto;width:auto;max-width:none;min-width:46px;height:38px;padding:0 14px;border-radius:13px;font-size:12px;font-weight:650;letter-spacing:.02em;' +
    'background:linear-gradient(145deg,#5B42E0 0%,#7557F6 55%,#8268F8 100%);color:#fff;border:none;box-shadow:0 6px 16px rgba(117,87,246,.34),inset 0 1px 0 rgba(255,255,255,.18);transition:transform .14s,opacity .15s,box-shadow .18s,filter .15s}' +
    '.sc-composer-shell .sc-btn.sc-send:hover{background:linear-gradient(145deg,#6548E8 0%,#7D62F7 55%,#9078FA 100%);box-shadow:0 8px 20px rgba(117,87,246,.4),inset 0 1px 0 rgba(255,255,255,.22);transform:translateY(-1px)}' +
    '.sc-send:active:not(:disabled){transform:translateY(0) scale(.96)}' +
    '.sc-send:disabled{opacity:.48;box-shadow:none;transform:none;cursor:not-allowed;filter:saturate(.7)}' +
    '.sc-form{width:100%;min-width:0;box-sizing:border-box}' +
    '.sc-form label{display:block;font-size:11.5px;font-weight:650;color:var(--sc-text-muted);margin-bottom:5px;letter-spacing:.01em}' +
    '.sc-form input,.sc-form textarea{display:block;width:100%;min-width:0;max-width:100%;padding:9px 11px;border:1px solid rgba(117,87,246,.14);border-radius:12px;font-size:16px;background:rgba(255,255,255,.94);color:var(--sc-text-dark);' +
    'pointer-events:auto;-webkit-user-select:text;user-select:text;touch-action:manipulation;box-sizing:border-box}' +
    '.sc-form textarea{resize:vertical;min-height:72px}' +
    '.sc-row{margin-bottom:11px}' +
    '.sc-btn{background:linear-gradient(145deg,#5B42E0 0%,#7557F6 100%);color:#fff;border:none;padding:10px 16px;border-radius:12px;font-size:13px;font-weight:650;cursor:pointer;width:100%;box-shadow:0 4px 14px rgba(117,87,246,.28)}' +
    '.sc-btn:disabled{opacity:.5;cursor:not-allowed;box-shadow:none}' +
    '.sc-btn-sec{background:rgba(255,255,255,.92);color:var(--sc-text-dark);box-shadow:none;border:1px solid rgba(117,87,246,.14)}' +
    '.sc-btn-mini{padding:5px 10px;font-size:10.5px;border-radius:8px;min-width:auto;width:auto;height:auto;line-height:1.2;font-weight:600}' +
    '.sc-file-input{position:fixed;left:-10000px;top:auto;width:1px;height:1px;opacity:0;pointer-events:none;overflow:hidden}' +
    '.sc-attach:disabled{opacity:.4;cursor:not-allowed}' +
    '.sc-foot-status-slot{display:none}' +
    '.sc-foot-ms .sc-foot-status-slot{display:block;box-sizing:border-box;min-height:16px;padding:1px 3px 3px;font-size:9.5px;line-height:1.25;color:#a8b4c4;text-align:right;flex-shrink:0;opacity:0;transition:opacity .15s ease;pointer-events:none}' +
    '.sc-foot-ms .sc-foot-status-slot.sc-visible{opacity:1}' +
    '.sc-foot-status-slot.sc-warn{color:#b45309}' +
    '.sc-foot-status-slot.sc-ok{color:#059669}' +
    '.sc-att-wrap{margin-top:3px;display:flex;flex-direction:column;gap:3px;width:100%;max-width:100%}' +
    '.sc-att{position:relative;border-radius:9px;overflow:hidden;border:1px solid rgba(15,23,42,.07);background:#f3f2f0}' +
    '.sc-msg-row--vis .sc-att{border-color:rgba(255,255,255,.12);background:rgba(0,0,0,.12)}' +
    '.sc-att--img{padding:0}' +
    '.sc-att-img-frame{position:relative;height:116px;overflow:hidden;background:#eceae6}' +
    '.sc-att-skel{position:absolute;inset:0;height:auto;background:linear-gradient(110deg,rgba(226,232,240,.4) 0%,rgba(241,245,249,.8) 45%,rgba(226,232,240,.4) 80%);background-size:200% 100%;animation:scShimmer 1.2s ease-in-out infinite}' +
    '.sc-msg-row--vis .sc-att-skel{background:linear-gradient(110deg,rgba(255,255,255,.08) 0%,rgba(255,255,255,.14) 45%,rgba(255,255,255,.08) 80%);background-size:200% 100%}' +
    '.sc-att-img-btn{position:absolute;inset:0;width:100%;height:100%;max-height:none;padding:0;border:none;background:transparent;cursor:pointer;line-height:0}' +
    '.sc-att-img-el{display:block;width:100%;height:100%;max-height:none;object-fit:cover;object-position:center;visibility:hidden}' +
    '.sc-att-img-el.sc-loaded{visibility:visible}' +
    '.sc-att-broken{padding:10px 8px;font-size:10px;color:#a8b4c4;text-align:center;background:#faf9f7}' +
    '.sc-att-bar{display:flex;align-items:center;justify-content:flex-end;gap:5px;padding:4px 7px;border-top:1px solid rgba(15,23,42,.05);background:rgba(255,255,255,.5)}' +
    '.sc-msg-row--vis .sc-att-bar{border-top-color:rgba(255,255,255,.14);background:rgba(0,0,0,.08)}' +
    '.sc-doc-card{display:flex;align-items:center;gap:8px;padding:7px 9px;border-radius:10px;border:1px solid rgba(15,23,42,.06);background:#fff;box-shadow:none}' +
    '.sc-msg-row--vis .sc-doc-card{border-color:rgba(255,255,255,.2);background:rgba(255,255,255,.1);box-shadow:none}' +
    '.sc-doc-ic{width:30px;height:30px;border-radius:8px;background:linear-gradient(145deg,#fef2f2,#fee2e2);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:9px;font-weight:700;color:#b91c1c;letter-spacing:.02em}' +
    '.sc-msg-row--vis .sc-doc-ic{background:rgba(255,255,255,.16);color:#fff}' +
    '.sc-doc-meta{flex:1;min-width:0}' +
    '.sc-doc-name{font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#0f172a}' +
    '.sc-msg-row--vis .sc-doc-name{color:#f8fafc}' +
    '.sc-doc-sub{font-size:10px;color:#94a3b8;margin-top:1px}' +
    '.sc-msg-row--vis .sc-doc-sub{color:rgba(248,250,252,.72)}' +
    '.sc-att-ph{padding:9px 10px;font-size:11px;color:#64748b;display:flex;align-items:center;gap:8px}' +
    '.sc-msg-row--vis .sc-att-ph{color:rgba(248,250,252,.85)}' +
    '.sc-lightbox{position:absolute;inset:0;z-index:50;display:none;align-items:center;justify-content:center;padding:14px}' +
    '.sc-lightbox.sc-open{display:flex}' +
    '.sc-lightbox-backdrop{position:absolute;inset:0;border:none;padding:0;margin:0;background:rgba(15,23,42,.78);backdrop-filter:blur(4px);cursor:pointer}' +
    '.sc-lightbox-inner{position:relative;z-index:1;max-width:100%;max-height:100%;display:flex;flex-direction:column;align-items:center;gap:10px}' +
    '.sc-lightbox-img{max-width:100%;max-height:min(70vh,460px);border-radius:12px;box-shadow:0 24px 56px rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.12)}' +
    '.sc-lightbox-x{position:absolute;top:-6px;right:-6px;width:34px;height:34px;border-radius:50%;border:none;background:rgba(255,255,255,.14);color:#fff;font-size:20px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(8px)}' +
    '.sc-lightbox-bar{display:flex;gap:8px}' +
    '.sc-err,.sc-banner{padding:9px 11px;border-radius:11px;font-size:12px;margin-bottom:8px;line-height:1.4}' +
    '.sc-err{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}' +
    '.sc-banner{background:#fffbeb;color:#92400e;border:1px solid #fde68a}' +
    '.sc-muted{text-align:center;font-size:12.5px;color:var(--sc-text-muted);padding:24px 16px;line-height:1.5}' +
    '.sc-empty{max-width:260px;margin:0 auto}' +
    '.sc-empty-title{display:block;font-size:13.5px;font-weight:650;color:var(--sc-text-dark);margin-bottom:5px}' +
    '.sc-greeting{margin:0 0 12px;padding:11px 13px;border-radius:18px;font-size:13px;line-height:1.48;color:#344054;' +
    'background:linear-gradient(180deg,rgba(255,255,255,.94),rgba(250,248,255,.86));' +
    'border:1px solid rgba(117,87,246,.14);box-shadow:0 14px 35px rgba(74,60,140,.1),inset 0 1px 0 rgba(255,255,255,.92);' +
    'backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}' +
    '.sc-greeting strong{font-weight:700;color:var(--sc-text-dark)}' +
    '.sc-messenger-fallback{margin:0 0 10px;padding:9px 11px 10px;border-radius:14px;background:rgba(255,255,255,.84);' +
    'border:1px solid rgba(117,87,246,.1);box-shadow:0 1px 0 rgba(255,255,255,.85) inset}' +
    '.sc-messenger-fallback--unavail{margin-top:8px;padding:0;background:transparent;border:none;box-shadow:none}' +
    '.sc-messenger-label{font-size:11px;color:#64748b;margin-bottom:6px;line-height:1.35}' +
    '.sc-messenger-btns{display:flex;flex-wrap:wrap;gap:6px}' +
    '.sc-messenger-btn{flex:1 1 0;min-width:88px;text-align:center;padding:7px 10px;border-radius:999px;font-size:11.5px;font-weight:600;' +
    'text-decoration:none;line-height:1.25;border:1px solid transparent;touch-action:manipulation;-webkit-tap-highlight-color:transparent}' +
    '.sc-messenger-btn--tg{background:rgba(232,226,255,.72);color:#5B42E0;border-color:rgba(117,87,246,.18)}' +
    '.sc-messenger-btn--wa{background:#ecfdf5;color:#047857;border-color:rgba(4,120,87,.14)}' +
    '.sc-messenger-btn:hover{text-decoration:none;filter:brightness(.97)}' +
    '.sc-unavail-links{margin-top:6px;font-size:11.5px;line-height:1.45}' +
    '.sc-unavail-links a{color:var(--sc-purple);text-decoration:none;font-weight:650}' +
    '.sc-unavail-links a:hover{text-decoration:underline}' +
    '@media (max-width:520px){.sc-wrap{right:max(12px,env(safe-area-inset-right));bottom:max(12px,env(safe-area-inset-bottom))}' +
    '.sc-panel{left:12px;right:auto;width:min(430px,calc(var(--sc-vw,100vw) - 24px));max-width:min(430px,calc(var(--sc-vw,100vw) - 24px));min-width:280px;' +
    'height:min(720px,calc(var(--sc-vh,100vh) - 96px));max-height:min(720px,calc(var(--sc-vh,100vh) - 96px));border-radius:20px;bottom:calc(84px + env(safe-area-inset-bottom) + var(--sc-kb-offset,0px))}' +
    '.sc-body,.sc-foot,.sc-form,.sc-composer-shell{width:100%;max-width:100%;min-width:0;box-sizing:border-box}' +
    '.sc-head{padding:10px 12px 9px}.sc-body{padding:12px 12px 10px}.sc-foot{padding:10px 12px max(10px,env(safe-area-inset-bottom))}' +
    '.sc-form input,.sc-form textarea,.sc-form .sc-btn,.sc-composer-field textarea{font-size:16px;line-height:1.35}' +
    '.sc-msg{max-width:78%}.sc-msg-row--vis .sc-msg{max-width:78%}' +
    '.sc-att-img-frame{height:95px}' +
    '.sc-composer-shell .sc-btn.sc-send{min-width:44px;height:38px;padding:0 12px;font-size:16px}}' +
    '</style>' +
    '<div class="sc-wrap">' +
    '<button type="button" class="sc-launcher" aria-label="' + t('launcherOpen') + '" title="' + t('launcherTitle') + '">' +
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
    '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/></svg>' +
    '</button></div>' +
    '<div class="sc-panel" role="dialog" aria-modal="false" aria-labelledby="sc-panel-title" aria-describedby="sc-panel-sub">' +
    '<div class="sc-head">' +
    '<div class="sc-head-brand">' +
    '<div class="sc-avatar" aria-hidden="true">' +
    '<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" fill="none"><path d="M12 2l7 4v6c0 5-3.5 9.2-7 10C8.5 21.2 5 17 5 12V6l7-4z"/></svg></div>' +
    '<div class="sc-head-text">' +
    '<div class="sc-title-row"><span id="sc-panel-title" class="sc-title">' + t('title') + '</span><span class="sc-online-dot" aria-hidden="true"></span></div>' +
    '<div id="sc-panel-sub" class="sc-sub">Our support team is here to help</div>' +
    '</div></div>' +
    '<button type="button" class="sc-close" aria-label="Close chat">' +
    '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg></button></div>' +
    '<div class="sc-body"></div>' +
    '<div class="sc-foot" style="display:none">' +
    '<input type="file" class="sc-file-input" tabindex="-1" aria-hidden="true" accept="image/jpeg,image/png,image/webp,application/pdf,video/mp4" />' +
    '<div class="sc-composer-shell">' +
    '<button type="button" class="sc-attach" aria-label="Attach file" title="JPEG, PNG, WebP (5 MB), PDF (10 MB), MP4 (25 MB)">' +
    '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
    '<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg></button>' +
    '<div class="sc-composer-field"><textarea rows="1" placeholder="Write a message…" aria-label="Write your message"></textarea></div>' +
    '<button type="button" class="sc-btn sc-send" aria-label="Send message">Send</button></div>' +
    '<div class="sc-foot-status-slot" aria-live="polite"></div></div>' +
    '<div class="sc-lightbox" aria-hidden="true">' +
    '<button type="button" class="sc-lightbox-backdrop" aria-label="Close image preview"></button>' +
    '<div class="sc-lightbox-inner">' +
    '<button type="button" class="sc-lightbox-x" aria-label="Close">\u00d7</button>' +
    '<img class="sc-lightbox-img" alt="" />' +
    '<div class="sc-lightbox-bar"><button type="button" class="sc-btn sc-btn-sec sc-btn-mini sc-lightbox-dl">Download</button></div>' +
    '</div></div>' +
    '</div>';

  var root = shadow.querySelector('.sc-wrap');
  var launcher = shadow.querySelector('.sc-launcher');
  var panel = shadow.querySelector('.sc-panel');
  var body = shadow.querySelector('.sc-body');
  var foot = shadow.querySelector('.sc-foot');
  var composerTa = foot.querySelector('textarea');
  var sendBtn = foot.querySelector('.sc-send');
  var fileInput = foot.querySelector('.sc-file-input');
  var attachBtn = foot.querySelector('.sc-attach');
  var footStatusSlotEl = foot.querySelector('.sc-foot-status-slot');
  var closeBtn = shadow.querySelector('.sc-close');
  var lightbox = shadow.querySelector('.sc-lightbox');
  var lightboxBackdrop = lightbox && lightbox.querySelector('.sc-lightbox-backdrop');
  var lightboxImgEl = lightbox && lightbox.querySelector('.sc-lightbox-img');
  var lightboxDl = lightbox && lightbox.querySelector('.sc-lightbox-dl');
  var lightboxCloseX = lightbox && lightbox.querySelector('.sc-lightbox-x');
  var lightboxDownloadUrl = null;
  var lightboxDownloadName = 'file';
  var previewObjectUrls = [];
  var viewportBound = false;

  function isMobileViewport() {
    var vv = window.visualViewport;
    var vw = vv && vv.width > 0 ? vv.width : window.innerWidth;
    return Math.round(vw || 0) <= 520;
  }

  function syncPanelVisibility() {
    if (!panel) return;
    panel.setAttribute('aria-hidden', panelOpen ? 'false' : 'true');
    if (isMobileViewport()) {
      panel.style.setProperty('display', panelOpen ? 'flex' : 'none', 'important');
    } else if (panelOpen) {
      panel.style.removeProperty('display');
    } else {
      panel.style.setProperty('display', 'none', 'important');
    }
  }

  function forceSafariPanelLayout() {
    if (!panel) return;
    syncPanelVisibility();
    if (!isMobileViewport()) return;
    var vv = window.visualViewport;
    var vw = Math.round((vv && vv.width > 0 ? vv.width : window.innerWidth) || 390);
    var vh = Math.round((vv && vv.height > 0 ? vv.height : window.innerHeight) || 844);
    var panelW = Math.min(430, Math.max(280, vw - 24));
    var panelH = Math.min(720, Math.max(320, vh - 96));
    panel.style.setProperty('position', 'fixed', 'important');
    panel.style.setProperty('box-sizing', 'border-box', 'important');
    panel.style.setProperty('min-width', panelW + 'px', 'important');
    panel.style.setProperty('width', panelW + 'px', 'important');
    panel.style.setProperty('max-width', panelW + 'px', 'important');
    panel.style.setProperty('height', panelH + 'px', 'important');
    panel.style.setProperty('max-height', panelH + 'px', 'important');
    panel.style.setProperty('left', '12px', 'important');
    panel.style.setProperty('right', 'auto', 'important');
    panel.style.setProperty('bottom', 'calc(84px + env(safe-area-inset-bottom))', 'important');
    var bodyShell = shadow.querySelector('.sc-body');
    if (bodyShell) {
      bodyShell.style.setProperty('width', '100%', 'important');
      bodyShell.style.setProperty('max-width', '100%', 'important');
      bodyShell.style.setProperty('min-width', '0', 'important');
    }
    if (body) {
      body.style.setProperty('width', '100%', 'important');
      body.style.setProperty('max-width', '100%', 'important');
      body.style.setProperty('min-width', '0', 'important');
    }
    var form = shadow.querySelector('.sc-form');
    if (form) {
      form.style.setProperty('width', '100%', 'important');
      form.style.setProperty('max-width', '100%', 'important');
      form.style.setProperty('min-width', '0', 'important');
    }
    var footEl = shadow.querySelector('.sc-foot');
    if (footEl) {
      footEl.style.setProperty('width', '100%', 'important');
      footEl.style.setProperty('max-width', '100%', 'important');
      footEl.style.setProperty('min-width', '0', 'important');
    }
    var composerShell = shadow.querySelector('.sc-composer-shell');
    if (composerShell) {
      composerShell.style.setProperty('width', '100%', 'important');
      composerShell.style.setProperty('max-width', '100%', 'important');
    }
    var inputs = shadow.querySelectorAll('.sc-form input,.sc-form textarea,.sc-composer-field textarea');
    for (var ii = 0; ii < inputs.length; ii++) {
      inputs[ii].style.setProperty('width', '100%', 'important');
      inputs[ii].style.setProperty('max-width', '100%', 'important');
      inputs[ii].style.setProperty('min-width', '0', 'important');
      inputs[ii].style.setProperty('font-size', '16px', 'important');
    }
  }

  function scheduleSafariPanelLayout() {
    if (!isMobileViewport()) {
      syncPanelVisibility();
      return;
    }
    forceSafariPanelLayout();
    setTimeout(forceSafariPanelLayout, 0);
    setTimeout(forceSafariPanelLayout, 250);
  }

  function updateScViewport() {
    var vh = window.innerHeight || 0;
    var vw = window.innerWidth || 0;
    var vv = window.visualViewport;
    if (vv && vv.height > 0) vh = vv.height;
    if (vv && vv.width > 0) vw = vv.width;
    var vhPx = Math.round(vh) + 'px';
    var vwPx = Math.round(vw) + 'px';
    host.style.setProperty('--sc-vh', vhPx);
    host.style.setProperty('--sc-vw', vwPx);
    if (panel) {
      panel.style.setProperty('--sc-vh', vhPx);
      panel.style.setProperty('--sc-vw', vwPx);
    }
    var kb = 0;
    if (vv && panelOpen) {
      kb = Math.max(0, window.innerHeight - vv.height - (vv.offsetTop || 0));
    }
    host.style.setProperty('--sc-kb-offset', Math.round(kb) + 'px');
    if (panelOpen) scheduleSafariPanelLayout();
  }

  function bindViewportListeners() {
    if (viewportBound) return;
    viewportBound = true;
    updateScViewport();
    window.addEventListener('resize', updateScViewport, { passive: true });
    window.addEventListener('orientationchange', updateScViewport, { passive: true });
    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', updateScViewport, { passive: true });
      window.visualViewport.addEventListener('scroll', updateScViewport, { passive: true });
    }
  }

  function scrollFocusedFieldIntoView(el) {
    if (!el || !body) return;
    try {
      el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
    } catch (e0) {
      try {
        el.scrollIntoView(false);
      } catch (e1) {}
    }
  }

  function isNearBottom(el, thresholdPx) {
    if (!el) return true;
    var th = thresholdPx == null ? SCROLL_NEAR_BOTTOM_PX : thresholdPx;
    return el.scrollHeight - el.scrollTop - el.clientHeight <= th;
  }

  function getScrollAnchor() {
    if (!body) return { nearBottom: true, distanceFromBottom: 0 };
    return {
      nearBottom: isNearBottom(body),
      distanceFromBottom: body.scrollHeight - body.scrollTop - body.clientHeight,
    };
  }

  function maybeAutoScroll(opts) {
    if (!body) return;
    opts = opts || {};
    if (opts.force || isNearBottom(body)) {
      body.scrollTop = body.scrollHeight;
    }
  }

  function applyScrollAfterRender(anchor, opts) {
    if (!body) return;
    opts = opts || {};
    if (opts.forceScroll) {
      body.scrollTop = body.scrollHeight;
      return;
    }
    if (!anchor) return;
    if (anchor.nearBottom) {
      body.scrollTop = body.scrollHeight;
    } else {
      body.scrollTop = Math.max(0, body.scrollHeight - body.clientHeight - anchor.distanceFromBottom);
    }
  }

  function getThreadSnapshot() {
    return {
      maxId: session.maxId,
      count: Object.keys(session.messagesById).length,
      echoes: session.attachmentEchoes ? session.attachmentEchoes.length : 0,
      status: session.status,
    };
  }

  function threadSnapshotChanged(a, b) {
    if (!a || !b) return true;
    return a.maxId !== b.maxId || a.count !== b.count || a.echoes !== b.echoes || a.status !== b.status;
  }

  function lightboxEscHandler(e) {
    if (e.key === 'Escape') closeLightbox();
  }

  function closeLightbox() {
    if (!lightbox) return;
    lightbox.classList.remove('sc-open');
    lightbox.setAttribute('aria-hidden', 'true');
    if (lightboxImgEl) lightboxImgEl.removeAttribute('src');
    lightboxDownloadUrl = null;
    try {
      document.removeEventListener('keydown', lightboxEscHandler);
    } catch (e1) {}
  }

  function openLightbox(blobUrl, downloadUrl, name) {
    if (!lightbox || !lightboxImgEl) return;
    closeLightbox();
    lightboxDownloadUrl = downloadUrl || null;
    lightboxDownloadName = name || 'file';
    lightboxImgEl.src = blobUrl;
    lightbox.classList.add('sc-open');
    lightbox.setAttribute('aria-hidden', 'false');
    document.addEventListener('keydown', lightboxEscHandler);
  }

  function collectActiveEchoPreviewUrls() {
    var keep = {};
    var echoes = session.attachmentEchoes || [];
    for (var ei = 0; ei < echoes.length; ei++) {
      var u = echoes[ei] && echoes[ei].localPreviewUrl;
      if (u) keep[u] = true;
    }
    return keep;
  }

  function revokeEchoPreviewUrl(echo) {
    if (!echo || !echo.localPreviewUrl) return;
    var u = echo.localPreviewUrl;
    try {
      URL.revokeObjectURL(u);
    } catch (eRev) {}
    previewObjectUrls = previewObjectUrls.filter(function (x) {
      return x !== u;
    });
    echo.localPreviewUrl = null;
  }

  /** Revoke hydrated message blobs; keep local echo preview URLs until echo is pruned/cleared. */
  function revokeOrphanPreviewUrls() {
    var keep = collectActiveEchoPreviewUrls();
    var next = [];
    var revoked = 0;
    for (var ri = 0; ri < previewObjectUrls.length; ri++) {
      var url = previewObjectUrls[ri];
      if (keep[url]) {
        next.push(url);
        continue;
      }
      try {
        URL.revokeObjectURL(url);
        revoked++;
      } catch (e2) {}
    }
    previewObjectUrls = next;
    scDebug('revokeOrphanPreviewUrls', {
      kept: next.length,
      revoked: revoked,
      activeEchoUrls: Object.keys(keep).map(scDebugBlobRef),
    });
  }

  function revokeAllPreviewUrls() {
    for (var ri = 0; ri < previewObjectUrls.length; ri++) {
      try {
        URL.revokeObjectURL(previewObjectUrls[ri]);
      } catch (e2) {}
    }
    previewObjectUrls = [];
    var echoes = session.attachmentEchoes || [];
    for (var ei = 0; ei < echoes.length; ei++) {
      if (echoes[ei]) echoes[ei].localPreviewUrl = null;
    }
  }

  var uploading = false;
  var sendInFlight = false;
  var uploadSeq = 0;
  var sendSeq = 0;
  var uploadSafetyTimer = null;
  var panelOpen = false;
  var pollTimer = null;
  var stopPoll = false;
  /** Monotonic: ignore stale GET /messages responses that finish after a newer request (prevents closed→reopen races). */
  var messagesFetchGeneration = 0;

  var session = {
    uuid: null,
    token: null,
    // status: only from API (create / poll / send) — never assume 'open'
    status: null,
    messagesById: {},
    maxId: 0,
    loading: false,
    /** P4.1: server flag SUPPORT_CHAT_MESSAGE_STATES_ENABLED — cosmetic footer only. */
    messageStatesEnabled: false,
    footerStatusHideTimer: null,
    /** Local-only echo rows after attachment upload (server has no SupportMessage for files yet). */
    attachmentEchoes: [],
  };

  function loadSession() {
    try {
      var u = normalizeStoredSessionValue(localStorage.getItem(STORAGE_UUID));
      var t = normalizeStoredSessionValue(localStorage.getItem(STORAGE_TOKEN));
      if (u !== session.uuid || t !== session.token) {
        session.status = null;
        session.messageStatesEnabled = false;
        syncFootMessageStatesClass();
      }
      session.uuid = u;
      session.token = t;
    } catch (e) {
      session.uuid = null;
      session.token = null;
      session.status = null;
    }
  }

  function syncFootMessageStatesClass() {
    if (foot) foot.classList.toggle('sc-foot-ms', !!session.messageStatesEnabled);
  }

  /** Apply API conversation status; avoids stale closed UI when status was omitted or a cached response had no JSON body. */
  function applyConversationStatusFromPayload(data) {
    if (data && Object.prototype.hasOwnProperty.call(data, 'message_states_enabled')) {
      var ms = data.message_states_enabled;
      session.messageStatesEnabled =
        ms === true || ms === 1 || ms === '1' || String(ms).toLowerCase() === 'true';
    }
    if (!data || !Object.prototype.hasOwnProperty.call(data, 'status')) {
      syncFootMessageStatesClass();
      return;
    }
    if (data.status == null) {
      session.status = null;
      syncFootMessageStatesClass();
      return;
    }
    var st = String(data.status).trim().toLowerCase();
    session.status = st === '' ? null : st;
    syncFootMessageStatesClass();
  }

  function saveSession(uuid, token) {
    session.uuid = uuid;
    session.token = token;
    try {
      localStorage.setItem(STORAGE_UUID, uuid);
      localStorage.setItem(STORAGE_TOKEN, token);
    } catch (e) {}
  }

  function clearSession() {
    session.uuid = null;
    session.token = null;
    session.messagesById = {};
    session.maxId = 0;
    session.status = null;
    revokeAllPreviewUrls();
    session.attachmentEchoes = [];
    session.messageStatesEnabled = false;
    if (session.footerStatusHideTimer) {
      clearTimeout(session.footerStatusHideTimer);
      session.footerStatusHideTimer = null;
    }
    hideFooterStatus();
    syncFootMessageStatesClass();
    releaseComposerControls();
    try {
      localStorage.removeItem(STORAGE_UUID);
      localStorage.removeItem(STORAGE_TOKEN);
    } catch (e) {}
  }

  function hideFooterStatus() {
    if (!footStatusSlotEl) return;
    footStatusSlotEl.textContent = '';
    footStatusSlotEl.classList.remove('sc-visible', 'sc-warn', 'sc-ok');
  }

  /**
   * Single footer status line (send + upload); only when session.messageStatesEnabled.
   * opts: { warn, ok, persist: true (Failed), autoClear: false (Sending/Uploading busy), ttlMs }
   */
  function setFooterStatus(text, opts) {
    if (!footStatusSlotEl || !session.messageStatesEnabled) return;
    opts = opts || {};
    if (session.footerStatusHideTimer) {
      clearTimeout(session.footerStatusHideTimer);
      session.footerStatusHideTimer = null;
    }
    footStatusSlotEl.classList.remove('sc-warn', 'sc-ok');
    var t = (text || '').toString();
    footStatusSlotEl.textContent = t;
    if (!t) {
      footStatusSlotEl.classList.remove('sc-visible', 'sc-warn', 'sc-ok');
      return;
    }
    footStatusSlotEl.classList.add('sc-visible');
    if (opts.warn) footStatusSlotEl.classList.add('sc-warn');
    else if (opts.ok) footStatusSlotEl.classList.add('sc-ok');
    if (opts.persist === true || opts.autoClear === false) return;
    var ttl = opts.ttlMs != null ? Number(opts.ttlMs) : 2600;
    if (isNaN(ttl) || ttl <= 0) return;
    session.footerStatusHideTimer = setTimeout(function () {
      hideFooterStatus();
      session.footerStatusHideTimer = null;
    }, ttl);
  }

  function showError(html) {
    var olds = body.querySelectorAll('.sc-err');
    for (var i = 0; i < olds.length; i++) {
      if (olds[i].parentNode) olds[i].parentNode.removeChild(olds[i]);
    }
    var n = document.createElement('div');
    n.className = 'sc-err';
    n.innerHTML = html;
    body.appendChild(n);
  }

  function visitorTimezone() {
    try {
      var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
      if (tz && typeof tz === 'string') return tz.slice(0, 64);
    } catch (e) {}
    return null;
  }

  function ensureGreeting() {
    if (!body || body.querySelector('.sc-greeting')) return;
    var g = document.createElement('div');
    g.className = 'sc-greeting';
    g.setAttribute('role', 'status');
    g.innerHTML = '<strong>Hello \uD83D\uDC4B</strong> Our support team is here to help.';
    if (body.firstChild) body.insertBefore(g, body.firstChild);
    else body.appendChild(g);
  }

  function messengerFallbackButtonsHtml() {
    return (
      '<div class="sc-messenger-btns">' +
      '<a class="sc-messenger-btn sc-messenger-btn--tg" href="' +
      esc(FALLBACK_TELEGRAM_URL) +
      '" target="_blank" rel="noopener noreferrer">Telegram</a>' +
      '<a class="sc-messenger-btn sc-messenger-btn--wa" href="' +
      esc(FALLBACK_WHATSAPP_URL) +
      '" target="_blank" rel="noopener noreferrer">WhatsApp</a>' +
      '</div>'
    );
  }

  function ensureMessengerFallback() {
    if (!body || body.querySelector('.sc-messenger-fallback:not(.sc-messenger-fallback--unavail)')) return;
    var el = document.createElement('div');
    el.className = 'sc-messenger-fallback';
    el.setAttribute('role', 'navigation');
    el.setAttribute('aria-label', 'Messenger contact options');
    el.innerHTML =
      '<div class="sc-messenger-label">Prefer messenger? Contact us directly:</div>' + messengerFallbackButtonsHtml();
    var greeting = body.querySelector('.sc-greeting');
    if (greeting) {
      if (greeting.nextSibling) body.insertBefore(el, greeting.nextSibling);
      else greeting.parentNode.appendChild(el);
    } else if (body.firstChild) {
      body.insertBefore(el, body.firstChild);
    } else {
      body.appendChild(el);
    }
  }

  function ensureWelcomeChrome() {
    ensureGreeting();
    ensureMessengerFallback();
  }

  function chatUnavailableFallbackHtml() {
    return (
      'Chat is temporarily unavailable. You can also contact us via Telegram or WhatsApp.' +
      '<div class="sc-messenger-fallback sc-messenger-fallback--unavail">' +
      messengerFallbackButtonsHtml() +
      '</div>'
    );
  }

  function showChatUnavailable() {
    clearBody();
    ensureGreeting();
    showError(chatUnavailableFallbackHtml());
  }

  function clearBody() {
    closeLightbox();
    revokeOrphanPreviewUrls();
    while (body.firstChild) body.removeChild(body.firstChild);
  }

  function formatTime(iso) {
    if (!iso) return '';
    try {
      var d = new Date(iso);
      return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
    } catch (e) {
      return '';
    }
  }

  function formatBytes(n) {
    if (n == null || n === '' || isNaN(Number(n)) || Number(n) < 0) return '';
    var v = Number(n);
    if (v < 1024) return v + ' B';
    if (v < 1048576) return (v / 1024).toFixed(v >= 10240 ? 0 : 1) + ' KB';
    return (v / 1048576).toFixed(1) + ' MB';
  }

  function buildMessageAttachmentsHtml(m) {
    if (!m.attachments || !m.attachments.length) return '';
    var h = '<div class="sc-att-wrap">';
    for (var j = 0; j < m.attachments.length; j++) {
      var a = m.attachments[j];
      var fname = truncateFilename((a.original_name || 'file').toString(), 48);
      var rel = (a.download_url || '').toString();
      if (!rel) continue;
      var full = apiUrl(rel);
      var mime = (a.mime_type && String(a.mime_type)) || '';
      var isImg = mime.indexOf('image/') === 0;
      var isPdf = mime === 'application/pdf' || /\.pdf$/i.test(fname);
      var isVid = mime.indexOf('video/') === 0;
      var sz = formatBytes(a.size_bytes);
      if (isImg) {
        h +=
          '<div class="sc-att sc-att--img" data-att-kind="image">' +
          '<div class="sc-att-img-frame">' +
          '<div class="sc-att-skel" aria-hidden="true"></div>' +
          '<button type="button" class="sc-att-img-btn" data-preview="1" data-url="' +
          esc(full) +
          '" data-name="' +
          esc(fname) +
          '" aria-label="View image">' +
          '<img class="sc-att-img-el" alt="" decoding="async" /></button></div>' +
          '<div class="sc-att-bar"><button type="button" class="sc-btn sc-btn-sec sc-btn-mini sc-att-dl" data-url="' +
          esc(full) +
          '" data-name="' +
          esc(fname) +
          '">Download</button></div></div>';
      } else if (isPdf) {
        h +=
          '<div class="sc-doc-card" data-att-kind="pdf">' +
          '<div class="sc-doc-ic" aria-hidden="true">PDF</div>' +
          '<div class="sc-doc-meta"><div class="sc-doc-name">' +
          esc(fname) +
          '</div><div class="sc-doc-sub">' +
          esc(sz || 'Document') +
          '</div></div>' +
          '<button type="button" class="sc-btn sc-btn-sec sc-btn-mini sc-att-dl" data-url="' +
          esc(full) +
          '" data-name="' +
          esc(fname) +
          '">Download</button></div>';
      } else if (isVid) {
        h +=
          '<div class="sc-att-ph" data-att-kind="video"><span>Video preview unavailable</span>' +
          '<button type="button" class="sc-btn sc-btn-sec sc-btn-mini sc-att-dl" data-url="' +
          esc(full) +
          '" data-name="' +
          esc(fname) +
          '">Download</button></div>';
      } else {
        h +=
          '<div class="sc-doc-card" data-att-kind="file">' +
          '<div class="sc-doc-ic" aria-hidden="true">FILE</div>' +
          '<div class="sc-doc-meta"><div class="sc-doc-name">' +
          esc(fname) +
          '</div><div class="sc-doc-sub">' +
          esc(mime || sz || 'Attachment') +
          '</div></div>' +
          '<button type="button" class="sc-btn sc-btn-sec sc-btn-mini sc-att-dl" data-url="' +
          esc(full) +
          '" data-name="' +
          esc(fname) +
          '">Download</button></div>';
      }
    }
    h += '</div>';
    return h;
  }

  function fetchAttachmentBlob(url) {
    if (!session.token) return Promise.resolve(null);
    return fetch(url, {
      method: 'GET',
      headers: { Accept: 'application/octet-stream', Authorization: 'Bearer ' + session.token },
      credentials: 'omit',
      cache: 'no-store',
    }).then(function (r) {
      if (!r.ok) return null;
      return r.blob();
    });
  }

  function hydrateImagePreviews(rootEl) {
    if (!rootEl || !session.token) return;
    var imgs = rootEl.querySelectorAll('.sc-att-img-btn[data-preview="1"]');
    for (var ii = 0; ii < imgs.length; ii++) {
      (function (wrap) {
        var url = wrap.getAttribute('data-url');
        var name = wrap.getAttribute('data-name') || 'image';
        var img = wrap.querySelector('.sc-att-img-el');
        var skel = wrap.parentNode && wrap.parentNode.querySelector('.sc-att-skel');
        if (!url || !img) return;
        fetchAttachmentBlob(url).then(function (blob) {
          if (!blob || !img.parentNode) return;
          var u = URL.createObjectURL(blob);
          previewObjectUrls.push(u);
          img.onload = function () {
            img.classList.add('sc-loaded');
            if (skel) skel.style.display = 'none';
            maybeAutoScroll();
          };
          img.onerror = function () {
            img.style.display = 'none';
            if (skel) {
              skel.className = 'sc-att-broken';
              skel.textContent = 'Preview unavailable';
              skel.style.display = 'block';
            }
          };
          img.src = u;
        });
      })(imgs[ii]);
    }
  }

  function downloadAttachmentWithAuth(url, name) {
    if (!session.token) {
      showError('Your session expired. Please refresh the page.');
      return;
    }
    fetchAttachmentBlob(url).then(function (blob) {
      if (!blob) {
        showError('Download failed.');
        return;
      }
      var u = URL.createObjectURL(blob);
      var a = document.createElement('a');
      a.href = u;
      a.download = name || 'file';
      a.rel = 'noopener';
      a.click();
      setTimeout(function () {
        try {
          URL.revokeObjectURL(u);
        } catch (e3) {}
      }, 4000);
    }).catch(function () {
      showError('Network error.');
    });
  }

  function renderMessages(opts) {
    opts = opts || {};
    var anchor = getScrollAnchor();
    scDebug('renderMessages start', {
      echoCount: session.attachmentEchoes ? session.attachmentEchoes.length : 0,
      msgCount: Object.keys(session.messagesById).length,
      forceScroll: !!(opts && opts.forceScroll),
    });
    clearBody();
    ensureWelcomeChrome();
    var ids = Object.keys(session.messagesById)
      .map(function (x) {
        return parseInt(x, 10);
      })
      .filter(function (x) {
        return !isNaN(x);
      })
      .sort(function (a, b) {
        return a - b;
      });
    var hasEchoes = session.attachmentEchoes && session.attachmentEchoes.length > 0;
    if (ids.length === 0 && !hasEchoes) {
      var empty = document.createElement('div');
      empty.className = 'sc-muted sc-empty';
      empty.innerHTML = '<span class="sc-empty-title">Start a conversation</span>Our team typically replies within a few minutes.';
      body.appendChild(empty);
    } else if (ids.length > 0) {
      for (var i = 0; i < ids.length; i++) {
        var m = session.messagesById[ids[i]];
        var st = m.sender_type || 'visitor';
        var prev = i > 0 ? session.messagesById[ids[i - 1]] : null;
        var prevSt = prev ? prev.sender_type || 'visitor' : null;
        var groupSame = prevSt === st;
        var who = st === 'visitor' ? t('you') : st === 'operator' ? t('operator') : t('system');
        var row = document.createElement('div');
        row.className =
          'sc-msg-row ' +
          (st === 'visitor' ? 'sc-msg-row--vis' : st === 'operator' ? 'sc-msg-row--op' : 'sc-msg-row--sys') +
          (groupSame ? ' sc-msg-row--group' : '');
        var inner = document.createElement('div');
        inner.className = 'sc-msg sc-msg--' + (st === 'visitor' ? 'vis' : st === 'operator' ? 'op' : 'sys');
        var bubble = document.createElement('div');
        bubble.className = 'sc-msg-bubble';
        var bodyTxt = (m.body || '').toString();
        if (bodyTxt !== '') {
          var textEl = document.createElement('div');
          textEl.className = 'sc-msg-text';
          textEl.textContent = bodyTxt;
          bubble.appendChild(textEl);
        }
        var attHtml = buildMessageAttachmentsHtml(m);
        if (attHtml) {
          var attWrap = document.createElement('div');
          attWrap.innerHTML = attHtml;
          while (attWrap.firstChild) bubble.appendChild(attWrap.firstChild);
        }
        inner.appendChild(bubble);
        var footMeta = document.createElement('div');
        footMeta.className = 'sc-msg-foot';
        footMeta.innerHTML =
          '<span class="sc-msg-badge">' +
          esc(who) +
          '</span><span class="sc-msg-time">' +
          esc(formatTime(m.created_at)) +
          '</span>';
        inner.appendChild(footMeta);
        row.appendChild(inner);
        body.appendChild(row);
        hydrateImagePreviews(bubble);
      }
    }
    if (session.attachmentEchoes && session.attachmentEchoes.length) {
      scDebug('renderMessages echoes', {
        count: session.attachmentEchoes.length,
        echoes: session.attachmentEchoes.map(scDebugEchoSummary),
      });
      for (var ei = 0; ei < session.attachmentEchoes.length; ei++) {
        appendAttachmentEchoRow(session.attachmentEchoes[ei]);
      }
    }
    if (session.status === 'closed') {
      var b = document.createElement('div');
      b.className = 'sc-banner';
      b.textContent = 'This conversation is closed. Send a message below to reopen.';
      body.appendChild(b);
    }
    applyScrollAfterRender(anchor, opts);
    ensureComposerReady();
  }

  function renderNewForm() {
    bumpComposerEpoch();
    safeResetComposerState();
    session.attachmentEchoes = [];
    session.messageStatesEnabled = false;
    if (session.footerStatusHideTimer) {
      clearTimeout(session.footerStatusHideTimer);
      session.footerStatusHideTimer = null;
    }
    hideFooterStatus();
    syncFootMessageStatesClass();
    clearBody();
    ensureWelcomeChrome();
    foot.style.display = 'none';
    var form = document.createElement('div');
    form.className = 'sc-form';
    form.innerHTML =
      '<div class="sc-row"><label for="sc-fn">Name</label><input id="sc-fn" type="text" autocomplete="name" maxlength="191" /></div>' +
      '<div class="sc-row"><label for="sc-fe">Email</label><input id="sc-fe" type="email" autocomplete="email" maxlength="255" /></div>' +
      '<div class="sc-row"><label for="sc-fm">Message</label><textarea id="sc-fm"></textarea></div>' +
      '<button type="button" class="sc-btn" id="sc-fsubmit">Start chat</button>';
    body.appendChild(form);
    updateScViewport();
    scheduleSafariPanelLayout();
    var btn = form.querySelector('#sc-fsubmit');
    btn.addEventListener('click', function () {
      var name = form.querySelector('#sc-fn').value.trim();
      var email = form.querySelector('#sc-fe').value.trim();
      var message = form.querySelector('#sc-fm').value.trim();
      if (!name || !email || !message) {
        showError('Please fill in name, email, and message.');
        return;
      }
      btn.disabled = true;
      createConversation(name, email, message, function (err) {
        btn.disabled = false;
        if (err) return;
      });
    });
  }

  function normalizeUploadedAttachmentResponse(json) {
    if (!json || typeof json !== 'object') return null;
    var att = json.attachment || json.data || json;
    if (!att || typeof att !== 'object') return null;
    return att;
  }

  function attachmentRenderableOnServerMessage(attachmentId) {
    if (attachmentId == null) return false;
    var keys = Object.keys(session.messagesById);
    for (var ki = 0; ki < keys.length; ki++) {
      var msg = session.messagesById[keys[ki]];
      if (!msg || !msg.attachments || !msg.attachments.length) continue;
      for (var ai = 0; ai < msg.attachments.length; ai++) {
        var a = msg.attachments[ai];
        if (!a || a.id == null || String(a.id) !== String(attachmentId)) continue;
        if ((a.download_url || '').toString() !== '') return true;
      }
    }
    return false;
  }

  function shouldKeepAttachmentEcho(echo) {
    if (!echo) return false;
    var ageMs = echo.uploadedAt ? Date.now() - echo.uploadedAt : null;
    if (echo.uploadedAt && ageMs < ECHO_PREVIEW_GRACE_MS) return true;
    if (echo.attachment_id == null) return true;
    return !attachmentRenderableOnServerMessage(echo.attachment_id);
  }

  function pruneAttachmentEchoes() {
    if (!session.attachmentEchoes || !session.attachmentEchoes.length) return;
    var next = [];
    for (var ei = 0; ei < session.attachmentEchoes.length; ei++) {
      var echo = session.attachmentEchoes[ei];
      var keep = shouldKeepAttachmentEcho(echo);
      var onServer = echo.attachment_id != null && attachmentRenderableOnServerMessage(echo.attachment_id);
      scDebug('pruneAttachmentEcho', {
        echo: scDebugEchoSummary(echo),
        onServerRenderable: onServer,
        keep: keep,
        prune: !keep,
      });
      if (keep) {
        next.push(echo);
      } else {
        revokeEchoPreviewUrl(echo);
      }
    }
    session.attachmentEchoes = next;
  }

  function buildEchoFromUpload(file, att) {
    var norm = att || {};
    var mime = (norm.mime_type || norm.mime || (file && file.type) || '').toString().toLowerCase();
    var name = truncateFilename(norm.original_name || norm.name || (file && file.name) || 'file');
    var attId = norm.id != null ? norm.id : null;
    var rel = null;
    if (attId != null && session.uuid) {
      rel =
        '/client-api/v1/support/conversations/' +
        encodeURIComponent(session.uuid) +
        '/attachments/' +
        encodeURIComponent(String(attId));
    }
    var echo = {
      local_id: 'local-' + Date.now(),
      attachment_id: attId,
      mime_type: mime,
      original_name: name,
      size_bytes: norm.size_bytes,
      download_url: rel,
      created_at: norm.created_at || new Date().toISOString(),
      uploadedAt: Date.now(),
      localPreviewUrl: null,
    };
    if (mime.indexOf('image/') === 0 && file) {
      try {
        var localU = URL.createObjectURL(file);
        echo.localPreviewUrl = localU;
        previewObjectUrls.push(localU);
      } catch (eLocal) {
        scDebug('buildEchoFromUpload blob failed', { mime: mime, name: name });
      }
    }
    scDebug('buildEchoFromUpload', {
      isImage: mime.indexOf('image/') === 0,
      echo: scDebugEchoSummary(echo),
    });
    return echo;
  }

  function applyLocalEchoImagePreview(bubbleEl, echo) {
    if (!bubbleEl || !echo || !echo.localPreviewUrl) return false;
    var imgEl = bubbleEl.querySelector('.sc-att-img-el');
    var skelEl = bubbleEl.querySelector('.sc-att-skel');
    if (!imgEl) {
      scDebug('applyLocalEchoImagePreview no img');
      return false;
    }
    imgEl.setAttribute('data-local-preview', '1');
    function markLoaded() {
      imgEl.classList.add('sc-loaded');
      if (skelEl) skelEl.style.display = 'none';
      maybeAutoScroll();
    }
    function markBroken() {
      imgEl.classList.remove('sc-loaded');
      if (skelEl) {
        skelEl.style.display = 'block';
        skelEl.className = 'sc-att-broken';
        skelEl.textContent = 'Preview unavailable';
      }
    }
    if (skelEl) skelEl.style.display = 'none';
    imgEl.classList.add('sc-loaded');
    imgEl.onload = markLoaded;
    imgEl.onerror = function () {
      scDebug('applyLocalEchoImagePreview error', { echo: scDebugEchoSummary(echo) });
      markBroken();
      if (echo.download_url && session.token) hydrateImagePreviews(bubbleEl);
    };
    imgEl.src = echo.localPreviewUrl;
    scDebug('applyLocalEchoImagePreview', {
      echo: scDebugEchoSummary(echo),
      src: scDebugBlobRef(imgEl.src),
      complete: imgEl.complete,
      naturalWidth: imgEl.naturalWidth,
      hasLoadedClass: imgEl.classList.contains('sc-loaded'),
    });
    if (imgEl.complete && imgEl.naturalWidth > 0) markLoaded();
    return true;
  }

  function appendAttachmentEchoRow(echo) {
    if (!echo) return;
    var er = document.createElement('div');
    er.className = 'sc-msg-row sc-msg-row--vis';
    var ev = document.createElement('div');
    ev.className = 'sc-msg sc-msg--vis';
    var eb = document.createElement('div');
    eb.className = 'sc-msg-bubble';
    var attHtml = '';
    if (echo.download_url && echo.mime_type) {
      attHtml = buildMessageAttachmentsHtml({
        attachments: [
          {
            original_name: echo.original_name,
            mime_type: echo.mime_type,
            size_bytes: echo.size_bytes,
            download_url: echo.download_url,
          },
        ],
      });
    }
    if (attHtml && attHtml.indexOf('sc-att') !== -1) {
      var wrap = document.createElement('div');
      wrap.innerHTML = attHtml;
      while (wrap.firstChild) eb.appendChild(wrap.firstChild);
      scDebug('appendAttachmentEchoRow imageCard', {
        echo: scDebugEchoSummary(echo),
        branch: echo.localPreviewUrl ? 'localPreview' : 'hydrate',
      });
      if (echo.localPreviewUrl) {
        applyLocalEchoImagePreview(eb, echo);
      } else {
        hydrateImagePreviews(eb);
      }
    } else if (echo.localPreviewUrl && (echo.mime_type || '').indexOf('image/') === 0) {
      var localWrap = document.createElement('div');
      localWrap.className = 'sc-att-wrap';
      localWrap.innerHTML =
        '<div class="sc-att sc-att--img" data-att-kind="image">' +
        '<div class="sc-att-img-frame">' +
        '<div class="sc-att-skel" aria-hidden="true"></div>' +
        '<button type="button" class="sc-att-img-btn" data-preview="1" aria-label="View image">' +
        '<img class="sc-att-img-el" alt="" decoding="async" /></button></div></div>';
      while (localWrap.firstChild) eb.appendChild(localWrap.firstChild);
      applyLocalEchoImagePreview(eb, echo);
    } else {
      var et = document.createElement('div');
      et.className = 'sc-msg-text';
      et.textContent = echo.body || '📎 ' + (echo.original_name || 'Attachment');
      eb.appendChild(et);
    }
    ev.appendChild(eb);
    er.appendChild(ev);
    body.appendChild(er);
  }

  function mergeMessages(arr) {
    if (!arr || !arr.length) return;
    for (var i = 0; i < arr.length; i++) {
      var m = arr[i];
      if (m && m.id != null) {
        session.messagesById[m.id] = m;
        if (m.id > session.maxId) session.maxId = m.id;
      }
    }
    pruneAttachmentEchoes();
  }

  function authHeaders() {
    var h = { 'Content-Type': 'application/json', Accept: 'application/json' };
    if (session.token) {
      h['Authorization'] = 'Bearer ' + session.token;
    }
    return h;
  }

  function truncateFilename(name, maxLen) {
    var ml = maxLen || 48;
    var n = (name || '').toString();
    if (n === '') return 'file';
    var base = n.replace(/^.*[\\/]/, '');
    if (base.length <= ml) return base;
    return base.slice(0, ml - 1) + '\u2026';
  }

  /** Client-side guard only; server remains authoritative. */
  function validateAttachmentClient(file) {
    if (!file || !file.size) {
      return 'Please choose a non-empty file.';
    }
    var t = (file.type || '').toLowerCase().trim();
    var limits = {
      'image/jpeg': 5 * 1024 * 1024,
      'image/png': 5 * 1024 * 1024,
      'image/webp': 5 * 1024 * 1024,
      'application/pdf': 10 * 1024 * 1024,
      'video/mp4': 25 * 1024 * 1024,
    };
    if (!Object.prototype.hasOwnProperty.call(limits, t)) {
      return 'Use JPEG, PNG, WebP, PDF, or MP4 only.';
    }
    var maxB = limits[t];
    if (file.size > maxB) {
      if (t.indexOf('image/') === 0) return 'Image is too large (max 5 MB).';
      if (t === 'application/pdf') return 'PDF is too large (max 10 MB).';
      return 'Video is too large (max 25 MB).';
    }
    return null;
  }

  function setUploadUiState(mode, text) {
    if (mode === 'busy') {
      if (session.messageStatesEnabled) {
        setFooterStatus(text || 'Uploading…', { autoClear: false });
      }
      return;
    }
    if (mode === 'error' && text && !session.messageStatesEnabled) {
      showError(esc(text));
    }
  }

  function refreshComposerRefs() {
    if (!foot) return false;
    composerTa = foot.querySelector('textarea');
    sendBtn = foot.querySelector('.sc-send');
    attachBtn = foot.querySelector('.sc-attach');
    fileInput = foot.querySelector('.sc-file-input');
    footStatusSlotEl = foot.querySelector('.sc-foot-status-slot');
    return !!(composerTa && sendBtn && attachBtn);
  }

  /** Invalidate in-flight upload/send handlers (panel close/reopen). */
  function bumpComposerEpoch() {
    uploadSeq++;
    sendSeq++;
    if (uploadSafetyTimer) {
      clearTimeout(uploadSafetyTimer);
      uploadSafetyTimer = null;
    }
  }

  /** Force-unlock composer; footer status is cosmetic only. */
  function safeResetComposerState() {
    uploading = false;
    sendInFlight = false;
    if (uploadSafetyTimer) {
      clearTimeout(uploadSafetyTimer);
      uploadSafetyTimer = null;
    }
    if (session.footerStatusHideTimer) {
      clearTimeout(session.footerStatusHideTimer);
      session.footerStatusHideTimer = null;
    }
    hideFooterStatus();
    refreshComposerRefs();
    if (composerTa) {
      composerTa.disabled = false;
      composerTa.readOnly = false;
    }
    if (sendBtn) sendBtn.disabled = false;
    if (attachBtn) attachBtn.disabled = false;
  }

  /** Only uploading locks textarea/attach; sendInFlight locks Send only. */
  function syncComposerDisabled() {
    refreshComposerRefs();
    if (!composerTa || !sendBtn || !attachBtn) return;
    attachBtn.disabled = !!uploading;
    composerTa.disabled = !!uploading;
    sendBtn.disabled = !!uploading || !!sendInFlight;
  }

  /** Heal stray disabled attributes without breaking active upload/send epochs. */
  function ensureComposerReady() {
    refreshComposerRefs();
    if (!composerTa || !sendBtn || !attachBtn) return;
    syncComposerDisabled();
    if (!uploading) {
      if (composerTa.disabled) composerTa.disabled = false;
      if (attachBtn.disabled) attachBtn.disabled = false;
    }
    if (!uploading && !sendInFlight && sendBtn.disabled) sendBtn.disabled = false;
  }

  function releaseComposerControls() {
    safeResetComposerState();
  }

  function finishSendAttempt(seq) {
    if (seq != null && seq !== sendSeq) return;
    sendInFlight = false;
    syncComposerDisabled();
    ensureComposerReady();
  }

  /**
   * Terminal upload handler — always unlocks composer when seq matches.
   * result: 'success' | 'failed' | 'cancelled' | 'stale'
   */
  function finishUploadAttempt(seq, result, detail) {
    if (seq != null && seq !== uploadSeq) return;
    if (uploadSafetyTimer) {
      clearTimeout(uploadSafetyTimer);
      uploadSafetyTimer = null;
    }
    uploading = false;
    syncComposerDisabled();
    ensureComposerReady();
    if (result === 'stale') return;
    if (session.messageStatesEnabled) {
      if (result === 'success') {
        setFooterStatus('Uploaded', { ok: true, ttlMs: 2600 });
      } else if (result === 'failed') {
        setFooterStatus(detail || 'Failed', { warn: true, persist: true });
      } else if (result === 'cancelled') {
        hideFooterStatus();
      } else {
        hideFooterStatus();
      }
    } else if (result === 'failed' && detail) {
      showError(esc(detail));
    }
  }

  function uploadAttachment(file) {
    if (!session.uuid || !session.token) {
      showError(esc('Please send a message first, then attach files.'));
      return;
    }
    var err = validateAttachmentClient(file);
    if (err) {
      showError(esc(err));
      return;
    }
    var seq = ++uploadSeq;
    uploading = true;
    scDebug('uploadAttachment start', {
      seq: seq,
      fileName: file && file.name,
      fileType: file && file.type,
      fileSize: file && file.size,
      uploading: true,
    });
    syncComposerDisabled();
    setUploadUiState('busy', 'Uploading…');
    uploadSafetyTimer = setTimeout(function () {
      if (seq !== uploadSeq) return;
      finishUploadAttempt(seq, 'failed', 'Upload timed out. Please try again.');
    }, 120000);
    var uploadOutcome = null;
    var uploadOutcomeDetail = '';
    var fd = new FormData();
    fd.append('file', file);
    fetch(
      apiUrl('/client-api/v1/support/conversations/' + encodeURIComponent(session.uuid) + '/attachments'),
      {
        method: 'POST',
        headers: { Accept: 'application/json', Authorization: 'Bearer ' + session.token },
        body: fd,
        credentials: 'omit',
        cache: 'no-store',
      }
    )
      .then(parseFetchResponse)
      .then(function (res) {
        if (seq !== uploadSeq) {
          uploadOutcome = 'stale';
          return;
        }
        if (res.status === 401) {
          uploadOutcome = 'cancelled';
          clearSession();
          stopPollingInternal();
          foot.style.display = 'none';
          clearBody();
          renderNewForm();
          showError('Your session expired. Please start a new conversation.');
          return;
        }
        if (!res.ok) {
          uploadOutcome = 'failed';
          uploadOutcomeDetail = 'Upload failed.';
          if (res.json && res.json.message) uploadOutcomeDetail = String(res.json.message);
          return;
        }
        scDebug('uploadAttachment response', {
          httpStatus: res.status,
          jsonKeys: res.json ? Object.keys(res.json) : [],
        });
        var att = normalizeUploadedAttachmentResponse(res.json);
        if (!att) {
          uploadOutcome = 'failed';
          uploadOutcomeDetail = 'Upload failed.';
          return;
        }
        scDebug('uploadAttachment normalized', {
          attachmentId: att.id,
          mime_type: att.mime_type || att.mime,
          original_name: att.original_name || att.name,
        });
        var echoCountBefore = session.attachmentEchoes.length;
        var echoRow = buildEchoFromUpload(file, att);
        var serverMsg = res.json.message;
        if (serverMsg && serverMsg.id != null) {
          mergeMessages([serverMsg]);
          scDebug('uploadAttachment merged server message', {
            messageId: serverMsg.id,
            attachmentCount: serverMsg.attachments ? serverMsg.attachments.length : 0,
          });
        } else {
          session.attachmentEchoes.push(echoRow);
          scDebug('uploadAttachment echo pushed (no server message)', {
            echoCountBefore: echoCountBefore,
            echoCountAfter: session.attachmentEchoes.length,
            echo: scDebugEchoSummary(echoRow),
          });
        }
        uploadOutcome = 'success';
        renderMessages({ forceScroll: true });
        fetchMessages(false, function () {});
      })
      .catch(function () {
        if (seq !== uploadSeq) {
          uploadOutcome = 'stale';
          return;
        }
        uploadOutcome = 'failed';
        uploadOutcomeDetail = 'Network error. Please try again.';
        if (!session.messageStatesEnabled) {
          showError(esc(uploadOutcomeDetail));
        }
      })
      .then(function () {
        if (uploadOutcome === 'stale') return;
        finishUploadAttempt(seq, uploadOutcome || 'failed', uploadOutcomeDetail);
      });
  }

  function onAttachClick() {
    if (uploading) return;
    if (!session.uuid || !session.token) {
      showError(esc('Please send a message first, then attach files.'));
      return;
    }
    if (fileInput) fileInput.click();
  }

  function onFileInputChange() {
    if (uploading) return;
    var f = fileInput.files && fileInput.files[0];
    try {
      fileInput.value = '';
    } catch (e0) {}
    if (!f) return;
    uploadAttachment(f);
  }

  function createConversation(name, email, message, cb) {
    session.loading = true;
    fetch(apiUrl('/client-api/v1/support/conversations'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        name: name,
        email: email,
        message: message,
        page_url: window.location.href,
        locale: (navigator.language || '').slice(0, 16) || null,
        timezone: visitorTimezone(),
      }),
      credentials: 'omit',
      cache: 'no-store',
    })
      .then(parseFetchResponse)
      .then(function (res) {
        session.loading = false;
        if (res.status === 404) {
          showChatUnavailable();
          cb('na');
          return;
        }
        if (!res.ok) {
          var msg = (res.json && res.json.message) || 'Could not start chat.';
          if (res.json && res.json.errors) {
            try {
              var k = Object.keys(res.json.errors)[0];
              if (k && res.json.errors[k][0]) msg = res.json.errors[k][0];
            } catch (e) {}
          }
          showError(esc(msg));
          cb(res.status);
          return;
        }
        if (!res.json || !res.json.conversation_uuid || !res.json.access_token) {
          showError('Could not start chat.');
          cb('bad');
          return;
        }
        saveSession(res.json.conversation_uuid, res.json.access_token);
        applyConversationStatusFromPayload(res.json);
        if (session.status == null) {
          session.status = 'waiting_operator';
        }
        session.messagesById = {};
        session.maxId = 0;
        session.attachmentEchoes = [];
        mergeMessages(res.json.messages || []);
        foot.style.display = 'block';
        renderMessages({ forceScroll: true });
        composerTa.value = '';
        autosizeComposer();
        pollIntervalMs = pollMsMin;
        if (panelOpen) {
          stopPoll = false;
          if (pollTimer) clearTimeout(pollTimer);
          schedulePollSoon(500);
        }
        cb(null);
      })
      .catch(function () {
        session.loading = false;
        showChatUnavailable();
        cb('net');
      });
  }

  function fetchMessages(isInitial, done) {
    if (!session.uuid || !session.token) {
      if (done) done();
      return;
    }
    var gen = ++messagesFetchGeneration;
    var url =
      apiUrl('/client-api/v1/support/conversations/' + encodeURIComponent(session.uuid) + '/messages') +
      '?after_id=' +
      (isInitial ? 0 : session.maxId) +
      '&limit=50';
    fetch(url, { method: 'GET', headers: authHeaders(), credentials: 'omit', cache: 'no-store' })
      .then(parseFetchResponse)
      .then(function (res) {
        if (res.status === 401) {
          clearSession();
          stopPollingInternal();
          foot.style.display = 'none';
          clearBody();
          renderNewForm();
          showError('Your session expired. Please start a new conversation.');
          pollIntervalMs = pollMsMin;
          if (done) done(true);
          return;
        }
        if (res.status === 404) {
          pollIntervalMs = pollMsMin;
          if (done) done();
          return;
        }
        if (res.status === 429) {
          pollIntervalMs = Math.min(MAX_POLL_MS, Math.max(pollIntervalMs * 2, pollMsMin * 2));
          if (done) done();
          return;
        }
        if (!res.ok) {
          if (done) done();
          return;
        }
        if (!res.json) {
          if (done) done();
          return;
        }
        if (gen !== messagesFetchGeneration) {
          if (panelOpen && session.uuid && session.token) {
            schedulePollSoon(pollIntervalMs);
          }
          return;
        }
        pollIntervalMs = pollMsMin;
        applyConversationStatusFromPayload(res.json);
        var snapBefore = getThreadSnapshot();
        var wasNearBottom = body ? isNearBottom(body) : true;
        mergeMessages(res.json.messages || []);
        var snapAfter = getThreadSnapshot();
        var threadChanged = threadSnapshotChanged(snapBefore, snapAfter);
        if (!isInitial && !threadChanged) {
          if (session.uuid && session.token) {
            foot.style.display = 'block';
          } else {
            foot.style.display = 'none';
          }
          if (done) done();
          return;
        }
        renderMessages({
          forceScroll: !!isInitial || (threadChanged && wasNearBottom),
        });
        if (session.uuid && session.token) {
          foot.style.display = 'block';
        } else {
          foot.style.display = 'none';
        }
        if (done) done();
      })
      .catch(function () {
        if (done) done();
      });
  }

  function sendMessage() {
    refreshComposerRefs();
    if (uploading || sendInFlight) return;
    if (!session.uuid || !session.token) return;
    var text = composerTa ? composerTa.value.trim() : '';
    if (!text) return;
    var seq = ++sendSeq;
    sendInFlight = true;
    syncComposerDisabled();
    if (session.messageStatesEnabled) {
      setFooterStatus('Sending…', { autoClear: false });
    }
    fetch(apiUrl('/client-api/v1/support/conversations/' + encodeURIComponent(session.uuid) + '/messages'), {
      method: 'POST',
      headers: authHeaders(),
      body: JSON.stringify({ message: text }),
      credentials: 'omit',
      cache: 'no-store',
    })
      .then(parseFetchResponse)
      .then(function (res) {
        if (seq !== sendSeq) return;
        if (res.status === 401) {
          if (session.messageStatesEnabled) setFooterStatus('');
          clearSession();
          stopPollingInternal();
          foot.style.display = 'none';
          clearBody();
          renderNewForm();
          showError('Your session expired. Please start a new conversation.');
          return;
        }
        if (res.status === 403) {
          if (session.messageStatesEnabled) {
            setFooterStatus('Failed', { warn: true, persist: true });
          }
          var m403 = (res.json && res.json.message) || 'Request denied.';
          showError(esc(m403));
          fetchMessages(true, function () {});
          return;
        }
        if (!res.ok) {
          if (session.messageStatesEnabled) {
            setFooterStatus('Failed', { warn: true, persist: true });
          }
          var msgS = 'Could not send.';
          if (res.json && res.json.message) msgS = res.json.message;
          if (res.json && res.json.errors) {
            try {
              var mk = Object.keys(res.json.errors)[0];
              if (mk && res.json.errors[mk][0]) msgS = res.json.errors[mk][0];
            } catch (e) {}
          }
          showError(esc(msgS));
          return;
        }
        if (!res.json || !res.json.message) {
          if (session.messageStatesEnabled) {
            setFooterStatus('Failed', { warn: true, persist: true });
          }
          showError('Could not send.');
          return;
        }
        applyConversationStatusFromPayload(res.json);
        if (composerTa) composerTa.value = '';
        autosizeComposer();
        mergeMessages([res.json.message]);
        renderMessages({ forceScroll: true });
        if (session.messageStatesEnabled) {
          setFooterStatus('Sent', { ttlMs: 2600 });
        }
        if (session.uuid && session.token) {
          foot.style.display = 'block';
        }
      })
      .catch(function () {
        if (seq !== sendSeq) return;
        if (session.messageStatesEnabled) {
          setFooterStatus('Failed', { warn: true, persist: true });
        }
        showError('Network error. Try again.');
      })
      .then(function () {
        finishSendAttempt(seq);
      });
  }

  function stopPollingInternal() {
    stopPoll = true;
    if (pollTimer) {
      clearTimeout(pollTimer);
      pollTimer = null;
    }
  }

  function schedulePollSoon(ms) {
    if (pollTimer) clearTimeout(pollTimer);
    pollTimer = setTimeout(tickPoll, ms || pollIntervalMs);
  }

  function tickPoll() {
    pollTimer = null;
    if (stopPoll || !panelOpen) return;
    fetchMessages(false, function () {
      if (stopPoll || !panelOpen) return;
      schedulePollSoon(pollIntervalMs);
    });
  }

  function openPanel() {
    panelOpen = true;
    panel.classList.add('open');
    if (launcher) {
      launcher.setAttribute('aria-label', t('launcherClose'));
      launcher.setAttribute('title', 'Close chat');
      launcher.classList.add('sc-launcher--open');
    }
    updateScViewport();
    scheduleSafariPanelLayout();
    stopPoll = false;
    loadSession();
    pollIntervalMs = pollMsMin;
    if (session.uuid && session.token) {
      bumpComposerEpoch();
      safeResetComposerState();
      foot.style.display = 'block';
      session.status = null;
      session.messagesById = {};
      session.maxId = 0;
      if (session.footerStatusHideTimer) {
        clearTimeout(session.footerStatusHideTimer);
        session.footerStatusHideTimer = null;
      }
      hideFooterStatus();
      clearBody();
      ensureWelcomeChrome();
      var ld = document.createElement('div');
      ld.className = 'sc-muted';
      ld.textContent = 'Loading…';
      body.appendChild(ld);
      fetchMessages(true, function (had401) {
        if (had401) return;
        if (!panelOpen) return;
        renderMessages({ forceScroll: true });
        autosizeComposer();
        ensureComposerReady();
        if (session.uuid && session.token) {
          foot.style.display = 'block';
        }
        stopPoll = false;
        if (pollTimer) clearTimeout(pollTimer);
        schedulePollSoon(pollIntervalMs);
      });
    } else {
      foot.style.display = 'none';
      renderNewForm();
    }
  }

  function closePanel() {
    panelOpen = false;
    panel.classList.remove('open');
    forceSafariPanelLayout();
    updateScViewport();
    stopPollingInternal();
    closeLightbox();
    bumpComposerEpoch();
    safeResetComposerState();
    if (launcher) {
      launcher.setAttribute('aria-label', t('launcherOpen'));
      launcher.setAttribute('title', 'Chat with support');
      launcher.classList.remove('sc-launcher--open');
    }
  }

  function bindTap(el, handler) {
    if (!el || typeof handler !== 'function') return;
    var lastTouchAt = 0;
    function run(ev) {
      if (ev && ev.cancelable) ev.preventDefault();
      if (ev) ev.stopPropagation();
      handler(ev);
    }
    el.addEventListener(
      'touchend',
      function (ev) {
        lastTouchAt = Date.now();
        run(ev);
      },
      { passive: false }
    );
    el.addEventListener('click', function (ev) {
      if (Date.now() - lastTouchAt < 700) {
        if (ev.cancelable) ev.preventDefault();
        ev.stopPropagation();
        return;
      }
      run(ev);
    });
  }

  function toggleChat() {
    if (panelOpen) closePanel();
    else openPanel();
  }

  function closeChat() {
    closePanel();
  }

  function autosizeComposer() {
    if (!composerTa) return;
    composerTa.style.height = 'auto';
    var h = Math.min(Math.max(composerTa.scrollHeight, 32), 96);
    composerTa.style.height = h + 'px';
  }

  bindTap(launcher, toggleChat);
  bindTap(closeBtn, closeChat);
  sendBtn.addEventListener('click', sendMessage);
  attachBtn.addEventListener('click', onAttachClick);
  fileInput.addEventListener('change', onFileInputChange);
  composerTa.addEventListener('input', autosizeComposer);
  composerTa.addEventListener('focus', function () {
    if (lightbox && lightbox.classList.contains('sc-open')) closeLightbox();
    ensureComposerReady();
  });
  composerTa.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });

  if (lightboxBackdrop) {
    lightboxBackdrop.addEventListener('click', closeLightbox);
  }
  if (lightboxCloseX) {
    lightboxCloseX.addEventListener('click', closeLightbox);
  }
  if (lightboxDl) {
    lightboxDl.addEventListener('click', function () {
      if (lightboxDownloadUrl) {
        downloadAttachmentWithAuth(lightboxDownloadUrl, lightboxDownloadName);
      }
    });
  }

  body.addEventListener('click', function (e) {
    var dl = e.target && e.target.closest ? e.target.closest('.sc-att-dl') : null;
    if (dl) {
      var url = dl.getAttribute('data-url');
      var name = dl.getAttribute('data-name') || 'file';
      if (url) downloadAttachmentWithAuth(url, name);
      return;
    }
    var pv = e.target && e.target.closest ? e.target.closest('.sc-att-img-btn[data-preview="1"]') : null;
    if (pv) {
      var img = pv.querySelector('.sc-att-img-el');
      var du = pv.getAttribute('data-url');
      var nm = pv.getAttribute('data-name') || 'image';
      if (img && img.src && img.classList.contains('sc-loaded')) {
        openLightbox(img.src, du, nm);
      }
      return;
    }
  });

  bindViewportListeners();

  shadow.addEventListener(
    'focusin',
    function (e) {
      var t = e.target;
      if (!t || (t.tagName !== 'INPUT' && t.tagName !== 'TEXTAREA')) return;
      updateScViewport();
      setTimeout(function () {
        updateScViewport();
        scrollFocusedFieldIntoView(t);
      }, 320);
    },
    true
  );

  host.setAttribute('data-sc-build', '20260531-support-chat-ui-2');
  document.body.appendChild(host);
  updateScViewport();

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') {
      stopPollingInternal();
    } else if (panelOpen && session.uuid && session.token) {
      stopPoll = false;
      ensureComposerReady();
      schedulePollSoon(500);
    }
  });
})();
