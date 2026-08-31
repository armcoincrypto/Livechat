# Admin SPA source recovery status

**Last audit:** 2026-09-01 (`/var/lib/server-ops/audits/admin-source-recovery-20260901/`)  
**Verdict:** `EXSWAPING_ADMIN_SOURCE_RECOVERY_AND_STABILITY_BLOCKED` for Vue source; stability bundle restored.

## Finding

```text
DIRECTION_EXCHANGE_SOURCE_STATE=F_INCOMPLETE_RECOVERY_TREE
SOURCE_FOUND_IN_GIT=NO
SOURCE_FOUND_IN_RELEASE_ARCHIVE=NO
SOURCE_MAPS=0
RECOVERY_TYPE=(none on host) — external vendor package required for A/B
```

The iEX admin Vite tree `resources/vuejs/**` (including `direction-exchange.vue`) was never present in this host’s git lineage or deploy archives. Production runs on prebuilt `public/build` + Vite manifest (dated ~2026-02-22).

## Certified compiled list chunk

| Field | Value |
|-------|-------|
| Path | `public/build/assets/direction-exchange--Ftzn3h7.js` |
| Provenance | Label-UX patch on top of stock Feb/Mar build (`*.bak-labelux` = pre-patch) |
| Certified md5 | `f0b59c4fbaf6acb9726f31471d0b73aa` |
| Behavior | Prefers `rate_source_label \|\| parser_source_name` |
| Preserved copy | `artifacts/admin-spa/direction-exchange--Ftzn3h7.certified-labelux.js` |

## Cutover regression (20260831T112919Z)

`deploy_backend_release.sh` overlays `exswaping-backend-shared/public-app/` onto the release `public/`. Shared still held the **pre-labelux** list chunk, wiping the certified preference. Fixed by restoring the certified asset into shared and cutting over `20260831T220226Z-0145e92b3d42`.

## Defense in depth (until Vue source exists)

1. API `DirectionExchangeResources` emits display-safe `parser_source_name` via `AdminRateSourcePresenter::displayParserName`
2. Certified list chunk prefers `rate_source_label`
3. `public/static/admin-rate-source-scrubber.js` — **KEEP_DEFENSE_IN_DEPTH**

## Unblock path

Acquire vendor admin SPA source per `VENDOR_ACQUISITION_CHECKLIST.md`, then rebuild with Node matching the original Vite toolchain and re-apply label preference in Vue source (not in minified JS).
