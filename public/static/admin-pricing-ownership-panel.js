/**
 * Admin pricing ownership / truth panel (operator safety).
 * Activates on direction fees/profit routes.
 * Labels: BASE / PROFIT / FINAL CUSTOMER RATE — course_value is commercial, not "BestChange rate".
 */
(function (w, d) {
  'use strict';
  if (w.__exsPricingOwnershipPanel) return;
  w.__exsPricingOwnershipPanel = true;

  var expanded = false;
  var lastPayload = null;

  function directionIdFromPath() {
    var m = (w.location.pathname || '').match(/direction[_-]?exchange\/(\d+)/i)
      || (w.location.hash || '').match(/direction[_-]?exchange\/(\d+)/i)
      || (w.location.pathname || '').match(/\/(\d+)\/(?:fees\/)?profit/i);
    return m ? m[1] : null;
  }

  function isProfitScreen() {
    var s = (w.location.pathname || '') + (w.location.hash || '');
    return /profit|fees\/profit|direction-edit-profit|pricing\/explain|pricing\/truth|courses/i.test(s);
  }

  function panelEl() {
    var el = d.getElementById('exs-pricing-ownership-panel');
    if (el) return el;
    el = d.createElement('div');
    el.id = 'exs-pricing-ownership-panel';
    el.setAttribute('style',
      'position:fixed;left:16px;bottom:16px;z-index:99998;max-width:460px;' +
      'padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;' +
      'background:#fff;color:#0f172a;font:12px/1.45 ui-sans-serif,system-ui,sans-serif;' +
      'box-shadow:0 8px 24px rgba(15,23,42,.12);display:none;max-height:75vh;overflow:auto');
    d.body.appendChild(el);
    return el;
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function row(label, value) {
    return '<div style="display:flex;justify-content:space-between;gap:12px;margin:2px 0">' +
      '<span style="color:#64748b">' + esc(label) + '</span>' +
      '<strong style="text-align:right">' + esc(value == null || value === '' ? '—' : value) + '</strong></div>';
  }

  function warnBlock(warnings) {
    if (!warnings || !warnings.length) return '';
    return '<div style="margin-top:8px;padding:8px;border:1px solid #f59e0b;background:#fffbeb;border-radius:6px;color:#92400e;font-size:11px">' +
      warnings.map(function (w) { return '<div>• ' + esc(w) + '</div>'; }).join('') +
      '</div>';
  }

  function render(data) {
    lastPayload = data;
    var truth = (data && data.data && (data.data.OWNER || data.data.BASE)) ? data.data : null;
    var a = (data && data.attributes) || {};
    var ra = (data && data.options && data.options.rateAuthority) || {};
    var p = (truth && truth.explain) || ra.pricing || a.pricing_explanation || (data && data.data) || {};
    var own = p.ownership || {};
    var pos = p.position || {};
    var com = p.commercial || {};
    var fresh = p.freshness || {};
    var layers = p.layers || {};
    var rates = p.customer_rates || {};
    var sourceLabel = (truth && truth.OWNER) || own.label || ra.rate_source_label || a.rate_source_label || '—';
    var pairOverride = (truth && truth.CONFIGURED_POSITION != null)
      ? String(truth.CONFIGURED_POSITION)
      : (pos.pair_override == null || pos.pair_override === '' ? 'нет' : String(pos.pair_override));
    var el = panelEl();
    el.style.display = 'block';

    var owner = (truth && truth.OWNER) || own.family || sourceLabel;
    var base = (truth && truth.BASE) || p.base || (p.source_rate && p.source_rate.base) || a.base_rate || '—';
    var profit = (truth && truth.PROFIT != null) ? truth.PROFIT : (com.explicit_profit_percent != null ? com.explicit_profit_percent : (a.profit != null ? a.profit : p.profit));
    var finalRate = (truth && truth.CALCULATOR_FINAL_RATE) || rates.floating || a.final_floating_rate || p.final_floating || '—';
    var stored = (truth && truth.STORED_COURSE_VALUE) || a.course_value || '—';
    var effPos = (truth && truth.EFFECTIVE_POSITION != null) ? truth.EFFECTIVE_POSITION : (pos.effective != null ? pos.effective : a.effective_bestchange_position);
    var warnings = (truth && truth.warnings) || [];

    var summary =
      row('OWNER', owner) +
      row('RATE_MODE', (truth && truth.RATE_MODE) || '—') +
      row('Источник курса', (truth && truth.SELECTED_EXCHANGER) || p.source_exchanger || own.label || sourceLabel) +
      row('Эффективная позиция', effPos != null ? ('#' + effPos) : '—') +
      row('CONFIGURED_POSITION', pairOverride) +
      row('SELECTED_EXCHANGER', (truth && truth.SELECTED_EXCHANGER) || p.source_exchanger || '—') +
      row('SELECTED_RATE_VALUE', (truth && truth.SELECTED_RATE_VALUE) || p.bestchange_rate || '—') +
      row('BASE', base) +
      row('PROFIT', (profit != null ? profit : '—') + '%') +
      row('Прибыль', (profit != null ? profit : '—') + '%') +
      row('FINAL CUSTOMER RATE', finalRate) +
      row('Итоговый курс', finalRate) +
      row('STORED_COURSE_VALUE', stored) +
      row('PUBLIC_QUOTE_RATE', (truth && truth.PUBLIC_QUOTE_RATE) || finalRate) +
      row('XML_RATE', (truth && truth.XML_RATE) || stored) +
      row('FIX_FEE', (truth && truth.FIX_FEE != null) ? truth.FIX_FEE : (com.fix_fee != null ? com.fix_fee : p.fix_fee)) +
      row('PRICING_HEALTH', (truth && (truth.PRICING_HEALTH_LABEL || truth.PRICING_HEALTH)) || fresh.state_label || a.freshness_label || '—') +
      (truth && truth.manual_controls_non_authoritative
        ? '<div style="margin-top:6px;color:#b45309;font-size:11px">Manual rate controls are non-authoritative for this OWNER. Ownership switch requires a deliberate action.</div>'
        : '') +
      warnBlock(warnings);

    var details =
      row('BESTCHANGE_PAIR', (truth && truth.BESTCHANGE_PAIR)
        ? ((truth.BESTCHANGE_PAIR.from || '') + ' → ' + (truth.BESTCHANGE_PAIR.to || ''))
        : ((p.pair && (p.pair.from + ' → ' + p.pair.to)) || '—')) +
      row('LAST_BESTCHANGE_UPDATE', (truth && truth.LAST_BESTCHANGE_UPDATE) || p.source_updated_at || fresh.human || '—') +
      row('LAST_COURSE_UPDATE', (truth && truth.LAST_COURSE_UPDATE) || '—') +
      row('Почему назначен', own.reason || '—') +
      row('Глобальная позиция', pos.global != null ? pos.global : '—') +
      row('Переопределение пары', pairOverride) +
      '<div style="margin-top:8px;color:#475569;font-size:11px">' +
        esc('BASE = source book rate. PROFIT = commercial %. FINAL CUSTOMER RATE = client rate. STORED_COURSE_VALUE is commercial snapshot — not «BestChange rate».') +
      '</div>' +
      (com.zero_profit_wording ? '<div style="margin-top:6px;color:#475569;font-size:11px">' + esc(com.zero_profit_wording) + '</div>' : '') +
      (layers.when_each_applies ? '<div style="margin-top:6px;color:#64748b;font-size:11px">' + esc(layers.when_each_applies) + '</div>' : '');

    el.innerHTML =
      '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px">' +
        '<div style="font-weight:700">Admin pricing truth</div>' +
        '<button type="button" data-exs-pricing-toggle="1" style="border:1px solid #cbd5e1;background:#f8fafc;border-radius:6px;padding:4px 8px;font:11px/1.2 ui-sans-serif,system-ui,sans-serif;cursor:pointer;color:#0f172a">' +
          (expanded ? 'Свернуть' : 'Подробнее') +
        '</button>' +
      '</div>' +
      '<div style="font-weight:600;margin-bottom:4px;color:#334155">Почему такой курс?</div>' +
      summary +
      (expanded ? '<div style="margin-top:8px;padding-top:8px;border-top:1px solid #e2e8f0">' + details + '</div>' : '');

    var btn = el.querySelector('[data-exs-pricing-toggle]');
    if (btn) {
      btn.addEventListener('click', function () {
        expanded = !expanded;
        if (lastPayload) render(lastPayload);
      });
    }
  }

  function fetchTruth(id) {
    var candidates = [
      '/administrator/direction_exchange/' + id + '/pricing/truth',
      '/administrator/direction_exchange/' + id + '/pricing/explain',
      '/administrator/direction_exchange/' + id + '/fees/profit'
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

  var lastId = null;
  function tick() {
    if (!isProfitScreen()) {
      var el = d.getElementById('exs-pricing-ownership-panel');
      if (el) el.style.display = 'none';
      lastId = null;
      return;
    }
    var id = directionIdFromPath();
    if (id && id !== lastId) {
      lastId = id;
      expanded = false;
      fetchTruth(id);
    }
  }

  w.setInterval(tick, 2500);
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', tick);
  else tick();
  w.addEventListener('hashchange', tick);
  w.addEventListener('popstate', tick);
})(window, document);
