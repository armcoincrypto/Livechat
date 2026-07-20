# Policy rollback

1. Restore JSON:
   `cp docs/audits/rub-policy-approval-*/rub-family-premium-policy.before.json resources/rates/rub-family-premium-policy.json`
   (and sync to production app tree)
2. Re-run `rates:deploy-verify` / `rates:economic-audit`
3. If any ZEC direction was restored, apply `zec_restore_rollback.sql`
