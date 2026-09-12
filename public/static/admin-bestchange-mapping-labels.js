/**
 * BestChange courses mapping labels + inactive-error demotion (maintenance closure).
 * Does not mutate data. Softens visual priority of inactive/historical errors.
 * ACTIVE ERROR remains high-priority; INACTIVE HISTORICAL ERROR is low-priority.
 */
(function (w, d) {
  'use strict';
  if (w.__exsBcMappingLabels) return;
  w.__exsBcMappingLabels = true;

  function isBcCourses() {
    var s = (w.location.pathname || '') + (w.location.hash || '');
    return /bestchange|parser-rates|courses/i.test(s);
  }

  function legendEl() {
    var el = d.getElementById('exs-bc-mapping-legend');
    if (el) return el;
    el = d.createElement('div');
    el.id = 'exs-bc-mapping-legend';
    el.setAttribute('style',
      'position:sticky;top:48px;z-index:99989;margin:0;padding:8px 14px;' +
      'border-bottom:1px solid #e2e8f0;background:#fffbeb;color:#0f172a;' +
      'font:12px/1.45 ui-sans-serif,system-ui,sans-serif;display:none');
    d.body.insertBefore(el, d.body.firstChild);
    return el;
  }

  function renderLegend() {
    if (!isBcCourses()) {
      var el = d.getElementById('exs-bc-mapping-legend');
      if (el) el.style.display = 'none';
      return;
    }
    var el = legendEl();
    el.style.display = 'block';
    el.innerHTML =
      '<strong>Классификация ошибок BestChange</strong> — ' +
      '<span style="color:#b91c1c;font-weight:700">ACTIVE ERROR</span> (высокий приоритет) · ' +
      '<span style="color:#92400e">INACTIVE HISTORICAL ERROR</span> (низкий) · ' +
      '<span style="color:#64748b">ARCHIVED / SUPERSEDED / STORM ARTIFACT</span> (история) · ' +
      'Фильтр по умолчанию: активные направления. Исторические строки доступны через фильтр статуса.';
  }

  function demoteInactiveErrorBadges() {
    if (!isBcCourses()) return;
    var nodes = d.querySelectorAll('span, div, td, li');
    nodes.forEach(function (n) {
      if (!n || n.children && n.children.length > 2) return;
      var t = (n.textContent || '').trim();
      if (t !== 'Ошибка обновления курса' && t !== 'Ошибка') return;
      // Heuristic: inactive/disabled nearby
      var row = n.closest('tr, [class*="row"], [class*="item"], li, div');
      var ctx = row ? (row.textContent || '') : '';
      var inactive = /отключ|неактив|disabled|inactive|архив/i.test(ctx);
      if (!inactive) {
        // Keep ACTIVE ERROR visually strong
        n.setAttribute('style', (n.getAttribute('style') || '') +
          ';color:#b91c1c;font-weight:700;background:#fef2f2;padding:1px 6px;border-radius:4px');
        n.title = 'ACTIVE ERROR — требует внимания';
        return;
      }
      n.textContent = 'INACTIVE HISTORICAL ERROR';
      n.setAttribute('style', (n.getAttribute('style') || '') +
        ';color:#92400e;font-weight:500;opacity:.75;background:#fffbeb;padding:1px 6px;border-radius:4px');
      n.title = 'Историческая ошибка неактивного маппинга — не используется для живого ценообразования';
    });
  }

  function tick() {
    renderLegend();
    demoteInactiveErrorBadges();
  }

  w.setInterval(tick, 2500);
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', tick);
  else tick();
  w.addEventListener('hashchange', tick);
})(window, document);
