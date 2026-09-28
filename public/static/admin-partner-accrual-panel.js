/**
 * Wave C — Partner Monthly Accrual admin panel (user detail).
 * Backend-owned money; preview/create/pause/resume/cancel via API.
 * Feature-flag fail-closed (503 FEATURE_DISABLED).
 */
(function (w, d) {
  'use strict';
  if (w.__exsPartnerAccrualPanel) return;
  w.__exsPartnerAccrualPanel = true;

  /** Bound preview state — invalidated when operator edits inputs. */
  var previewState = null; // { seed, fingerprint, exact_sum_ok }
  var lastUserId = null;
  var lastSummary = null;
  var loadInFlight = null;

  function formFingerprint() {
    var budget = (d.getElementById('exs-pa-budget') || {}).value || '';
    // Period is server-derived (NEXT_FULL_CALENDAR_MONTH); amount is the only operator input.
    return ['USD', String(budget).trim()].join('|');
  }

  function invalidatePreview(reason) {
    previewState = null;
    var createBtn = d.getElementById('exs-pa-create');
    if (createBtn) createBtn.disabled = true;
    var box = d.getElementById('exs-pa-preview');
    if (box && reason) {
      box.innerHTML = '<span style="color:#9a3412">' + reason + '</span>';
    }
  }


  function adminPrefix() {
    var cfg = w.adminConfig || {};
    var p = cfg.adminPath || cfg.admin_path || '';
    if (p) return String(p).replace(/\/$/, '');
    var m = (w.location.pathname || '').match(/^\/([^/]+)\//);
    return m ? '/' + m[1] : '/iexadmin';
  }

  /**
   * Canonical iEX admin mutation CSRF (matches Vue Axios withXSRFToken):
   * prefer fresh XSRF-TOKEN cookie → X-XSRF-TOKEN (URL-decoded).
   * Meta csrf-token is rendered once with the SPA shell and goes stale after
   * session/token rotation while the Dialog stays open — that caused Preview/Create 419.
   * Do not send stale X-CSRF-TOKEN alongside a valid X-XSRF-TOKEN: Laravel prefers X-CSRF-TOKEN.
   */
  function applyCsrfHeaders(headers) {
    try {
      var parts = (d.cookie || '').split('; ');
      var i;
      for (i = 0; i < parts.length; i++) {
        if (parts[i].indexOf('XSRF-TOKEN=') === 0) {
          headers['X-XSRF-TOKEN'] = decodeURIComponent(parts[i].slice('XSRF-TOKEN='.length));
          return headers;
        }
      }
    } catch (e) {}
    var m = d.querySelector('meta[name="csrf-token"]');
    var meta = m ? m.getAttribute('content') : '';
    if (meta) headers['X-CSRF-TOKEN'] = meta;
    return headers;
  }

  function userIdFromLocation() {
    // Dedicated deep-link: /users/436 or #/.../users/436
    var m = (w.location.hash || '').match(/users\/(\d+)/i)
      || (w.location.pathname || '').match(/users\/(\d+)/i);
    if (m) return m[1];
    // Vue users list opens the editor as a right Dialog via ?edit_user=<id>
    // (see users-XtT9HQo8.js). Without this, the panel never mounts on the
    // standard "Редактирование пользователя" surface.
    try {
      var q = new URLSearchParams(w.location.search || '');
      if (q.get('edit_user')) return String(q.get('edit_user'));
      var hash = w.location.hash || '';
      var qi = hash.indexOf('?');
      if (qi >= 0) {
        var hq = new URLSearchParams(hash.slice(qi + 1));
        if (hq.get('edit_user')) return String(hq.get('edit_user'));
      }
    } catch (e) {}
    return null;
  }

  function panelEl() {
    var el = d.getElementById('exs-partner-accrual-panel');
    if (el) return el;
    el = d.createElement('section');
    el.id = 'exs-partner-accrual-panel';
    el.setAttribute('aria-label', 'Partner Monthly Accrual');
    el.setAttribute('style',
      'margin:12px 0 16px;padding:14px 16px;border:1px solid #94a3b8;border-radius:10px;' +
      'background:#fff7ed;color:#0f172a;font:13px/1.45 ui-sans-serif,system-ui,sans-serif');
    return el;
  }

  function openedFromEditUserQuery() {
    try {
      if (new URLSearchParams(w.location.search || '').get('edit_user')) return true;
      var hash = w.location.hash || '';
      var qi = hash.indexOf('?');
      if (qi >= 0 && new URLSearchParams(hash.slice(qi + 1)).get('edit_user')) return true;
    } catch (e) {}
    return false;
  }

  function dialogContentEl() {
    var nodes = d.querySelectorAll('.p-dialog .p-dialog-content, .p-drawer .p-drawer-content');
    var i;
    for (i = 0; i < nodes.length; i++) {
      var n = nodes[i];
      if (n.offsetParent !== null || (n.getClientRects && n.getClientRects().length)) return n;
    }
    return nodes.length ? nodes[nodes.length - 1] : null;
  }

  function place(el) {
    // Vue user editor is a right Dialog. Do not pin the panel to #app first —
    // that leaves it outside (and behind) the modal. Re-place on every tick.
    if (openedFromEditUserQuery()) {
      var dialogContent = dialogContentEl();
      if (!dialogContent) {
        el.style.display = 'none';
        return;
      }
      if (el.parentNode !== dialogContent) {
        dialogContent.insertBefore(el, dialogContent.firstChild);
      }
      el.style.display = '';
      el.dataset.placed = '1';
      return;
    }
    var app = d.getElementById('app') || d.body;
    var anchor = d.getElementById('exs-user-lifecycle-panel');
    var targetParent;
    var beforeNode = null;
    if (anchor && anchor.parentNode) {
      targetParent = anchor.parentNode;
      beforeNode = anchor.nextSibling;
    } else {
      targetParent = app;
      beforeNode = app.firstChild;
    }
    if (el.parentNode !== targetParent) {
      if (beforeNode && beforeNode.parentNode === targetParent) targetParent.insertBefore(el, beforeNode);
      else if (targetParent.firstChild) targetParent.insertBefore(el, targetParent.firstChild);
      else targetParent.appendChild(el);
    }
    el.style.display = '';
    el.dataset.placed = '1';
  }

  function money(v) {
    if (v === null || v === undefined || v === '') return '—';
    var n = Number(String(v).replace(',', '.'));
    if (!isFinite(n)) return String(v);
    return n.toFixed(2);
  }

  function api(method, path, body) {
    var opts = {
      method: method,
      credentials: 'same-origin',
      headers: applyCsrfHeaders({
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      })
    };
    if (body !== undefined) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    return fetch(adminPrefix() + '/frontend-api' + path, opts).then(function (r) {
      return r.json().then(function (j) { return { http: r.status, body: j }; });
    });
  }

  function renderDisabled(el, msg) {
    el.innerHTML =
      '<div style="font-weight:700;margin-bottom:6px">Ежемесячное партнёрское начисление</div>' +
      '<div style="color:#9a3412">' + (msg || 'Функция отключена (PARTNER_ACCRUAL_ENABLED=false)') + '</div>';
  }

  function renderSummary(el, data, userId) {
    // Keep amount-only preview across Dialog remounts for the same user.
    // Cleared explicitly on user change / invalidatePreview / successful create reload.
    var plan = data.plan;
    var elig = data.user_eligibility || {};
    var bal = data.current_partner_balance || {};
    var html = '';
    html += '<div style="font-weight:700;margin-bottom:8px">Ежемесячное партнёрское начисление (USD)</div>';
    html += '<div style="margin-bottom:8px;color:#475569;font-size:12px">' +
      'Текущий партнёрский баланс: <b>' + money(bal.amount_display || bal.amount) + ' USD</b> — ' +
      'не равен сумме плана. Ручные изменения баланса не меняют расписание начисления.</div>';
    html += '<div style="margin-bottom:8px">Статус пользователя: <b>' + (elig.label || '—') + '</b></div>';

    if (!plan) {
      html += '<div style="margin-bottom:10px;color:#334155">Нет активного ежемесячного начисления</div>';
    } else {
      var recon = plan.reconciliation || {};
      html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin-bottom:10px">';
      html += cell('Статус', plan.status);
      html += cell('Валюта', plan.currency_code);
      html += cell('Бюджет', money(plan.total_display) + ' USD');
      html += cell('Начислено', money(plan.credited_display) + ' USD');
      html += cell('Остаток', money(plan.remaining_display) + ' USD');
      html += cell('Период', (plan.start_date || '') + ' → ' + (plan.end_date || ''));
      html += cell('Прогресс', (plan.progress_percent || '0') + '%');
      html += cell('Просрочено', String(plan.overdue_installments || 0));
      html += '</div>';
      if (plan.next_installment) {
        html += '<div style="margin-bottom:8px">Следующее: ' + plan.next_installment.scheduled_for +
          ' / ' + money(plan.next_installment.amount_display) + ' USD</div>';
      }
      html += '<div style="margin-bottom:10px;padding:8px;border-radius:8px;background:' +
        (recon.ok ? '#dcfce7' : '#fee2e2') + '">Сверка: <b>' + (recon.label || recon.status) + '</b></div>';
      html += '<div style="margin-bottom:10px">' +
        '<button type="button" data-act="detail" style="' + btn() + '">Расписание</button> ' +
        '<button type="button" data-act="dryrun" style="' + btn() + '">Dry-run (без изменений)</button> ' +
        (plan.status === 'ACTIVE' ? '<button type="button" data-act="pause" style="' + btnWarn() + '">Пауза</button> ' : '') +
        (plan.status === 'PAUSED' ? '<button type="button" data-act="resume" style="' + btn() + '">Возобновить</button> ' : '') +
        ((plan.status === 'ACTIVE' || plan.status === 'PAUSED')
          ? '<button type="button" data-act="cancel" style="' + btnDanger() + '">Отменить</button>' : '') +
        '</div>';
      html += '<div id="exs-pa-detail" style="display:none;margin-top:8px;max-height:280px;overflow:auto;border:1px solid #e2e8f0;padding:8px"></div>';
    }

    if (elig.eligible && (!plan || plan.status === 'CANCELLED' || plan.status === 'COMPLETED')) {
      html += createFormHtml();
    } else if (!elig.eligible) {
      html += '<div style="color:#991b1b;margin-top:8px">Начисление недоступно для заблокированных или деактивированных пользователей. Аккаунт не реактивируется.</div>';
    }

    html += '<div id="exs-pa-msg" style="margin-top:8px;color:#9a3412"></div>';
    el.innerHTML = html;
    bind(el, userId, plan);
    restorePreviewUi();
  }

  function restorePreviewUi() {
    if (!previewState || !previewState.seed) return;
    var parts = String(previewState.fingerprint || '').split('|');
    var budget = d.getElementById('exs-pa-budget');
    if (budget && parts.length > 1 && parts[1]) budget.value = parts[1];
    var hint = d.getElementById('exs-pa-period-hint');
    if (hint && previewState.start_date) {
      hint.innerHTML =
        '<b>Период начисления:</b> ' + formatRuDate(previewState.start_date) + ' – ' + formatRuDate(previewState.end_date);
    }
    var box = d.getElementById('exs-pa-preview');
    if (box && previewState.html) box.innerHTML = previewState.html;
    var createBtn = d.getElementById('exs-pa-create');
    if (createBtn) createBtn.disabled = !previewState.exact_sum_ok;
    if (previewState.msg) setMsg(previewState.msg);
  }

  function cell(k, v) {
    return '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:8px"><div style="font-size:11px;color:#64748b">' +
      k + '</div><div style="font-weight:600">' + v + '</div></div>';
  }

  function btn() {
    return 'cursor:pointer;padding:6px 10px;border-radius:8px;border:1px solid #cbd5e1;background:#f8fafc;font-weight:600';
  }
  function btnWarn() {
    return 'cursor:pointer;padding:6px 10px;border-radius:8px;border:1px solid #f59e0b;background:#fffbeb;font-weight:600';
  }
  function btnDanger() {
    return 'cursor:pointer;padding:6px 10px;border-radius:8px;border:1px solid #ef4444;background:#fef2f2;font-weight:600;color:#991b1b';
  }

  function formatRuDate(iso) {
    if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return iso || '—';
    var p = iso.split('-');
    return p[2] + '.' + p[1] + '.' + p[0];
  }

  function createFormHtml() {
    return '<div style="margin-top:12px;padding-top:12px;border-top:1px dashed #fdba74">' +
      '<div style="font-weight:700;margin-bottom:6px">Создать ежемесячное начисление</div>' +
      '<div style="margin-bottom:8px">' +
      '<label style="display:inline-flex;align-items:center;gap:6px">Месячный бюджет ' +
      '<input id="exs-pa-budget" type="text" inputmode="decimal" placeholder="300.00" style="width:110px">' +
      '<span style="color:#64748b;font-weight:600">USD</span></label></div>' +
      '<div id="exs-pa-period-hint" style="margin-bottom:8px;color:#475569;font-size:12px">' +
      'Период будет выбран автоматически:<br>следующий полный календарный месяц</div>' +
      '<button type="button" data-act="preview" style="' + btn() + '">Предпросмотр</button> ' +
      '<button type="button" data-act="create" style="' + btn() + '" disabled id="exs-pa-create">Подтвердить</button>' +
      '<div id="exs-pa-preview" style="margin-top:8px;font-size:12px;color:#334155"></div>' +
      '</div>';
  }

  function setMsg(text) {
    var m = d.getElementById('exs-pa-msg');
    if (m) m.textContent = text || '';
  }

  function bind(el, userId, plan) {
    el.onclick = function (ev) {
      var t = ev.target;
      if (!t || !t.getAttribute) return;
      var act = t.getAttribute('data-act');
      if (!act) return;
      if (act === 'preview') doPreview(userId);
      if (act === 'create') doCreate(userId);
      if (act === 'detail' && plan) doDetail(plan.id);
      if (act === 'dryrun') doDryRun(userId);
      if (act === 'pause' && plan) {
        if (!w.confirm('Приостановить будущие начисления? Уже начисленные средства не отменяются.')) return;
        mutate('POST', '/account/users/partner-accrual/plans/' + plan.id + '/pause', {}, userId);
      }
      if (act === 'resume' && plan) {
        if (!w.confirm('Возобновить план? Расписание не пересчитывается; пропущенные дни могут доначислиться после активации планировщика.')) return;
        mutate('POST', '/account/users/partner-accrual/plans/' + plan.id + '/resume', {}, userId);
      }
      if (act === 'cancel' && plan) {
        if (!w.confirm('Отменить все будущие неначисленные платежи? Уже начисленные средства остаются на балансе.')) return;
        if (!w.confirm('Подтвердите отмену ещё раз.')) return;
        mutate('POST', '/account/users/partner-accrual/plans/' + plan.id + '/cancel', { confirm: 'CANCEL' }, userId);
      }
    };
    ['exs-pa-budget'].forEach(function (id) {
      var node = d.getElementById(id);
      if (!node || node.dataset.staleBound === '1') return;
      node.dataset.staleBound = '1';
      node.addEventListener('input', function () {
        if (previewState) invalidatePreview('Параметры изменились — нужен новый предпросмотр');
      });
      node.addEventListener('change', function () {
        if (previewState) invalidatePreview('Параметры изменились — нужен новый предпросмотр');
      });
    });
  }

  function doPreview(userId) {
    var budget = (d.getElementById('exs-pa-budget') || {}).value;
    if (!String(budget || '').trim()) {
      setMsg('Укажите месячный бюджет');
      return;
    }
    api('POST', '/account/users/' + userId + '/partner-accrual/preview', {
      total_amount: budget,
      currency_code: 'USD'
    }).then(function (res) {
      var box = d.getElementById('exs-pa-preview');
      var createBtn = d.getElementById('exs-pa-create');
      var hint = d.getElementById('exs-pa-period-hint');
      if (res.http === 503) {
        setMsg(res.body.message || 'FEATURE_DISABLED');
        return;
      }
      if (res.body.status !== 0) {
        setMsg(res.body.message || res.body.code || 'Ошибка предпросмотра');
        if (createBtn) createBtn.disabled = true;
        return;
      }
      var p = res.body.data;
      if (!p.schedule_seed) {
        previewState = null;
        if (createBtn) createBtn.disabled = true;
        setMsg('Предпросмотр без schedule_seed — создание запрещено');
        return;
      }
      previewState = {
        seed: String(p.schedule_seed),
        fingerprint: formFingerprint(),
        exact_sum_ok: !!p.exact_sum_ok,
        start_date: p.start_date || null,
        end_date: p.end_date || null,
        html: null,
        msg: null
      };
      if (hint) {
        hint.innerHTML =
          '<b>Период начисления:</b> ' + formatRuDate(p.start_date) + ' – ' + formatRuDate(p.end_date);
      }
      var rows = (p.schedule || []).slice(0, 8).map(function (r) {
        return r.scheduled_for + ': ' + money(r.amount_display);
      }).join('<br>');
      var previewHtml =
          '<div style="margin-bottom:4px"><b>Период:</b> ' + formatRuDate(p.start_date) + ' – ' + formatRuDate(p.end_date) + '</div>' +
          '<div><b>Количество начислений:</b> ' + p.installment_count + '</div>' +
          '<div><b>Итог:</b> ' + money(p.sum_display) + ' USD</div>' +
          '<div style="margin-top:4px;color:#64748b">Средн./мин/макс: ' +
          money(p.average_display) + ' / ' + money(p.min_display) + ' / ' + money(p.max_display) + '</div>' +
          '<div style="margin-top:6px">' + rows + (p.schedule && p.schedule.length > 8 ? '<br>…' : '') + '</div>';
      if (box) box.innerHTML = previewHtml;
      previewState.html = previewHtml;
      if (createBtn) createBtn.disabled = !p.exact_sum_ok;
      previewState.msg = p.exact_sum_ok ? 'Предпросмотр готов. Сумма точна. Можно подтвердить.' : 'Сумма не совпадает';
      setMsg(previewState.msg);
    }).catch(function () { setMsg('Сеть/ошибка предпросмотра'); });
  }

  function doCreate(userId) {
    if (!previewState || !previewState.seed) {
      setMsg('Сначала выполните предпросмотр');
      return;
    }
    if (previewState.fingerprint !== formFingerprint()) {
      invalidatePreview('Параметры изменились — нужен новый предпросмотр');
      setMsg('Устаревший предпросмотр. Сделайте Preview снова.');
      return;
    }
    if (!previewState.exact_sum_ok) {
      setMsg('Предпросмотр с неточной суммой нельзя подтвердить');
      return;
    }
    if (!w.confirm('Создать активный план с ТОЧНО этим предпросмотренным расписанием?')) return;
    var budget = (d.getElementById('exs-pa-budget') || {}).value;
    var seed = previewState.seed;
    // Prevent double-submit with same UI state.
    var createBtn = d.getElementById('exs-pa-create');
    if (createBtn) createBtn.disabled = true;
    // Period is server-authoritative; do not send operator dates.
    mutate('POST', '/account/users/' + userId + '/partner-accrual', {
      total_amount: budget,
      schedule_seed: seed
    }, userId);
  }

  function doDetail(planId) {
    api('GET', '/account/users/partner-accrual/plans/' + planId).then(function (res) {
      var box = d.getElementById('exs-pa-detail');
      if (!box) return;
      if (res.body.status !== 0) {
        box.style.display = 'block';
        box.textContent = res.body.message || 'Ошибка';
        return;
      }
      var rows = (res.body.data.installments || []).map(function (i) {
        return i.sequence + '. ' + i.scheduled_for + ' — ' + money(i.amount_display) + ' USD [' + i.status + ']';
      }).join('<br>');
      box.style.display = 'block';
      box.innerHTML = '<div style="font-weight:600;margin-bottom:4px">Сохранённое расписание (без перегенерации)</div>' + rows;
    });
  }

  function doDryRun(userId) {
    api('GET', '/account/users/' + userId + '/partner-accrual/dry-run').then(function (res) {
      if (res.body.status !== 0) {
        setMsg(res.body.message || 'Dry-run ошибка');
        return;
      }
      var data = res.body.data;
      setMsg('Dry-run — без изменений баланса. Due=' + data.due_installments +
        '; would_credit=' + data.would_credit_count + ' / ' + money(data.would_credit_total) + ' USD');
    });
  }

  function mutate(method, path, body, userId) {
    api(method, path, body).then(function (res) {
      if (res.http === 503) {
        setMsg(res.body.message || 'FEATURE_DISABLED');
        return;
      }
      if (res.body.status !== 0) {
        setMsg(res.body.message || res.body.code || 'Ошибка');
        return;
      }
      setMsg(res.body.message || 'OK');
      load(userId);
    }).catch(function () { setMsg('Сеть/ошибка'); });
  }

  function load(userId) {
    var el = panelEl();
    place(el);
    if (String(lastUserId) !== String(userId)) {
      previewState = null;
    }
    if (lastSummary && String(lastUserId) === String(userId)) {
      renderSummary(el, lastSummary, userId);
      return;
    }
    if (loadInFlight === String(userId)) {
      el.innerHTML = '<div style="font-weight:700">Ежемесячное партнёрское начисление</div><div>Загрузка…</div>';
      return;
    }
    loadInFlight = String(userId);
    el.innerHTML = '<div style="font-weight:700">Ежемесячное партнёрское начисление</div><div>Загрузка…</div>';
    api('GET', '/account/users/' + userId + '/partner-accrual').then(function (res) {
      loadInFlight = null;
      var node = panelEl();
      place(node);
      if (res.http === 503 || (res.body && res.body.code === 'FEATURE_DISABLED')) {
        renderDisabled(node, res.body && res.body.message);
        return;
      }
      if (res.http === 403) {
        renderDisabled(node, 'Недостаточно прав');
        return;
      }
      if (res.body.status !== 0) {
        renderDisabled(node, res.body.message || 'Ошибка');
        return;
      }
      lastUserId = String(userId);
      lastSummary = res.body.data;
      renderSummary(node, lastSummary, userId);
    }).catch(function () {
      loadInFlight = null;
      renderDisabled(panelEl(), 'Не удалось загрузить блок начисления');
    });
  }

  function tick() {
    var id = userIdFromLocation();
    if (!id) {
      var hidden = d.getElementById('exs-partner-accrual-panel');
      if (hidden) hidden.style.display = 'none';
      return;
    }
    var el = panelEl();
    place(el);
    if (el.dataset.userId !== String(id)) {
      lastSummary = null;
      lastUserId = null;
      el.dataset.userId = String(id);
      load(id);
      return;
    }
    if (lastSummary && String(lastUserId) === String(id)) {
      if (el.innerHTML.indexOf('Загрузка') !== -1 || !el.innerText) {
        renderSummary(el, lastSummary, id);
      }
    }
  }

  w.addEventListener('hashchange', tick);
  w.addEventListener('popstate', tick);
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', tick);
  else tick();
  setInterval(tick, 800);
})(window, document);
