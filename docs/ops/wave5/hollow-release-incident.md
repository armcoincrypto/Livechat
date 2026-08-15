# Hollow-release incident (Wave 3 / Wave 15)

Classification: **ROOT_CAUSE_PROVEN**.

Evidence: `/var/lib/server-ops/audits/exswaping-wave15-atomic-backend-20260815T202428Z`
Verdict then: `XSWAPING_ATOMIC_BACKEND_RELEASE_BLOCKED` (site 404).

## Why hollow

`git archive` of tracked overlay files only — not the full Laravel tree.

## Why promoted

Cutover pointed nginx at `.../exswaping-backend-releases/20260815T202936Z-23ee338168c6`.
Release `.env`/storage/licenses were symlinks into LIVE. After overlay `mv` to legacy, retarget created LIVE backrefs. `disable_symlinks if_not_owner` + incomplete tree → 404.

## Why preflight missed it

Checked a small file set; did not require min PHP count, allowlist, zero LIVE backrefs, or `artisan about`.

Wave 5: `validate_backend_candidate.sh` rejects synthetic hollow; production inode unchanged.
