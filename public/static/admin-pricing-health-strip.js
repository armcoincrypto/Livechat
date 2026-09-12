/**
 * Compact read-only pricing health strip + commercial inventory filters.
 * Maintenance closure: adds active-error / no-rate / stale / zero / Top-N summary.
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
      'Позиция ≠ глобальной': 'position_override=1',
      'Active only': 'pricing_view=active',
      'Active errors': 'pricing_view=active_errors',
      'Inactive historical errors': 'pricing_view=inactive_errors'
    };
    var q = map[key];
    if (!q) return;
    var url = new URL(w.location.href);
    ['zero_profit', 'pricing_owner', 'position_override', 'pricing_view'].forEach(function (k) { url.searchParams.delete(k); });
    q.split('&').forEach(function (pair) {
      var p = pair.split('=');
      url.searchParams.set(p[0], p[1] || '1');
    });
    w.history.replaceState({}, '', url.toString());
    w.dispatchEvent(new Event('exs-pricing-filter'));
    var note = d.getElementById('exs-pricing-filter-note');
    if (note) {
      note.textContent = 'Фильтр: ' + key + '. Операционный вид по умолчанию — Active only. Исторические ошибки не равны ACTIVE ERROR.';
    }
  }

  function render(snap) {
    var o = (snap && snap.ownership_counts) || {};
    var z = (snap && snap.zero_profit) || {};
    var healthy = !!(snap && snap.healthy);
    var activeDirs = snap.active_directions || (
      (Number(o.BESTCHANGE || 0) + Number(o.DERIVED || 0) + Number(o.ZELLE || 0) + Number(o.MANUAL || 0) + Number(o.UNKNOWN || 0)) || '—'
    );
    var el = stripEl();
    el.style.display = 'block';
    el.innerHTML =
      '<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center">' +
        '<strong style="color:' + (healthy ? '#047857' : '#b45309') + '">' +
          'Pricing Health: ' + (healthy ? 'PASS' : 'CHECK') +
        '</strong>' +
        '<span>Active directions: <b>' + esc(activeDirs) + '</b></span>' +
        '<span>BestChange: <b>' + esc(o.BESTCHANGE) + '</b></span>' +
        '<span>Derived: <b>' + esc(o.DERIVED) + '</b></span>' +
        '<span>Zelle: <b>' + esc(o.ZELLE) + '</b></span>' +
        '<span>Manual: <b>' + esc(o.MANUAL) + '</b></span>' +
        '<span>Active errors: <b style="color:' + ((snap.active_error_count || 0) > 0 ? '#b91c1c' : '#047857') + '">' + esc(snap.active_error_count || 0) + '</b></span>' +
        '<span>No-rate: <b>' + esc(snap.active_no_rate_count || 0) + '</b></span>' +
        '<span>Stale: <b>' + esc(snap.stale_critical_count || 0) + '</b></span>' +
        '<span>Zero-rate: <b>' + esc(snap.zero_rate_active_count || 0) + '</b></span>' +
        '<span>Ownership drift: <b>' + esc(snap.ownership_drift || o.UNKNOWN || 0) + '</b></span>' +
        '<span>Top-N eligible: <b>' + esc((snap.topn && snap.topn.TOTAL_ELIGIBLE) || '—') + '</b></span>' +
      '</div>' +
      '<div style="margin-top:6px;color:#475569">Явная прибыль 0%: BestChange ' + esc(z.BESTCHANGE) +
        ' · Zelle ' + esc(z.ZELLE) + ' · Derived ' + esc(z.DERIVED) + ' · Manual ' + esc(z.MANUAL) +
        ' <span style="opacity:.8">(не равно «без маржи»)</span></div>' +
      '<div style="margin-top:6px">' +
        chip('Active only', true) +
        chip('Active errors', false) +
        chip('Inactive historical errors', false) +
        chip('Прибыль = 0', false) +
        chip('BestChange', false) +
        chip('Zelle', false) +
        chip('Derived', false) +
        chip('Manual', false) +
        chip('Позиция ≠ глобальной', false) +
      '</div>' +
      '<div id="exs-pricing-filter-note" style="margin-top:4px;color:#64748b;font-size:11px">Операционный вид: Active only. Исторические/архивные ошибки — низкий приоритет.</div>';

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
      w.__exsPricingHealthLoaded = false;
    }
  }, 60000);
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', tick);
  else tick();
  w.addEventListener('hashchange', tick);
  w.addEventListener('popstate', tick);
})(window, document);
