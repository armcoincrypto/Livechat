/**
 * Admin UI scrubber: never leave raw internal rate-source tokens visible.
 * Defense in depth when list/detail Vue falls back to parser_source_name or
 * when status/update toasts briefly expose a stale field.
 */
(function (w, d) {
  'use strict';
  if (w.__exsRateSourceScrubber) return;
  w.__exsRateSourceScrubber = true;

  var MAP = {
    DERIVED_MARKET_BASELINE: 'Внутренний рынок · AUTO',
    ZELLE_USDTTRC20_BENCHMARK: 'Zelle · свой курс'
  };

  function scrubText(text) {
    if (!text || text.indexOf('DERIVED_MARKET') === -1 && text.indexOf('ZELLE_USDT') === -1) {
      return text;
    }
    var out = String(text);
    Object.keys(MAP).forEach(function (raw) {
      if (out.indexOf(raw) !== -1) {
        out = out.split(raw).join(MAP[raw]);
      }
    });
    return out;
  }

  function walk(node) {
    if (!node) return;
    if (node.nodeType === 3) {
      var next = scrubText(node.nodeValue);
      if (next !== node.nodeValue) node.nodeValue = next;
      return;
    }
    if (node.nodeType !== 1) return;
    var tag = (node.tagName || '').toLowerCase();
    if (tag === 'script' || tag === 'style' || tag === 'noscript') return;
    var child = node.firstChild;
    while (child) {
      var sibling = child.nextSibling;
      walk(child);
      child = sibling;
    }
  }

  function scrubRoot() {
    walk(d.body);
  }

  var scheduled = false;
  function schedule() {
    if (scheduled) return;
    scheduled = true;
    w.requestAnimationFrame(function () {
      scheduled = false;
      scrubRoot();
    });
  }

  function boot() {
    scrubRoot();
    var obs = new MutationObserver(schedule);
    obs.observe(d.documentElement, { childList: true, subtree: true, characterData: true });
  }

  if (d.readyState === 'loading') {
    d.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
