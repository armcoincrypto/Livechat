# RUB FAMILY PREMIUM POLICY APPROVAL + COIN→RUB RECERT + ZEC CONTROLLED RESTORE

Evidence: `docs/audits/rub-policy-approval-20260720T142523Z/`  
Continued from: `EXSWAPING_RUB_POLICY_PENDING_DIRECTIONS_REMAIN_BLOCKED`

## 1. Final verdict

```text
EXSWAPING_RUB_POLICY_APPROVED_ZEC_PARTIAL_RESTORE_CERTIFIED
```

Operator-approved RUB family premium policy is live (`approved=true`). Coin→RUB BestChange eligibility is limited to **PASS** / **PASS_EXPLAINED_SPREAD**. ZEC **541** and **542** restored individually after rollback artifacts and recertification. ZEC **540** remains quarantined.

## 2. RUB policy (canonical)

| Item | Value |
|---|---|
| File | `resources/rates/rub-family-premium-policy.json` |
| Schema | `rub_family_premium_policy_v3` |
| approved | **true** |
| approved_at (UTC) | **2026-07-20T14:25:23Z** |
| approved_by | `exswaping-operator-chat-approval` |
| Service | `App\Services\Rates\RubFamilyPremiumPolicy` |

| Family | Target | Warning | Hard max |
|---|---|---|---|
| SBPRUB / SBERRUB / ACRUB / YAMRUB / RFBRUB | 3–5% | >6% | 8% |
| TCSBRUB / TBRUB / CARDRUB | 3–6% | >7% | 9% |
| OTHER_RUB | KEEP_BLOCKED | — | — |

Mandatory controls enforced:

- Independent crypto baseline ≤15 minutes; approved USD/RUB source
- No BestChange-circular baseline
- Block: no family policy, missing/stale baseline, missing effective reserve, API/order/XML mismatch (beyond rounding)
- Unexplained vs **policy expected** (target-band top): warn >1%, block >2%
- Family hard max is a **ceiling only** — configured premiums were **not** auto-raised
- Export/submit only **PASS** or **PASS_EXPLAINED_SPREAD**

Rollback: `POLICY_ROLLBACK.md`, `policy_rollback.sql`, `rub-family-premium-policy.before.json`

## 3. Coin → RUB (post-approval, post-restore)

| Class (active coin→RUB) | Count |
|---|---|
| PASS | 17 |
| PASS_EXPLAINED_SPREAD | 29 |
| REVIEW | 17 |
| QUARANTINE_REQUIRED | 7 |
| BestChange-eligible | **46** |

Full catalog economic audit (active): PASS 73 / PASS_EXPLAINED 29 / REVIEW 23 / QUARANTINE 7 / NO_BASELINE 700 / NO_POLICY 0.

Artifacts: `coin_rub_recert_post_restore.json`, `economic_audit_summary.json`

## 4. ZEC individual restore

Rollback SQL prepared **before** restores: `zec_restore_rollback.sql`, `zec_restore_541_apply.sql`, `zec_restore_542_apply.sql`.

| ID | Pair | Outcome | Notes |
|---|---|---|---|
| 540 | ZEC→SBERRUB | **KEEP_QUARANTINED** | REVIEW / unexplained warning band; `status=0`, `allow_export=2`; absent from XML |
| 541 | ZEC→SBPRUB | **RESTORED_CERTIFIED** | PASS_EXPLAINED_SPREAD; XML present; DB↔XML parity OK |
| 542 | ZEC→ACRUB | **RESTORED_CERTIFIED** | PASS_EXPLAINED_SPREAD; XML present; DB↔XML parity OK |

Restores were sequential with parser/XML cycles between them. Artifact: `ZEC_INDIVIDUAL_CERTIFICATION.json`

## 5. BestChange package V6

```text
…/BESTCHANGE_COIN_RUB_FINAL_V6.json
…/BESTCHANGE_COIN_RUB_FINAL_V6.csv
…/BESTCHANGE_MANUAL_RESPONSE_TO_KATE_V4.txt
```

`eligible_for_submission`: **46** (PASS / PASS_EXPLAINED only)  
XML probe: **20/20**; ZEC→SBPRUB 20/20; ZEC→ACRUB 20/20; ZEC→SBERRUB 0/20

## 6. Tests / production

| Check | Result |
|---|---|
| PHPUnit `tests/Unit/Rates` | **71/71 OK** |
| `rates:health` critical | **false** (gap: TONUSDT only) |
| Public XML | 807 items post-restore |

## 7. Git

| Item | Value |
|---|---|
| Branch | `release/exswaping-zec-rate-incident-20260720` |
| Prior tip | `e11a6e0` |
| Remote push | **blocked** — GitHub credential read-only |
| Merge | **not performed** |

## 8. Explicit non-actions

- Did not restore 540
- Did not auto-increase configured premiums to family maxima
- Did not mass-quarantine NO_BASELINE
- Did not contact BestChange automatically
- Did not enable TONUSDT (Binance BREAK)
