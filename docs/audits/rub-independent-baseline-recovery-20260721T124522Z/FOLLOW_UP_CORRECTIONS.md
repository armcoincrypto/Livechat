# RUB recovery follow-up corrections

Status: `RECOVERY_BLOCKED_PENDING_EXACT_PREMIUM_APPROVAL_AND_RELEASE_ACCESS`

The initial 69/69 simulation used each approved family's
`target_premium_max_percent` as a hypothetical source premium. That proves the
independent provider path and calculator orientation, but it is not authority
to publish those rates: the approved policy explicitly defines the target as a
band/ceiling and says it must not be applied automatically.

The release now requires a separate operator-approved
`canonical_premium_percent` for every family. Without it:

- the independent calculator strategy returns no rate;
- no BestChange or peer fallback is allowed;
- no direction is written or restored;
- quote, order, XML, and package surfaces remain fail-closed.

Additional hardening applied after source-path review:

- parser rows require `is_not_update=0`;
- future-dated rows are rejected;
- providers are constrained by symbol and approved alias;
- provider divergence above 2% is rejected rather than annotated;
- medians are sorted numerically;
- USDC uses its fresh USDC/USDT quote before USD/RUB conversion;
- the independent strategy returns the approved final family rate and the
  calculator does not reapply heterogeneous direction profits to it;
- both compilers share one direction-write lock;
- `scheme:files` has command and scheduler overlap protection;
- XML and package reads run in one repeatable-read transaction and shared quote
  snapshot.

Verification with the current policy, which has no exact canonical premiums:

```text
directions evaluated: 69
positive canonical sources: 0
export allowed: 0
result: fail closed
```

No production code, rates, statuses, XML, cache, or database rows were changed.
