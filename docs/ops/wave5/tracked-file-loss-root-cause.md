# Tracked-file loss root cause

Classification: **LIKELY** for overlay-tracked disappearance; **ROOT_CAUSE_PROVEN** for hollow promotion replacing the runtime tree.

## Proven mechanisms

1. `git archive` of overlay-only checkout (~619 tracked files vs ~2960 live PHP excluding vendor).
2. `rsync --delete` from an incomplete mirror (Wave 12 contract) would delete live-only files.
3. Wave 15 docroot switch to a release with LIVE backrefs → 404; overlay restored.
   Audit: `/var/lib/server-ops/audits/exswaping-wave15-atomic-backend-20260815T202428Z`

## Not proven

A FastPanel/cron `git clean` of this overlay. History was not treated as proof.

## Why critical files were untracked

Repo tracked a rates/analytics overlay inside a full Laravel tree. `artisan` and most of `app/`/`packages/` were live-loaded and never added. Wave 5 committed that runtime source as `07ab31f`.
