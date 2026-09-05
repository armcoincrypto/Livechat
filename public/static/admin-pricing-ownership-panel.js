/**
 * Admin pricing ownership / «Почему такой курс?» panel (Wave C).
 * Activates on direction fees/profit routes. Russian owner language.
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
    return /profit|fees\/profit|direction-edit-profit|pricing\/explain/i.test(s);
  }

  function panelEl() {
    var el = d.getElementById('exs-pricing-ownership-panel');
    if (el) return el;
    el = d.createElement('div');
    el.id = 'exs-pricing-ownership-panel';
    el.setAttribute('style',
      'position:fixed;left:16px;bottom:16px;z-index:99998;max-width:420px;' +
      'padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;' +
      'background:#fff;color:#0f172a;font:12px/1.45 ui-sans-serif,system-ui,sans-serif;' +
      'box-shadow:0 8px 24px rgba(15,23,42,.12);display:none;max-height:70vh;overflow:auto');
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

  function render(data) {
    var a = (data && data.attributes) || {};
    var ra = (data && data.options && data.options.rateAuthority) || {};
    var p = ra.pricing || a.pricing_explanation || (data && data.data) || {};
    var own = p.ownership || {};
    var pos = p.position || {};
    var com = p.commercial || {};
    var fresh = p.freshness || {};
    var layers = p.layers || {};
    var rates = p.customer_rates || {};
    var sourceLabel = own.label || ra.rate_source_label || a.rate_source_label || '—';
    var pairOverride = pos.pair_override == null || pos.pair_override === ''
      ? 'нет'
      : String(pos.pair_override);
    var el = panelEl();
    el.style.display = 'block';
    el.innerHTML =
      '<div style="font-weight:700;margin-bottom:6px">Почему такой курс?</div>' +
      row('Источник курса', sourceLabel) +
      row('Почему назначен', own.reason || '—') +
      row('Глобальная позиция', pos.global != null ? pos.global : '—') +
      row('Переопределение пары', pairOverride) +
      row('Эффективная позиция', pos.effective != null ? pos.effective : (a.effective_bestchange_position || p.bestchange_position || '—')) +
      row('Курс источника', p.base || a.base_rate || '—') +
      row('Прибыль', (com.explicit_profit_percent != null ? com.explicit_profit_percent : (a.profit != null ? a.profit : p.profit)) + '%') +
      row(layers.is_floating_fee_additional ? 'Доп. корректировка (плав.)' : 'Корректировка плавающего (от прибыли)',
        layers.floating_adjustment_percent != null ? layers.floating_adjustment_percent + '%' : '—') +
      row('Курс клиента (плавающий)', rates.floating || a.final_floating_rate || p.final_floating || '—') +
      row(layers.is_fixed_fee_additional ? 'Доп. корректировка (фикс.)' : 'Корректировка фиксированного',
        (com.fix_fee != null ? com.fix_fee : p.fix_fee) + '%') +
      row('Курс клиента (фиксированный)', rates.fixed || a.final_fixed_rate || p.final_fixed || '—') +
      row('Последнее обновление', fresh.human || a.freshness_human || p.source_updated_at || '—') +
      row('Состояние', fresh.state_label || a.freshness_label || p.source_status || '—') +
      (com.zero_profit_wording ? '<div style="margin-top:6px;color:#475569;font-size:11px">' + esc(com.zero_profit_wording) + '</div>' : '') +
      '<div style="margin-top:8px;color:#475569;font-size:11px">' +
        esc(own.profit_survives_hint || a.profit_survives_hint || 'Коммерческая настройка (Прибыль) сохраняется при автообновлении курса.') +
      '</div>' +
      (layers.when_each_applies ? '<div style="margin-top:6px;color:#64748b;font-size:11px">' + esc(layers.when_each_applies) + '</div>' : '');
  }

  function fetchProfit(id) {
    var base = (w.adminConfig && (w.adminConfig.api_url || w.adminConfig.baseURL || w.adminConfig.url)) || '';
    var candidates = [
      '/administrator/direction_exchange/' + id + '/fees/profit',
      '/administrator/direction_exchange/' + id + '/pricing/explain',
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
      fetchProfit(id);
    }
  }

  w.setInterval(tick, 2500);
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', tick);
  else tick();
  w.addEventListener('hashchange', tick);
  w.addEventListener('popstate', tick);
})(window, document);
