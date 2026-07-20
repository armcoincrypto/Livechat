# RUB PREMIUM POLICY RECERT + ZEC CONTROLLED RESTORE GATE

Evidence: `docs/audits/rub-policy-recert-20260720T141157Z/`  
Continued from: `EXSWAPING_ZEC_BASELINE_RESTORED_RUB_POLICY_PENDING`

## 1. Final verdict

```text
EXSWAPING_RUB_POLICY_PENDING_DIRECTIONS_REMAIN_BLOCKED
```

Operator approval was **not invented**. Policy remains `approved=false`. Coin→RUB BestChange eligibility stays **0**. ZEC 540/541/542 remain quarantined despite live ZECUSDT baseline.

## 2. RUB policy

| Item | Value |
|---|---|
| File | `resources/rates/rub-family-premium-policy.json` (schema v2) |
| approved | **false** |
| Families reviewed | SBPRUB, SBERRUB, TCSBRUB/TBRUB, ACRUB, YAMRUB, RFBRUB, CARDRUB, OTHER_RUB |
| Families approved | **0** (pending operator) |
| Proposed APPROVE | SBP/Sber/TCS/AC/YAM/RFB with explained bands typically **0–8%** (TCS up to 9%) |
| CARDRUB | propose **REVISE** (configured profit already ~5%) |
| OTHER_RUB | **KEEP_BLOCKED** |
| Decision package | `RUB_FAMILY_PREMIUM_OPERATOR_DECISION.json` + `.md` |

Live evidence (active dirs vs CBR/independent mid): SBP median raw premium ≈6.3%; Sber ≈6.3%; TCS ≈7.2%; AC ≈6.0%.

## 3. Coin → RUB

Economic audit after NO_POLICY class:

| Class | Count |
|---|---|
| reviewed (all active) | 830 |
| PASS | 61 |
| PASS_EXPLAINED_SPREAD | 1 |
| NO_BASELINE | 700 |
| NO_POLICY | **68** (coin→RUB with baseline but unapproved policy) |
| REVIEW / QUARANTINE_REQUIRED | 0 in this run (RUB moved to NO_POLICY) |

Coin→RUB BestChange-eligible (PASS/PASS_EXPLAINED): **0**

## 4. ZEC (individual)

Live baseline ≈ ZECUSDT×USDRUB (fresh). All three **KEEP_QUARANTINED_NO_POLICY**.

| ID | Pair | raw_dev vs mid | Outcome |
|---|---|---|---|
| 541 | ZEC→SBPRUB | ~4.4% | blocked — no policy |
| 540 | ZEC→SBERRUB | ~7.3% | blocked — no policy |
| 542 | ZEC→ACRUB | ~4.1% | blocked — no policy |

No status mutation. Artifact: `ZEC_INDIVIDUAL_CERTIFICATION.json`

## 5. BestChange package V5

```text
…/BESTCHANGE_COIN_RUB_FINAL_V5.json
…/BESTCHANGE_COIN_RUB_FINAL_V5.csv
…/BESTCHANGE_MANUAL_RESPONSE_TO_KATE_V3.txt
```

`eligible_for_submission`: **0**

## 6. Git

| Item | Value |
|---|---|
| Branch | `release/exswaping-zec-rate-incident-20260720` |
| Prior tip | `0d4111a` |
| This phase tip | (commit after docs/policy tooling) |
| Remote push | **blocked** — GitHub credential read-only |
| Merge | **not performed** |

## 7. Production

```text
health critical=false
baseline_gaps: TONUSDT only
quarantined: 114
ZEC/LTC parsers: live
XML spot checks: ZEC→SBPRUB absent
tests: 67/67 OK
```

Deploy-verify vs `0d4111a` shows expected drift for new policy files until tip is committed/synced as certified SHA.

## 8. NO_BASELINE closure

See `NO_BASELINE_FAMILY_CLOSURE_PLAN.json`. Top families: USDT uncovered destinations (252), unsupported DASH/XMR/SOL/… — recommend KEEP_INTERNAL_ONLY/DEPRECATE, not mass quarantine.

## 9. TON

```text
KEEP_TON_DISABLED
```

Binance `TONUSDT` exchangeInfo status **BREAK**, book zeros. Rapira/MEXC rows disabled/stale; do not confuse with GRAM/STON/KTON.

## 10. Tests

```bash
sudo -u app_exswapin_usr php8.4 vendor/bin/phpunit tests/Unit/Rates --do-not-cache-result
# OK (67 tests, 202 assertions)
sudo nginx -t  # ok
```

New: `rates:rub-family-policy-status`, `RubFamilyPremiumPolicyTest`, export gate requires approved RUB family.

## 11. Rollback

```text
Policy: keep approved=false (safe default) or git checkout prior JSON
ZEC dirs: no mutation this phase
Parser 344/228: prior rollback SQL still valid if needed
Code: revert release commits
XML: no manual edits
```

## 12. Safety

```text
no funds moved
no real customer orders
no secrets exposed
no guessed mappings
no force-push
no automatic BestChange contact
historical data preserved
no uncertified direction restored
no invented operator approval
```

## 13. Remaining operator actions

1. Sign `RUB_FAMILY_PREMIUM_OPERATOR_DECISION.*` and set `approved=true` with identity/time.  
2. Provide writable GitHub credential to push/merge release branch.  
3. After approval: recertify coin→RUB, restore 540/541/542 **individually**, regenerate V5 with non-empty eligible set.  
4. Decide unsupported-asset deprecation vs internal-only for DASH/XMR/… families.
