# C3-A / C2 follow-up — promotion race hardening (proposed)

## Required rules (implement in deploy_owned_frontend.sh)

1. Deploy lock held through build → promote → post-verify (already mostly true).
2. Record `TARGET_SHA` + `CANONICAL_TIP_AT_START` before build (exists).
3. `rate_routing_ancestry_gate` must remain (floating `464ad81` + add routing ancestor `be81a4a` or tip containing it).
4. After promote: `live RELEASE.json.sourceCommit == TARGET_SHA` and `BUILD_ID` matches candidate.
5. Concurrent SEO/UI deploys must wait on `/var/lock/exswaping-owned-deploy.lock` (document for operators).
6. Optional: require `CANONICAL_WORKTREE_CLEAN=1` (already blocks uncommitted files).

## Ancestor list addition

Append to `scripts/deploy/lib/required_rate_routing_ancestors.txt`:

```
be81a4a8f7e34885afa72904ae7ed7f013fe5bf2
```

(or the merge-base equivalent present on tip after C3 lands).

## Do not

- Kill concurrent SEO builds mid-flight
- Promote without ancestry of certified floating + BestChange routing commits
