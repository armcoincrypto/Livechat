# Atomic backend release design (long-term) + interim protected patch

## Problem class prevented
POST_DEPLOY_FILE_REMOVAL / DEPLOY_COPY_OMISSION where live PHP references Rates classes/JSON
that were never present or were wiped from the overlay tree.

## Interim (implemented now)
`scripts/deploy/verify_backend_candidate_integrity.sh`
`scripts/deploy/apply_backend_protected_patch.sh`
`scripts/deploy/exswaping_deploy_lock.sh`
`resources/deploy/release-integrity-manifest.json`

Rules:
1. Acquire backend-financial lock with owner + candidate SHA.
2. Copy **all** candidate additions/changes (Git ACMR set ∪ manifest), not only edits to existing files.
3. Backup targets before overwrite.
4. Hash-compare candidate Git blob ≡ candidate worktree ≡ live target for every manifest path.
5. Resolve required classes via Composer autoload in candidate.
6. Validate policy JSON parseability.
7. Run `rates:active-pair-closure` smoke; refuse reload on gaps.
8. Reload PHP-FPM only after gates PASS.
9. On failure: retain lock; do not claim success.

## Long-term atomic releases
```text
/var/lib/exswaping/backend/releases/<timestamp>-<sha>/
/var/lib/exswaping/backend/current -> release
```
- Full tree + vendor prepared offline
- `composer dump-autoload --no-dev --classmap-authoritative` in release dir
- Shared storage/env linked
- Integrity + PHPUnit + closure smoke against release path
- Atomic `ln -sfn` switch then PHP-FPM reload
- Rollback = repoint `current` to previous release

Do not convert live overlay in this change set; interim protected patch is the enforced path.
