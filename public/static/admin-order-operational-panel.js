/**
 * Compact operational requisites on iEX order-detail (compiled Vue is not rebuilt here).
 * Hydrates from OrderIdResource.operational_requisites.
 */
(function (w, d) {
  "use strict";
  if (w.__exsOrderOpsPanel) return;
  w.__exsOrderOpsPanel = true;

  function orderIdFromLocation() {
    var s = (w.location.pathname || "") + " " + (w.location.hash || "");
    var m = s.match(/orders\/(?:list\/)?(\d+)/i) || s.match(/#\/orders\/(\d+)/i);
    return m ? m[1] : null;
  }

  function adminPrefix() {
    var cfg = w.adminConfig || {};
    if (cfg.adminPath) return String(cfg.adminPath).replace(/\/$/, "");
    if (cfg.admin_path) return String(cfg.admin_path).replace(/\/$/, "");
    return "/iexadmin";
  }

  function panelEl() {
    var el = d.getElementById("exs-order-ops-panel");
    if (el) return el;
    el = d.createElement("div");
    el.id = "exs-order-ops-panel";
    el.setAttribute("style",
      "position:fixed;right:16px;top:88px;z-index:99990;width:320px;max-height:70vh;overflow:auto;" +
      "padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#0f172a;" +
      "font:12px/1.45 ui-sans-serif,system-ui,sans-serif;box-shadow:0 8px 24px rgba(15,23,42,.12);display:none");
    d.body.appendChild(el);
    return el;
  }

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c];
    });
  }

  function row(label, value) {
    return '<div style="display:flex;justify-content:space-between;gap:10px;margin:3px 0">' +
      '<span style="color:#64748b">' + esc(label) + "</span>" +
      '<strong style="text-align:right;word-break:break-all">' + esc(value) + "</strong></div>";
  }

  function render(payload) {
    var attrs = (payload && (payload.attributes || payload.data || payload)) || {};
    var ops = attrs.operational_requisites || {};
    var rows = ops.rows || [];
    var el = panelEl();
    if (!rows.length && !attrs.assigned_operator && !attrs.completed_by) {
      el.style.display = "none";
      return;
    }
    var html = '<div style="font-weight:700;margin-bottom:6px">Данные заявки / Реквизиты</div>';
    if (attrs.assigned_operator) html += row("Оператор", attrs.assigned_operator);
    if (attrs.claimed_at) html += row("Принято", attrs.claimed_at);
    if (attrs.completed_by) html += row("Завершил", attrs.completed_by);
    rows.forEach(function (r) { html += row(r.label, r.value); });
    if (ops.deposit_address) html += '<div style="margin-top:6px;color:#475569;font-size:11px">Депозит ≠ кошелёк выплаты</div>';
    el.innerHTML = html;
    el.style.display = "block";
  }

  function load(id) {
    var token = (d.querySelector('meta[name="csrf-token"]') || {}).content || "";
    var url = adminPrefix() + "/orders/list/" + id;
    fetch(url, {
      credentials: "same-origin",
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", "X-CSRF-TOKEN": token }
    }).then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
      .then(render)
      .catch(function () { /* keep Vue screen if overlay cannot load */ });
  }

  function tick() {
    var id = orderIdFromLocation();
    var el = d.getElementById("exs-order-ops-panel");
    if (!id) {
      if (el) el.style.display = "none";
      return;
    }
    if (w.__exsOrderOpsLoaded === id) return;
    w.__exsOrderOpsLoaded = id;
    load(id);
  }

  w.addEventListener("hashchange", function () { w.__exsOrderOpsLoaded = null; tick(); });
  w.addEventListener("popstate", function () { w.__exsOrderOpsLoaded = null; tick(); });
  setInterval(tick, 1200);
  if (d.readyState === "loading") d.addEventListener("DOMContentLoaded", tick);
  else tick();
})(window, document);
