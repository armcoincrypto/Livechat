# Git lineage report

Repo: `/var/www/app_exswapin_usr/data/www/app.exswaping.com`
Remote: origin → `git@github.com:armcoincrypto/Livechat.git`

## Canonical branch

Local `main` only. Do not pull or merge `origin/main`.

## Ahead / behind

After Wave 5 source commit: `main` vs `origin/main` **ahead 75, behind 11**.
Merge-base: `8967daa`. Origin-only commits are a July RUB-independent baseline, not this overlay's runtime lineage.

## Other trees

Older deploy default worktree `exswaping-backend-production-source-20260815` / branch
`production/exswaping-recovered-baseline-20260815` is **not** overlay `eebe75e`/`07ab31f`.
`git archive` from that tree produced hollow releases.

## Commits

- `eebe75e` last certified Wave 4 backend
- `07ab31f` track production Laravel source (no business redesign)

Protected overlay WIP remains uncommitted.
