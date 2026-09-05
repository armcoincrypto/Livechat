/**
 * Wave A — PAID operator queue panel for admin Vue SPA.
 * Injects age-bucket chips + counts without rebuilding Vue source.
 * Filters use existing orders list API: ?paidAge=paid|gt1h|gt6h|gt24h
 * Does not display customer PII (email/wallet).
 */
(function (w, d) {
  "use strict";
  if (w.__exsPaidQueuePanel) return;
  w.__exsPaidQueuePanel = true;

  function adminPrefix() {
    var cfg = w.adminConfig || {};
    var p = cfg.adminPath || cfg.admin_path || "";
    if (p) return String(p).replace(/\/$/, "");
    var m = (w.location.pathname || "").match(/^\/([^/]+)\//);
    return m ? "/" + m[1] : "/iexadmin";
  }

  function onOrdersSurface() {
    var s = (w.location.pathname || "") + " " + (w.location.hash || "");
    return /orders/i.test(s) && !/orders\/\d+/i.test(s.replace(/#/, "/"));
  }

  function panelEl() {
    var el = d.getElementById("exs-paid-queue-panel");
    if (el) return el;
    el = d.createElement("div");
    el.id = "exs-paid-queue-panel";
    el.setAttribute(
      "style",
      "margin:12px 0 16px;padding:14px 16px;border:1px solid #f59e0b;border-radius:10px;" +
        "background:#fffbeb;color:#78350f;font:13px/1.45 ui-sans-serif,system-ui,sans-serif"
    );
    return el;
  }

  function place(el) {
    if (el.parentNode && el.dataset.placed === "1") return;
    var app = d.getElementById("app") || d.body;
    var first = app.firstChild;
    if (first) app.insertBefore(el, first);
    else app.appendChild(el);
    el.dataset.placed = "1";
  }

  function chip(label, count, bucket, level) {
    var colors = {
      fresh: "#065f46",
      attention: "#92400e",
      warn: "#9a3412",
      critical: "#7f1d1d",
    };
    var bg = {
      fresh: "#d1fae5",
      attention: "#fef3c7",
      warn: "#ffedd5",
      critical: "#fee2e2",
    };
    var c = colors[level] || "#78350f";
    var b = bg[level] || "#fde68a";
    var href = adminPrefix() + "/#/orders?paidAge=" + encodeURIComponent(bucket);
    return (
      '<a href="' +
      href +
      '" data-paid-age="' +
      bucket +
      '" style="display:inline-flex;align-items:center;gap:8px;margin:4px 8px 4px 0;padding:8px 12px;' +
      "border-radius:8px;background:" +
      b +
      ";color:" +
      c +
      ";text-decoration:none;font-weight:600;border:1px solid rgba(0,0,0,.06)\">" +
      "<span>" +
      label +
      '</span><span style="min-width:1.5em;text-align:center;background:rgba(255,255,255,.7);padding:2px 8px;border-radius:999px">' +
      count +
      "</span></a>"
    );
  }

  function applyHashFilter(bucket) {
    try {
      var url = new URL(w.location.href);
      // Vue hash routers often ignore search; also push query for API clients.
      if (w.history && w.history.replaceState) {
        var qs = "paidAge=" + encodeURIComponent(bucket);
        var hash = (w.location.hash || "#/orders").split("?")[0] + "?" + qs;
        w.history.replaceState(null, "", w.location.pathname + w.location.search + hash);
      }
      // Soft hint for operators: refetch if SPA listens to hashchange.
      w.dispatchEvent(new Event("hashchange"));
    } catch (e) {}
  }

  function render(summary) {
    var el = panelEl();
    place(el);
    var total = summary.total || 0;
    var g1 = summary.gt1h || 0;
    var g6 = summary.gt6h || 0;
    var g24 = summary.gt24h || 0;
    el.innerHTML =
      "<div style=\"font-weight:700;margin-bottom:6px\">PAID queue — settlement required</div>" +
      "<div style=\"color:#92400e;margin-bottom:8px\">Payment confirmed. Age since payment. No SSH needed.</div>" +
      '<div style="display:flex;flex-wrap:wrap">' +
      chip("PAID", total, "paid", total > 0 ? "attention" : "fresh") +
      chip("PAID > 1h", g1, "gt1h", g1 > 0 ? "attention" : "fresh") +
      chip("PAID > 6h", g6, "gt6h", g6 > 0 ? "warn" : "fresh") +
      chip("PAID > 24h", g24, "gt24h", g24 > 0 ? "critical" : "fresh") +
      "</div>" +
      '<div style="margin-top:8px;font-size:12px;color:#a16207">Filter API: paidAge=paid|gt1h|gt6h|gt24h · Reject on PAID is blocked — use Needs review / Freeze</div>';

    el.querySelectorAll("[data-paid-age]").forEach(function (a) {
      a.addEventListener("click", function (ev) {
        ev.preventDefault();
        applyHashFilter(a.getAttribute("data-paid-age"));
        // Also try classic list query if present
        try {
          var apiBase = adminPrefix();
          w.__exsPaidAgeFilter = a.getAttribute("data-paid-age");
          w.dispatchEvent(
            new CustomEvent("exs-paid-age-filter", {
              detail: { paidAge: a.getAttribute("data-paid-age") },
            })
          );
        } catch (e) {}
      });
    });
  }

  function fetchSummary() {
    var url = adminPrefix() + "/vue/orders/live";
    // Prefer live endpoint paid_queue; fall back to dedicated counts if absent.
    return fetch(url, {
      credentials: "same-origin",
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    })
      .then(function (r) {
        return r.ok ? r.json() : Promise.reject(r.status);
      })
      .then(function (data) {
        if (data && data.paid_queue) return data.paid_queue;
        return { total: 0, gt1h: 0, gt6h: 0, gt24h: 0 };
      })
      .catch(function () {
        return null;
      });
  }

  function tick() {
    if (!onOrdersSurface()) {
      var existing = d.getElementById("exs-paid-queue-panel");
      if (existing && existing.parentNode) existing.parentNode.removeChild(existing);
      return;
    }
    fetchSummary().then(function (summary) {
      if (!summary) return;
      render(summary);
    });
  }

  // Patch XHR/fetch query injection for orders list when filter set.
  (function patchFetch() {
    if (!w.fetch || w.__exsPaidFetchPatched) return;
    w.__exsPaidFetchPatched = true;
    var orig = w.fetch.bind(w);
    w.fetch = function (input, init) {
      try {
        var bucket = w.__exsPaidAgeFilter;
        if (bucket && typeof input === "string" && /orders/i.test(input) && !/paidAge=/i.test(input)) {
          input += (input.indexOf("?") >= 0 ? "&" : "?") + "paidAge=" + encodeURIComponent(bucket);
        }
      } catch (e) {}
      return orig(input, init);
    };
  })();

  tick();
  w.setInterval(tick, 30000);
  w.addEventListener("hashchange", function () {
    setTimeout(tick, 200);
  });
})(window, document);
