/**
 * Admin pricing ownership panel (no Vue rebuild required).
 * Activates on direction fees/profit routes and renders canonical BASE/profit/final.
 */
(function (w, d) {
  'use strict';
  if (w.__exsPricingOwnershipPanel) return;
  w.__exsPricingOwnershipPanel = true;

  function directionIdFromPath() {
    var m = (w.location.pathname || '').match(/direction[_-]?exchange\/(\d+)/i)
      || (w.location.hash || '').match(/direction[_-]?exchange\/(\d+)/i)
      || (w.location.pathname || '').match(/\/(\d+)\/(?:fees\/)?profit/i);
    return m ? m[1] : null;
  }

  function isProfitScreen() {
    var s = (w.location.pathname || '') + (w.location.hash || '');
    return /profit|fees\/profit|direction-edit-profit/i.test(s);
  }

  function panelEl() {
    var el = d.getElementById('exs-pricing-ownership-panel');
    if (el) return el;
    el = d.createElement('div');
    el.id = 'exs-pricing-ownership-panel';
    el.setAttribute('style',
      'position:fixed;left:16px;bottom:16px;z-index:99998;max-width:360px;' +
      'padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;' +
      'background:#fff;color:#0f172a;font:12px/1.45 ui-sans-serif,system-ui,sans-serif;' +
      'box-shadow:0 8px 24px rgba(15,23,42,.12);display:none');
    d.body.appendChild(el);
    return el;
  }

  function row(label, value) {
    return '<div style="display:flex;justify-content:space-between;gap:12px;margin:2px 0">' +
      '<span style="color:#64748b">' + label + '</span>' +
      '<strong style="text-align:right">' + (value == null || value === '' ? '—' : value) + '</strong></div>';
  }

  function render(data) {
    var a = (data && data.attributes) || {};
    var ra = (data && data.options && data.options.rateAuthority) || {};
    var p = ra.pricing || {};
    var el = panelEl();
    el.style.display = 'block';
    el.innerHTML =
      '<div style="font-weight:700;margin-bottom:6px">Pricing ownership</div>' +
      row('Ownership', ra.ownership || a.ownership || 'MANUAL_PROFIT') +
      row('BASE source', a.base_source || p.base_source) +
      row('Status', a.source_status || p.source_status) +
      row('BC position', a.bestchange_position || p.bestchange_position) +
      row('BASE', a.base_rate || p.base || a.course_value) +
      row('Прибыль', (a.profit != null ? a.profit : p.profit) + '%') +
      row('Floating', a.final_floating_rate || p.final_floating) +
      row('Fixed', a.final_fixed_rate || p.final_fixed) +
      row('fix_fee', a.fix_fee || p.fix_fee) +
      row('Updated', a.source_updated_at || p.source_updated_at) +
      '<div style="margin-top:8px;color:#475569;font-size:11px">Прибыль is manual and must survive BASE refresh.</div>';
  }

  function fetchProfit(id) {
    var base = (w.adminConfig && (w.adminConfig.api_url || w.adminConfig.baseURL || w.adminConfig.url)) || '';
    var candidates = [
      '/administrator/direction_exchange/' + id + '/fees/profit',
      '/admin/direction_exchange/' + id + '/fees/profit',
      base.replace(/\/$/, '') + '/direction_exchange/' + id + '/fees/profit'
    ];
    var i = 0;
    function tryNext() {
      if (i >= candidates.length) return;
      var url = candidates[i++];
      fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
        .then(render)
        .catch(tryNext);
    }
    tryNext();
  }

  function tick() {
    if (!isProfitScreen()) {
      var el = d.getElementById('exs-pricing-ownership-panel');
      if (el) el.style.display = 'none';
      return;
    }
    var id = directionIdFromPath();
    if (id) fetchProfit(id);
  }

  w.setInterval(tick, 2500);
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', tick);
  else tick();
  w.addEventListener('hashchange', tick);
  w.addEventListener('popstate', tick);
})(window, document);
