/**
 * Inject operational requisites into iEX order-detail.
 * Compiled Vue is not rebuilt; this hydrates OrderIdResource.operational_requisites
 * from the same payload the SPA already loads.
 */
(function (w, d) {
  "use strict";
  if (w.__exsOrderOpsPanel) return;
  w.__exsOrderOpsPanel = true;

  function adminPrefix() {
    var cfg = w.adminConfig || w.adminConfig || {};
    var p = cfg.adminPath || cfg.admin_path || "";
    if (p) return String(p).replace(/\/$/, "");
    var m = (w.location.pathname || "").match(/^\/([^/]+)\//);
    return m ? "/" + m[1] : "/iexadmin";
  }

  function orderIdFromLocation() {
    var s = (w.location.pathname || "") + " " + (w.location.hash || "");
    var m = s.match(/orders\/(?:list\/|show\/)?(\d+)/i)
      || s.match(/#\/orders\/(\d+)/i)
      || s.match(/#\/order\/(\d+)/i)
      || s.match(/order(?:s)?(?:-id)?[=/](\d+)/i);
    return m ? m[1] : null;
  }

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c];
    });
  }

  function row(label, value) {
    return '<div style="display:flex;justify-content:space-between;gap:12px;margin:4px 0">' +
      '<span style="color:#64748b;min-width:110px">' + esc(label) + "</span>" +
      '<strong style="text-align:right;word-break:break-all">' + esc(value) + "</strong></div>";
  }

  function panelEl() {
    var el = d.getElementById("exs-order-ops-inline");
    if (el) return el;
    el = d.createElement("div");
    el.id = "exs-order-ops-inline";
    el.setAttribute("style",
      "margin:12px 0 16px;padding:14px 16px;border:1px solid #cbd5e1;border-radius:10px;" +
      "background:#f8fafc;color:#0f172a;font:13px/1.45 ui-sans-serif,system-ui,sans-serif");
    return el;
  }

  function placePanel(el) {
    if (el.parentNode && el.dataset.placed === "1") return;
    var nodes = d.querySelectorAll("div,h2,h3,h4,p,span,strong");
    var target = null;
    for (var i = 0; i < nodes.length; i++) {
      var t = (nodes[i].textContent || "").replace(/\s+/g, " ").trim();
      if (t === "Работают над заявкой" || t.indexOf("Работают над заявкой") === 0 || t === "О клиенте" || t.indexOf("О клиенте") === 0) {
        target = nodes[i];
        break;
      }
    }
    if (target) {
      var wrap = target.closest("section,.card,.box,.ant-card,.el-card,.v-card") || target.parentNode;
      if (wrap && wrap.parentNode) {
        wrap.parentNode.insertBefore(el, wrap);
        el.dataset.placed = "1";
        el.style.position = "static";
        el.style.maxWidth = "760px";
        return;
      }
    }
    if (!el.parentNode) {
      el.style.position = "fixed";
      el.style.right = "16px";
      el.style.top = "88px";
      el.style.zIndex = "99990";
      el.style.width = "340px";
      el.style.maxHeight = "70vh";
      el.style.overflow = "auto";
      el.style.background = "#fff";
      d.body.appendChild(el);
    }
  }

  function attrsFromPayload(payload) {
    if (!payload || typeof payload !== "object") return null;
    if (payload.attributes && (payload.attributes.operational_requisites || payload.attributes.in_currency)) {
      return payload.attributes;
    }
    if (payload.data && payload.data.attributes) return payload.data.attributes;
    if (payload.operational_requisites) return payload;
    return null;
  }

  function render(payload) {
    var attrs = attrsFromPayload(payload);
    if (!attrs) return;
    var ops = attrs.operational_requisites || {};
    var rows = ops.rows || [];
    var el = panelEl();
    if (!rows.length && !attrs.assigned_operator && !attrs.completed_by) {
      el.style.display = "none";
      return;
    }
    var html = '<div style="font-weight:700;margin-bottom:8px">Данные заявки / Реквизиты</div>';
    if (attrs.assigned_operator) html += row("Оператор", attrs.assigned_operator);
    if (attrs.claimed_at) html += row("Принято", attrs.claimed_at);
    if (attrs.completed_by) html += row("Завершил", attrs.completed_by);
    rows.forEach(function (r) {
      if (!r || !r.value) return;
      if (String(r.label) === "Имя" && /^user$/i.test(String(r.value))) return;
      html += row(r.label, r.value);
    });
    if (ops.deposit_address && ops.payout_wallet && ops.deposit_address !== ops.payout_wallet) {
      html += '<div style="margin-top:8px;color:#475569;font-size:11px">Адрес для депозита и кошелёк выплаты — разные поля.</div>';
    }
    el.innerHTML = html;
    el.style.display = "block";
    placePanel(el);
  }

  function load(id) {
    var token = (d.querySelector('meta[name="csrf-token"]') || {}).content || "";
    var prefix = adminPrefix();
    var urls = [
      prefix + "/frontend-api/orders/list/" + id,
      prefix + "/orders/list/" + id
    ];
    var i = 0;
    function next() {
      if (i >= urls.length) return;
      var url = urls[i++];
      fetch(url, {
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", "X-CSRF-TOKEN": token }
      }).then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
        .then(render)
        .catch(next);
    }
    next();
  }

  function maybeCapture(url, body) {
    if (!url || String(url).indexOf("/orders/list/") === -1) return;
    try {
      render(typeof body === "string" ? JSON.parse(body) : body);
    } catch (e) {}
  }

  var origFetch = w.fetch;
  if (typeof origFetch === "function") {
    w.fetch = function () {
      var url = arguments[0];
      return origFetch.apply(this, arguments).then(function (res) {
        try {
          var cloned = res.clone();
          cloned.json().then(function (body) { maybeCapture(url && url.url ? url.url : url, body); }).catch(function () {});
        } catch (e) {}
        return res;
      });
    };
  }
  if (w.XMLHttpRequest && w.XMLHttpRequest.prototype) {
    var origOpen = w.XMLHttpRequest.prototype.open;
    w.XMLHttpRequest.prototype.open = function (method, url) {
      this.addEventListener("load", function () {
        maybeCapture(url, this.responseText);
      });
      return origOpen.apply(this, arguments);
    };
  }

  function tick() {
    var id = orderIdFromLocation();
    if (!id) {
      var el = d.getElementById("exs-order-ops-inline");
      if (el) el.style.display = "none";
      return;
    }
    if (w.__exsOrderOpsLoaded === id) return;
    w.__exsOrderOpsLoaded = id;
    load(id);
  }

  w.addEventListener("hashchange", function () { w.__exsOrderOpsLoaded = null; tick(); });
  w.addEventListener("popstate", function () { w.__exsOrderOpsLoaded = null; tick(); });
  setInterval(tick, 1500);
  if (d.readyState === "loading") d.addEventListener("DOMContentLoaded", tick);
  else tick();
})(window, document);
