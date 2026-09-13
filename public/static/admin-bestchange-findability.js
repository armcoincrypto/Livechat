/**
 * BestChange admin findability: search + counts + page size.
 * Does not mutate rates. Intercepts listing requests only.
 */
(function (w, d) {
  'use strict';
  if (w.__exsBcFindability) return;
  w.__exsBcFindability = true;

  var Q_KEY = 'exs-bc-q';
  var PP_KEY = 'exs-bc-per-page';

  function isBcList() {
    var s = (w.location.pathname || '') + (w.location.hash || '') + (w.location.search || '');
    return /bestchange/i.test(s) && !/bestchange\/settings|bestchange\/log/i.test(s);
  }

  function getQ() {
    var el = d.getElementById('exs-bc-q');
    if (el && typeof el.value === 'string') return el.value.trim();
    try { return (w.sessionStorage.getItem(Q_KEY) || '').trim(); } catch (e) { return ''; }
  }

  function getPerPage() {
    var el = d.getElementById('exs-bc-per-page');
    var n = el ? parseInt(el.value, 10) : 0;
    if (n === 30 || n === 50 || n === 100) return n;
    try {
      n = parseInt(w.sessionStorage.getItem(PP_KEY) || '', 10);
    } catch (e) { n = 0; }
    return (n === 30 || n === 50 || n === 100) ? n : 0;
  }

  function persist() {
    try {
      w.sessionStorage.setItem(Q_KEY, getQ());
      var pp = getPerPage();
      if (pp) w.sessionStorage.setItem(PP_KEY, String(pp));
    } catch (e) {}
  }

  function bar() {
    var el = d.getElementById('exs-bc-findability');
    if (el) return el;
    el = d.createElement('div');
    el.id = 'exs-bc-findability';
    el.setAttribute('style',
      'position:sticky;top:0;z-index:99990;display:none;gap:8px;align-items:center;flex-wrap:wrap;' +
      'padding:8px 14px;border-bottom:1px solid #cbd5e1;background:#f8fafc;color:#0f172a;' +
      'font:13px/1.4 ui-sans-serif,system-ui,sans-serif');
    el.innerHTML =
      '<label style="font-weight:600">Поиск</label>' +
      '<input id="exs-bc-q" type="search" placeholder="ID, USDTTRC20, YooMoney, сбер, AdvCash, TON, Gram" ' +
      'style="min-width:280px;flex:1;padding:6px 8px;border:1px solid #94a3b8;border-radius:6px"/>' +
      '<select id="exs-bc-per-page" style="padding:6px 8px;border:1px solid #94a3b8;border-radius:6px">' +
      '<option value="30">30 / стр</option><option value="50">50 / стр</option><option value="100">100 / стр</option>' +
      '</select>' +
      '<button id="exs-bc-find" type="button" style="padding:6px 12px;border-radius:6px;border:0;background:#1d4ed8;color:#fff;font-weight:600">Найти</button>' +
      '<span id="exs-bc-counts" style="color:#334155"></span>';
    d.body.insertBefore(el, d.body.firstChild);
    try {
      var savedQ = w.sessionStorage.getItem(Q_KEY) || '';
      var savedPp = w.sessionStorage.getItem(PP_KEY) || '30';
      el.querySelector('#exs-bc-q').value = savedQ;
      var sel = el.querySelector('#exs-bc-per-page');
      if (sel && (savedPp === '30' || savedPp === '50' || savedPp === '100')) sel.value = savedPp;
    } catch (e) {}
    el.querySelector('#exs-bc-find').addEventListener('click', function () {
      persist();
      refetch();
    });
    el.querySelector('#exs-bc-q').addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') {
        ev.preventDefault();
        persist();
        refetch();
      }
    });
    el.querySelector('#exs-bc-per-page').addEventListener('change', function () {
      persist();
      refetch();
    });
    return el;
  }

  function setCounts(filtered, total, page) {
    var el = d.getElementById('exs-bc-counts');
    if (!el) return;
    var parts = [];
    if (typeof filtered === 'number') parts.push('найдено ' + filtered);
    if (typeof total === 'number') parts.push('всего маппингов ' + total);
    if (page) parts.push('стр. ' + page);
    el.textContent = parts.join(' · ');
  }

  function refetch() {
    var nodes = d.querySelectorAll('button, a, [role="button"]');
    for (var i = 0; i < nodes.length; i++) {
      var t = (nodes[i].textContent || '').trim();
      if (t === '1') {
        nodes[i].click();
        return;
      }
    }
    var active = d.querySelector('[aria-current="page"], .page-item.active, [class*="pagination"] [class*="active"]');
    if (active) {
      var clickable = active.querySelector('button, a') || active;
      if (clickable.click) clickable.click();
    }
  }

  function shouldPatch(url) {
    if (!url) return false;
    var u = String(url);
    if (!/bestchange/i.test(u)) return false;
    if (/loadingDirection|loadingFilters|favorite-toggle|\/rating|\/settings|\/log|status\/bulk/.test(u)) return false;
    return true;
  }

  function mergeParams(url, extra) {
    try {
      var abs = new URL(url, w.location.origin);
      Object.keys(extra).forEach(function (k) {
        if (extra[k] === '' || extra[k] == null) abs.searchParams.delete(k);
        else abs.searchParams.set(k, String(extra[k]));
      });
      return abs.pathname + abs.search + abs.hash;
    } catch (e) {
      return url;
    }
  }

  function extraParams() {
    var out = {};
    var q = getQ();
    if (q) out.q = q;
    var pp = getPerPage();
    if (pp) out.per_page = String(pp);
    return out;
  }

  function noteCountsFromJson(json) {
    if (!json || typeof json !== 'object') return;
    var filtered = json.filtered_total;
    if (filtered == null && json.items && typeof json.items.total === 'number') filtered = json.items.total;
    var total = json.total_mapping_rows;
    var page = json.items && json.items.current_page;
    if (filtered != null || total != null) setCounts(filtered, total, page);
  }

  var origFetch = w.fetch;
  if (typeof origFetch === 'function') {
    w.fetch = function (input, init) {
      var url = typeof input === 'string' ? input : (input && input.url);
      if (shouldPatch(url) && isBcList()) {
        var extra = extraParams();
        if (Object.keys(extra).length) {
          var next = mergeParams(url, extra);
          if (typeof input === 'string') input = next;
          else if (w.Request && input instanceof w.Request) input = new w.Request(next, input);
        }
      }
      return origFetch.apply(this, arguments).then(function (res) {
        try {
          if (shouldPatch(url) && isBcList()) {
            res.clone().json().then(noteCountsFromJson).catch(function () {});
          }
        } catch (e) {}
        return res;
      });
    };
  }

  var origOpen = w.XMLHttpRequest && w.XMLHttpRequest.prototype.open;
  var origSend = w.XMLHttpRequest && w.XMLHttpRequest.prototype.send;
  if (origOpen && origSend) {
    w.XMLHttpRequest.prototype.open = function (method, url) {
      this.__exsBcUrl = url;
      if (shouldPatch(url) && isBcList()) {
        url = mergeParams(url, extraParams());
        this.__exsBcUrl = url;
      }
      return origOpen.apply(this, arguments);
    };
    w.XMLHttpRequest.prototype.send = function () {
      var xhr = this;
      xhr.addEventListener('load', function () {
        if (!shouldPatch(xhr.__exsBcUrl) || !isBcList()) return;
        try { noteCountsFromJson(JSON.parse(xhr.responseText)); } catch (e) {}
      });
      return origSend.apply(this, arguments);
    };
  }

  function tick() {
    var el = bar();
    el.style.display = isBcList() ? 'flex' : 'none';
  }

  w.setInterval(tick, 1500);
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', tick);
  else tick();
  w.addEventListener('hashchange', tick);
  w.addEventListener('popstate', tick);
})(window, document);
