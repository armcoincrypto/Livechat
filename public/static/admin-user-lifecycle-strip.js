/**
 * Wave E — read-only account lifecycle strip for admin users surfaces.
 * Shows deactivated / banned / email verified / last login when API fields exist.
 * No mass-reactivate controls.
 */
(function (w, d) {
  'use strict';
  if (w.__exsUserLifecycleStrip) return;
  w.__exsUserLifecycleStrip = true;

  function adminPrefix() {
    var cfg = w.adminConfig || {};
    var p = cfg.adminPath || cfg.admin_path || '';
    if (p) return String(p).replace(/\/$/, '');
    var m = (w.location.pathname || '').match(/^\/([^/]+)\//);
    return m ? '/' + m[1] : '/iexadmin';
  }

  function onUsersSurface() {
    var s = (w.location.pathname || '') + ' ' + (w.location.hash || '');
    return /users/i.test(s) && !/users\/\d+/i.test(s.replace(/#/, '/'));
  }

  function userIdFromHash() {
    var m = (w.location.hash || '').match(/users\/(\d+)/i)
      || (w.location.pathname || '').match(/users\/(\d+)/i);
    return m ? m[1] : null;
  }

  function panelEl() {
    var el = d.getElementById('exs-user-lifecycle-panel');
    if (el) return el;
    el = d.createElement('div');
    el.id = 'exs-user-lifecycle-panel';
    el.setAttribute('style',
      'margin:10px 0 14px;padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;' +
      'background:#f8fafc;color:#0f172a;font:13px/1.45 ui-sans-serif,system-ui,sans-serif');
    return el;
  }

  function place(el) {
    if (el.parentNode && el.dataset.placed === '1') return;
    var app = d.getElementById('app') || d.body;
    var first = app.firstChild;
    if (first) app.insertBefore(el, first);
    else app.appendChild(el);
    el.dataset.placed = '1';
  }

  function pill(label, ok, warn) {
    var bg = ok ? '#dcfce7' : (warn ? '#fee2e2' : '#e2e8f0');
    var fg = ok ? '#166534' : (warn ? '#991b1b' : '#334155');
    return '<span style="display:inline-flex;margin:2px 6px 2px 0;padding:4px 8px;border-radius:999px;background:' +
      bg + ';color:' + fg + ';font-weight:600;font-size:12px">' + label + '</span>';
  }

  function renderListHint() {
    var el = panelEl();
    place(el);
    el.innerHTML =
      '<div style="font-weight:700;margin-bottom:4px">Account lifecycle (read-only)</div>' +
      '<div style="color:#475569;font-size:12px">API now exposes last login, email verified, deactivated, banned, and order history flags. Open a user for the strip details. No mass reactivation.</div>';
  }

  function renderDetail(attrs) {
    var el = panelEl();
    place(el);
    var deactivated = !!attrs.is_deactivated;
    var banned = !!attrs.is_banned;
    var verified = !!attrs.is_email_verified;
    var login = attrs.last_login_at_human || attrs.last_login_at || 'never';
    var orders = attrs.has_order_history || ((attrs.order_num || 0) > 0);
    el.innerHTML =
      '<div style="font-weight:700;margin-bottom:6px">Account state</div>' +
      '<div>' +
        pill(deactivated ? 'Deactivated' : 'Active', !deactivated, deactivated) +
        pill(banned ? 'Banned' : 'Not banned', !banned, banned) +
        pill(verified ? 'Email verified' : 'Email unverified', verified, !verified) +
        pill(orders ? 'Has orders' : 'No orders', orders, false) +
      '</div>' +
      '<div style="margin-top:8px;color:#475569;font-size:12px">Last login: <strong>' + String(login) + '</strong></div>';
  }

  function fetchUser(id) {
    var urls = [
      adminPrefix() + '/vue/users/' + id,
      adminPrefix() + '/frontend-api/users/' + id,
      adminPrefix() + '/users/' + id
    ];
    var i = 0;
    function next() {
      if (i >= urls.length) return;
      var url = urls[i++];
      fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
        .then(function (data) {
          var attrs = (data && data.attributes) || (data && data.data && data.data.attributes) || data || {};
          renderDetail(attrs);
        })
        .catch(next);
    }
    next();
  }

  function tick() {
    var detailId = userIdFromHash();
    var onList = onUsersSurface();
    if (!onList && !detailId) {
      var existing = d.getElementById('exs-user-lifecycle-panel');
      if (existing && existing.parentNode) existing.parentNode.removeChild(existing);
      return;
    }
    if (detailId) fetchUser(detailId);
    else if (onList) renderListHint();
  }

  tick();
  w.setInterval(tick, 8000);
  w.addEventListener('hashchange', function () { setTimeout(tick, 150); });
})(window, document);
