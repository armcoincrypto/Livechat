/**
 * Compact read-only pricing health strip + commercial inventory filters (Wave C).
 * Shows on direction exchange list screens. No mutations.
 */
(function (w, d) {
  'use strict';
  if (w.__exsPricingHealthStrip) return;
  w.__exsPricingHealthStrip = true;

  function isDirectionsList() {
    var s = (w.location.pathname || '') + (w.location.hash || '');
    if (/fees\/profit|verifications|mass-direction|min_price/i.test(s)) return false;
    return /direction[_-]?exchange/i.test(s);
  }

  function stripEl() {
    var el = d.getElementById('exs-pricing-health-strip');
    if (el) return el;
    el = d.createElement('div');
    el.id = 'exs-pricing-health-strip';
    el.setAttribute('style',
      'position:sticky;top:0;z-index:99990;margin:0;padding:10px 14px;' +
      'border-bottom:1px solid #e2e8f0;background:#f8fafc;color:#0f172a;' +
      'font:12px/1.45 ui-sans-serif,system-ui,sans-serif;display:none');
    d.body.insertBefore(el, d.body.firstChild);
    return el;
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function chip(label, active) {
    return '<button type="button" data-exs-filter="' + esc(label) + '" style="' +
      'margin:2px 4px 2px 0;padding:3px 8px;border:1px solid ' + (active ? '#0f766e' : '#cbd5e1') + ';' +
      'border-radius:6px;background:' + (active ? '#ccfbf1' : '#fff') + ';cursor:pointer;font:11px/1.3 inherit">' +
      esc(label) + '</button>';
  }

  function applyListFilter(key) {
    var map = {
      'Прибыль = 0': 'zero_profit=1',
      'BestChange': 'pricing_owner=bestchange',
      'Zelle': 'pricing_owner=zelle',
      'Derived': 'pricing_owner=derived',
      'Manual': 'pricing_owner=manual',
      'Позиция ≠ глобальной': 'position_override=1'
    };
    var q = map[key];
    if (!q) return;
    // Soft-navigate: append query and reload list via hash/query if Vue listens to location.
    var url = new URL(w.location.href);
    ['zero_profit', 'pricing_owner', 'position_override'].forEach(function (k) { url.searchParams.delete(k); });
    q.split('&').forEach(function (pair) {
      var p = pair.split('=');
      url.searchParams.set(p[0], p[1] || '1');
    });
    w.history.replaceState({}, '', url.toString());
    w.dispatchEvent(new Event('exs-pricing-filter'));
    // Trigger a soft refetch of known list endpoints so filters apply even without Vue rebuild.
    try {
      var apiBase = (w.adminConfig && (w.adminConfig.api_url || w.adminConfig.baseURL)) || '/administrator';
      fetch(String(apiBase).replace(/\/$/, '') + '/direction_exchange?' + url.searchParams.toString(), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function () { /* list Vue may ignore; inventory API still available */ }).catch(function () {});
    } catch (e) {}
    var note = d.getElementById('exs-pricing-filter-note');
    if (note) {
      note.textContent = key === 'Прибыль = 0'
        ? 'Фильтр: явная прибыль 0%. Это не значит «без маржи» — позиция BestChange / fix_fee могут быть активны. API: /administrator/pricing/inventory?filter=zero_profit'
        : 'Фильтр: ' + key + '. Список: добавьте параметр в запрос direction_exchange. Inventory: /administrator/pricing/inventory';
    }
  }

  function render(snap) {
    var o = (snap && snap.ownership_counts) || {};
    var z = (snap && snap.zero_profit) || {};
    var healthy = !!(snap && snap.healthy);
    var el = stripEl();
    el.style.display = 'block';
    el.innerHTML =
      '<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center">' +
        '<strong style="color:' + (healthy ? '#047857' : '#b45309') + '">' +
          (healthy ? 'Курсы в норме' : 'Курсы: нужна проверка') +
        '</strong>' +
        '<span>BestChange: <b>' + esc(o.BESTCHANGE) + '</b></span>' +
        '<span>Derived: <b>' + esc(o.DERIVED) + '</b></span>' +
        '<span>Zelle: <b>' + esc(o.ZELLE) + '</b></span>' +
        '<span>Manual: <b>' + esc(o.MANUAL) + '</b></span>' +
        '<span>Unknown: <b>' + esc(o.UNKNOWN) + '</b></span>' +
        '<span>Устаревшие критичные: <b>' + esc(snap.stale_critical_count) + '</b></span>' +
        '<span>Последнее обновление BestChange: <b>' + esc(snap.last_bestchange_update_human || '—') + '</b></span>' +
        '<span>Глобальная позиция: <b>' + esc(snap.global_bestchange_position) + '</b></span>' +
      '</div>' +
      '<div style="margin-top:6px;color:#475569">Явная прибыль 0%: BestChange ' + esc(z.BESTCHANGE) +
        ' · Zelle ' + esc(z.ZELLE) + ' · Derived ' + esc(z.DERIVED) + ' · Manual ' + esc(z.MANUAL) +
        ' <span style="opacity:.8">(не равно «без маржи»)</span></div>' +
      '<div style="margin-top:6px">' +
        chip('Прибыль = 0', false) +
        chip('BestChange', false) +
        chip('Zelle', false) +
        chip('Derived', false) +
        chip('Manual', false) +
        chip('Позиция ≠ глобальной', false) +
      '</div>' +
      '<div id="exs-pricing-filter-note" style="margin-top:4px;color:#64748b;font-size:11px"></div>';

    el.querySelectorAll('[data-exs-filter]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        applyListFilter(btn.getAttribute('data-exs-filter'));
      });
    });
  }

  function fetchHealth() {
    var candidates = [
      '/administrator/pricing/health',
      '/admin/pricing/health'
    ];
    var i = 0;
    function tryNext() {
      if (i >= candidates.length) return;
      var url = candidates[i++];
      fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
        .then(function (j) { render((j && j.data) || j); })
        .catch(tryNext);
    }
    tryNext();
  }

  function tick() {
    if (!isDirectionsList()) {
      var el = d.getElementById('exs-pricing-health-strip');
      if (el) el.style.display = 'none';
      return;
    }
    if (!w.__exsPricingHealthLoaded) {
      w.__exsPricingHealthLoaded = true;
      fetchHealth();
    } else {
      var el = d.getElementById('exs-pricing-health-strip');
      if (el) el.style.display = 'block';
    }
  }

  w.setInterval(tick, 3000);
  w.setInterval(function () {
    if (isDirectionsList() && w.__exsPricingHealthLoaded) {
      w.__exsPricingHealthLoaded = false; // refresh periodically
    }
  }, 60000);
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', tick);
  else tick();
  w.addEventListener('hashchange', tick);
  w.addEventListener('popstate', tick);
})(window, document);
