# Support Chat V2.4 — Production Runbook

Last reviewed: 2026-05-16

## Deployed artifact

| Item | Path / value |
|------|----------------|
| Widget (production) | `public/support-chat/widget.js` |
| Version header | `V2.4 — persistent visitor attachment messages (P4.1)` |
| Stable rollback copy | `public/support-chat/widget.js.stable-v24.bak` (HTTP denied) |
| Widget size | ~71 KB (~69 KiB) uncompressed |

## Production checklist

### Widget

- [ ] `node --check public/support-chat/widget.js` passes
- [ ] Header shows `V2.4`
- [ ] `diff widget.js widget.js.stable-v24.bak` is empty (or intentional delta documented)
- [ ] Only `https://app.exswaping.com/support-chat/widget.js` returns **200**
- [ ] All `*.bak` / `*.pre-*.bak` under `support-chat/` return **403** (nginx + `.htaccess`)
- [ ] No `data-sc-debug="1"` on production embed unless actively troubleshooting

### Visitor attachments (V2.4)

- [ ] Upload PNG → instant preview → hard refresh → attachment card persists
- [ ] Upload PDF → refresh → document card persists
- [ ] Download works with `Authorization: Bearer <access_token>`
- [ ] No duplicate visitor message rows per upload
- [ ] Telegram: one file forward per upload (no duplicate text notify for `metadata.kind=attachment`)

### Debug (opt-in only)

Enable temporarily:

```html
<script src="…/support-chat/widget.js" defer data-chat-enabled="1" data-sc-debug="1" …></script>
```

Or in console: `window.__EXSWAPING_SC_DEBUG = true` then reload.

- Logs are prefixed `[exswaping-sc]`
- Blob/http URLs are redacted (`blob:…`, `http:…`)
- Tokens, paths, and message bodies are **not** logged

### Backend (V2.4 message linking)

- `SupportAttachmentController::store` → `persistVisitorAttachmentMessage()` → sets `support_attachments.support_message_id`
- `GET /messages` includes attachment via `SupportMessageResource` (`download_url` only, no raw storage paths)
- `scheduleVisitorTelegramNotifyAfterCommitIfApplicable` skips `metadata.kind === 'attachment'`
- Telegram file job: `SendSupportAttachmentToTelegramJob` only

## Orphan attachments (pre-V2.4 uploads)

Rows with `support_message_id IS NULL` and `sender_type = visitor` were stored before message linking. They **do not** appear in chat history after refresh.

**New uploads (post-V2.4):** linked automatically.

**Optional backfill (not deployed):** one-off script/SQL to create visitor messages and set `support_message_id` for historical orphans. Run only after backup and count review.

## Rollback

### Widget only (V2.4 stable)

```bash
cp public/support-chat/widget.js.stable-v24.bak public/support-chat/widget.js
node --check public/support-chat/widget.js
```

### Full V2.4 attachment persistence rollback

```bash
cp public/support-chat/widget.js.pre-message-persist.bak public/support-chat/widget.js
cp packages/SupportChat/Http/Controllers/Public/SupportAttachmentController.php.pre-message-link.bak \
   packages/SupportChat/Http/Controllers/Public/SupportAttachmentController.php
cp packages/SupportChat/Services/SupportChatService.php.pre-message-link.bak \
   packages/SupportChat/Services/SupportChatService.php
php -l packages/SupportChat/Http/Controllers/Public/SupportAttachmentController.php
php -l packages/SupportChat/Services/SupportChatService.php
```

Do **not** run `php artisan config:cache`.

## Backup inventory (`public/support-chat/`)

| File | Role |
|------|------|
| `widget.js` | Production |
| `widget.js.stable-v24.bak` | V2.4 stable snapshot |
| `widget.js.pre-message-persist.bak` | Pre attachment-message linking |
| `widget.js.pre-debug-pass.bak` | Pre opt-in debug pass |
| `widget.js.pre-echo-revoke-fix.bak` | Pre blob revoke fix |
| `widget.js.pre-echo-preview.bak` | Pre echo preview |
| `widget.js.pre-upload-status.bak` | Pre upload status fix |
| `widget.js.pre-scroll-visual.bak` | Pre scroll stability |
| `widget.js.pre-v22-tighten.bak` | Pre V2.2 density |
| `widget.js.pre-premium-visual.bak` | Pre premium visual |
| `widget.js.pre-composer-layout.bak` | Pre composer flex |
| `widget.js.pre-send-incident.bak` | Pre send incident |
| `widget.js.pre-p41-ui-fix.bak` | Pre P4.1 UI |
| `widget.js.20260516_002845.bak` | Early snapshot (renamed; ends in `.bak` for deny rules) |

Backups are kept on disk for rollback; HTTP access is denied.

## Logs to watch

- `storage/logs/iex-*.log`: `support_chat_attachment_rejected`, `support_chat` diagnostics
- Unrelated known noise: Telegram inbound type-hint errors (separate from V2.4 widget work)

## Validation commands

```bash
node --check public/support-chat/widget.js
head -3 public/support-chat/widget.js
wc -c public/support-chat/widget.js public/support-chat/widget.js.stable-v24.bak
diff -q public/support-chat/widget.js public/support-chat/widget.js.stable-v24.bak
php -l packages/SupportChat/Http/Controllers/Public/SupportAttachmentController.php
php -l packages/SupportChat/Services/SupportChatService.php
```
