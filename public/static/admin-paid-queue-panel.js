/**
 * Wave A / Wave E — PAID operator queue panel for admin Vue SPA.
 * Injects age-bucket chips + counts without rebuilding Vue source.
 * Filters use existing orders list API: ?paidAge=paid|gt1h|gt6h|gt24h
 * Wave E: patch axios + XHR (SPA does not use window.fetch for list).
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

  function appendPaidAge(url, bucket) {
    if (!bucket || typeof url !== "string") return url;
    if (!/orders/i.test(url) || /paidAge=/i.test(url)) return url;
    // Avoid mutating unrelated admin routes that merely mention "orders" in hash fragments.
    if (!/\/(vue\/)?orders|frontend-api\/.*order|live/i.test(url) && !/[?&](page|status|filter)=/i.test(url)) {
      if (!/order/i.test(url)) return url;
    }
    return url + (url.indexOf("?") >= 0 ? "&" : "?") + "paidAge=" + encodeURIComponent(bucket);
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
    var active = w.__exsPaidAgeFilter === bucket;
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
      ";text-decoration:none;font-weight:600;border:" +
      (active ? "2px solid " + c : "1px solid rgba(0,0,0,.06)") +
      '">' +
      "<span>" +
      label +
      '</span><span style="min-width:1.5em;text-align:center;background:rgba(255,255,255,.7);padding:2px 8px;border-radius:999px">' +
      count +
      "</span></a>"
    );
  }

  function applyHashFilter(bucket) {
    try {
      if (w.history && w.history.replaceState) {
        var qs = "paidAge=" + encodeURIComponent(bucket);
        var hash = (w.location.hash || "#/orders").split("?")[0] + "?" + qs;
        w.history.replaceState(null, "", w.location.pathname + w.location.search + hash);
      }
      w.dispatchEvent(new Event("hashchange"));
    } catch (e) {}
  }

  function triggerListRefresh() {
    try {
      // Soft remount signal for hash routers + any listeners.
      w.dispatchEvent(new Event("hashchange"));
      w.dispatchEvent(
        new CustomEvent("exs-paid-age-filter", {
          detail: { paidAge: w.__exsPaidAgeFilter },
        })
      );
      // Prefer clicking an existing refresh/reload control if present.
      var btn =
        d.querySelector('[data-action="refresh"]') ||
        d.querySelector('button[aria-label*="обнов" i]') ||
        d.querySelector('button[title*="обнов" i]') ||
        d.querySelector('button[aria-label*="refresh" i]');
      if (btn && typeof btn.click === "function") {
        btn.click();
        return;
      }
      // Last resort: nudge hash so Vue re-queries with patched transport.
      var base = (w.location.hash || "#/orders").split("?")[0] || "#/orders";
      var bucket = w.__exsPaidAgeFilter || "paid";
      w.location.hash = base + "?paidAge=" + encodeURIComponent(bucket) + "&_ts=" + Date.now();
    } catch (e) {}
  }

  function render(summary) {
    var el = panelEl();
    place(el);
    var total = summary.total || 0;
    var g1 = summary.gt1h || 0;
    var g6 = summary.gt6h || 0;
    var g24 = summary.gt24h || 0;
    var active = w.__exsPaidAgeFilter || "";
    el.innerHTML =
      "<div style=\"font-weight:700;margin-bottom:6px\">PAID queue — settlement required</div>" +
      "<div style=\"color:#92400e;margin-bottom:8px\">Payment confirmed. Age since payment. No SSH needed.</div>" +
      '<div style="display:flex;flex-wrap:wrap">' +
      chip("PAID", total, "paid", total > 0 ? "attention" : "fresh") +
      chip("PAID > 1h", g1, "gt1h", g1 > 0 ? "attention" : "fresh") +
      chip("PAID > 6h", g6, "gt6h", g6 > 0 ? "warn" : "fresh") +
      chip("PAID > 24h", g24, "gt24h", g24 > 0 ? "critical" : "fresh") +
      (active
        ? '<button type="button" data-paid-age-clear="1" style="margin:4px 0;padding:8px 12px;border-radius:8px;border:1px solid #d6d3d1;background:#fff;color:#78716c;font-weight:600;cursor:pointer">Clear age filter</button>'
        : "") +
      "</div>" +
      '<div style="margin-top:8px;font-size:12px;color:#a16207">' +
      (active ? "Active filter: paidAge=" + active + " · " : "") +
      "Reject on PAID is blocked — use Needs review / Freeze</div>";

    el.querySelectorAll("[data-paid-age]").forEach(function (a) {
      a.addEventListener("click", function (ev) {
        ev.preventDefault();
        var bucket = a.getAttribute("data-paid-age");
        w.__exsPaidAgeFilter = bucket;
        applyHashFilter(bucket);
        triggerListRefresh();
        render(summary);
      });
    });
    var clearBtn = el.querySelector("[data-paid-age-clear]");
    if (clearBtn) {
      clearBtn.addEventListener("click", function () {
        w.__exsPaidAgeFilter = null;
        try {
          var hash = (w.location.hash || "#/orders").split("?")[0];
          w.history.replaceState(null, "", w.location.pathname + w.location.search + hash);
        } catch (e) {}
        triggerListRefresh();
        render(summary);
      });
    }
  }

  function fetchSummary() {
    var url = adminPrefix() + "/vue/orders/live";
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

  // Restore filter from hash on load.
  try {
    var hm = (w.location.hash || "").match(/[?&]paidAge=([^&]+)/);
    if (hm) w.__exsPaidAgeFilter = decodeURIComponent(hm[1]);
  } catch (e) {}

  // Patch window.fetch (summary + any fetch-based clients).
  (function patchFetch() {
    if (!w.fetch || w.__exsPaidFetchPatched) return;
    w.__exsPaidFetchPatched = true;
    var orig = w.fetch.bind(w);
    w.fetch = function (input, init) {
      try {
        var bucket = w.__exsPaidAgeFilter;
        if (bucket && typeof input === "string") {
          input = appendPaidAge(input, bucket);
        } else if (bucket && input && typeof input.url === "string") {
          // Request object — fall through; XHR/axios cover SPA.
        }
      } catch (e) {}
      return orig(input, init);
    };
  })();

  // Patch XHR — Vue axios typically uses XMLHttpRequest.
  (function patchXhr() {
    if (w.__exsPaidXhrPatched || !w.XMLHttpRequest) return;
    w.__exsPaidXhrPatched = true;
    var open = w.XMLHttpRequest.prototype.open;
    w.XMLHttpRequest.prototype.open = function (method, url, async, user, password) {
      try {
        var bucket = w.__exsPaidAgeFilter;
        if (bucket && typeof url === "string") {
          url = appendPaidAge(url, bucket);
        }
      } catch (e) {}
      this.__exsPaidUrl = url;
      return open.call(this, method, url, async, user, password);
    };
  })();

  // Patch axios when/if it appears on window (some builds expose it).
  (function patchAxiosLoop() {
    var tries = 0;
    function attempt() {
      tries += 1;
      var ax = w.axios;
      if (ax && ax.interceptors && ax.interceptors.request && !ax.__exsPaidPatched) {
        ax.__exsPaidPatched = true;
        ax.interceptors.request.use(function (config) {
          try {
            var bucket = w.__exsPaidAgeFilter;
            if (!bucket || !config) return config;
            if (config.params && typeof config.params === "object") {
              if (!config.params.paidAge) config.params.paidAge = bucket;
            }
            if (typeof config.url === "string") {
              config.url = appendPaidAge(config.url, bucket);
            }
          } catch (e) {}
          return config;
        });
        return;
      }
      if (tries < 40) w.setTimeout(attempt, 250);
    }
    attempt();
  })();

  // Visual stale emphasis for PAID rows when SPA leaves aging fields in DOM text/attrs.
  (function injectAgingStyles() {
    if (d.getElementById("exs-paid-aging-style")) return;
    var style = d.createElement("style");
    style.id = "exs-paid-aging-style";
    style.textContent =
      '[data-paid-aging-level="warn"]{box-shadow:inset 3px 0 0 #ea580c}' +
      '[data-paid-aging-level="critical"]{box-shadow:inset 3px 0 0 #b91c1c;background:#fff7ed}' +
      '[data-paid-aging-level="attention"]{box-shadow:inset 3px 0 0 #ca8a04}';
    d.head.appendChild(style);
  })();

  tick();
  w.setInterval(tick, 30000);
  w.addEventListener("hashchange", function () {
    try {
      var hm = (w.location.hash || "").match(/[?&]paidAge=([^&]+)/);
      w.__exsPaidAgeFilter = hm ? decodeURIComponent(hm[1]) : w.__exsPaidAgeFilter;
    } catch (e) {}
    setTimeout(tick, 200);
  });
})(window, document);
