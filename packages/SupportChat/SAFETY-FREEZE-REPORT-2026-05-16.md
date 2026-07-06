# Support Chat V2.4 — Safety Freeze Report

**Date:** 2026-05-16  
**Mode:** Monitor / verify only (no production changes applied)  
**Stable version:** Widget **V2.4** — persistent visitor attachment messages (P4.1)

---

## 1. Public exposure

| URL (app.exswaping.com/support-chat/) | HTTP | Notes |
|--------------------------------------|------|-------|
| `widget.js` | **200** | Production artifact (~71 KB) |
| `widget.js.stable-v24.bak` | **403** | Denied |
| All `widget.js.pre-*.bak` (12 files) | **403** | Denied |
| `widget.js.20260516_002845.bak` | **403** | Denied (renamed from leaky name) |
| `widget.js.bak.20260516_002845` | **200** | **Not a file** — Laravel JSON fallback for unknown path (`content-type: application/json`). No backup content served. |

**On-disk backups:** 13 files under `public/support-chat/`; only `widget.js` is the live widget.  
**Defense:** nginx `deny-sensitive-files.conf` + `public/support-chat/.htaccess` (whitelist `widget.js`).

`diff widget.js widget.js.stable-v24.bak` → **identical**  
`node --check widget.js` → **pass**

---

## 2. Support flow verification

| Flow | Status | Evidence |
|------|--------|----------|
| Visitor text → Telegram topic | **PASS** | `support-chat telegram: outbound visitor_message_sent` (e.g. msg 906 @ 19:08, ongoing today) |
| Telegram operator text → website | **PASS** | `forum_text_persisted` + `support_chat_operator_reply` (e.g. 20:49:30 thread 908) |
| Visitor PNG upload → refresh persists | **PASS (DB)** | Attachment **12** → message **910**, `metadata.kind=attachment`, `channel=website_attachment` @ 20:48:33 |
| Visitor PDF upload → refresh persists | **NOT VERIFIED (post-V2.4)** | No visitor PDF with `support_message_id` after V2.4 deploy; manual QA still required |
| Operator image → website card | **PASS** | Attachment **13** (operator JPEG) → message **912** @ 20:49:20, `forum_media_attachment_stored` |
| Operator PDF → website card | **PASS** | Attachment **14** (operator PDF) → message **914** @ 20:51:10 |
| `/status` `/close` `/reopen` | **CODE OK** | `SupportTelegramOperatorCommandService` — not exercised in today’s logs |

---

## 3. Diagnostics

| Check | Result |
|-------|--------|
| Recent support-chat fatals | **None after 05:37** today for webhook path |
| Telegram webhook errors | **23 total**, last @ **05:37:03** — type-hint bug (`SupportMessage` namespace); **no recurrence** after operator media enabled |
| Token/path/body in widget logs | **None** — `scDebug` gated; URLs redacted; no `scDebug(..., token/body/path)` |
| Attachment storage permission errors | **None** |
| `storage_write_failed` | **None** |
| `failed_jobs` (`SendSupport*` last 24h) | **0** |
| Attachment rejections | Expected `dangerous_mime` / test traffic only |

---

## 4. Database

| Metric | Value |
|--------|-------|
| Visitor attachments with `support_message_id IS NULL` (all time) | **10** (pre-V2.4 orphans — documented, no backfill) |
| Post-V2.4 visitor uploads linked | **1** (attachment id **12** → message **910**) |
| Duplicate visitor messages per attachment (7d) | **0** |
| Orphan uploads in last 24h | **10** (all before 20:48 V2.4 link); **1 linked** after |

---

## 5. Rollback commands

**Widget only:**
```bash
cp public/support-chat/widget.js.stable-v24.bak public/support-chat/widget.js
node --check public/support-chat/widget.js
```

**Full V2.4 (widget + attachment message linking):**
```bash
cp public/support-chat/widget.js.pre-message-persist.bak public/support-chat/widget.js
cp packages/SupportChat/Http/Controllers/Public/SupportAttachmentController.php.pre-message-link.bak \
   packages/SupportChat/Http/Controllers/Public/SupportAttachmentController.php
cp packages/SupportChat/Services/SupportChatService.php.pre-message-link.bak \
   packages/SupportChat/Services/SupportChatService.php
```

Do **not** run `php artisan config:cache`.

---

## 6. Backup paths (`public/support-chat/`)

- `widget.js` — production
- `widget.js.stable-v24.bak` — V2.4 freeze snapshot
- `widget.js.pre-message-persist.bak` — pre attachment-message linking
- `widget.js.pre-debug-pass.bak` through `widget.js.pre-p41-ui-fix.bak` — incremental rollback chain
- `widget.js.20260516_002845.bak` — early snapshot

**Do not delete** until retention approval.

---

## 7. Open risks

1. **Manual QA gap:** Post-V2.4 visitor **PDF** refresh not confirmed in DB (only PNG id 12 verified).
2. **Historical orphans:** 10 visitor attachments without messages — invisible after refresh until backfill (optional).
3. **Telegram inbound type-hint:** 23 errors 05:13–05:37; dormant since media path fixed — separate fix if media regressions return.
4. **Unknown paths under `/support-chat/*`:** Non-file URLs may return Laravel JSON 200 (not backup leakage).

---

## 8. Next allowed actions (freeze)

| Allowed | Not allowed (without new approval) |
|---------|-------------------------------------|
| Monitor logs / failed_jobs | New features, UI redesign |
| Manual PNG/PDF refresh QA | Backend changes (except critical hotfix) |
| Opt-in `data-sc-debug="1"` troubleshooting | Backup deletion |
| Orphan backfill (planned change) | `config:cache` |
| Move backups outside `public/` (hardening) | Telegram/nginx/payment changes |

---

## 9. Freeze verdict

**SAFE TO HOLD V2.4** with conditions:

- Production widget matches stable backup.
- On-disk backups are HTTP-denied.
- Post-V2.4 visitor attachment linking confirmed for at least one PNG upload.
- Complete freeze sign-off after one manual visitor **PDF** upload + hard refresh test.
