# RUB Family Premium — Operator Decision Package

Generated: 2026-07-20T14:14:12.217415+00:00

## Status

- Policy file: `resources/rates/rub-family-premium-policy.json`
- **approved = false** (no invented approval)
- BestChange coin→RUB exports remain blocked until sign-off

## How to approve

1. Review live premium stats and historical order aggregates in this audit folder.
2. For each family choose: `APPROVE` | `REVISE` | `KEEP_BLOCKED` | `INTERNAL_ONLY` | `DEPRECATE`.
3. Record identity and UTC time in this JSON (`operator_decision`, `operator_approved_by`, `operator_approved_at`).
4. Set policy `approved=true`, `approved_by`, `approved_at`.
5. Re-run `rates:economic-audit`, `rates:rub-family-policy-status`, rebuild V5.
6. Restore ZEC 540/541/542 **one at a time** only after PASS / PASS_EXPLAINED_SPREAD.

## Recommended decisions (evidence-based, not yet approved)

### SBPRUB — propose **APPROVE**
- Band: 0.0–8.0% explained (target 5.0%)
- Warn/Critical unexplained: 5.0% / 10.0%
- Reason: High-demand SBP rail; live OTC premium cluster ~6–7% vs CBR; historical USDT/SBP ~4%+. Proposed explained band 0–8%.
- Live stats: `{"n": 10, "median_raw_premium_pct": 6.284, "p75": 7.211, "p90": 7.237, "max": 7.536, "median_configured_profit_pct": 0.15}`

### SBERRUB — propose **APPROVE**
- Band: 0.0–8.0% explained (target 5.0%)
- Warn/Critical unexplained: 5.0% / 10.0%
- Reason: Highest volume RUB rail; premium profile similar to SBP.
- Live stats: `{"n": 11, "median_raw_premium_pct": 6.349, "p75": 7.21, "p90": 7.237, "max": 8.436, "median_configured_profit_pct": 0.0}`

### TCSBRUB — propose **APPROVE**
- Band: 0.0–9.0% explained (target 5.5%)
- Warn/Critical unexplained: 5.0% / 10.0%
- Reason: Slightly higher live median than SBP/Sber; allow up to 9% explained.
- Live stats: `{"n": 9, "median_raw_premium_pct": 7.178, "p75": 7.237, "p90": 7.812, "max": 8.44, "median_configured_profit_pct": 0.3}`

### ACRUB — propose **APPROVE**
- Band: 0.0–7.0% explained (target 4.5%)
- Warn/Critical unexplained: 4.0% / 9.0%
- Reason: E-wallet rail; tighter proposed band than bank/SBP.
- Live stats: `{"n": 9, "median_raw_premium_pct": 5.955, "p75": 6.728, "p90": 6.761, "max": 7.189, "median_configured_profit_pct": 0.15}`

### YAMRUB — propose **APPROVE**
- Band: 0.0–7.0% explained (target 4.0%)
- Warn/Critical unexplained: 4.0% / 9.0%
- Reason: Lower live median than SBP; still OTC.
- Live stats: `{"n": 9, "median_raw_premium_pct": 4.896, "p75": 5.064, "p90": 5.865, "max": 7.136, "median_configured_profit_pct": 0.3}`

### RFBRUB — propose **APPROVE**
- Band: 0.0–7.0% explained (target 4.5%)
- Warn/Critical unexplained: 4.0% / 9.0%
- Reason: Bank rail; mid band.
- Live stats: `{"n": 8, "median_raw_premium_pct": 5.444, "p75": 6.225, "p90": 6.225, "max": 6.226, "median_configured_profit_pct": 0.3}`

### CARDRUB — propose **REVISE**
- Band: 0.0–9.0% explained (target 5.0%)
- Warn/Critical unexplained: 5.0% / 11.0%
- Reason: Needs operator revise: configured profit already explains most of the gap.
- Live stats: `{"n": 4, "median_raw_premium_pct": 1.018, "p75": 1.237, "p90": 2.165, "max": 2.165, "median_configured_profit_pct": 5.0}`

### OTHER_RUB — propose **KEEP_BLOCKED**
- Band: 0.0–0.0% explained (target 0.0%)
- Warn/Critical unexplained: 3.0% / 7.0%
- Reason: Catch-all remains blocked until mapped to a named family.

## ZEC individual preview (live baseline, still quarantined)

- **542** ZEC→ACRUB: course=43169.683651… baseline≈41470.560339 raw_dev=4.097179% status=quarantined
- **540** ZEC→SBERRUB: course=44517.921233… baseline≈41470.560339 raw_dev=7.348251% status=quarantined
- **541** ZEC→SBPRUB: course=43286.997609… baseline≈41470.560339 raw_dev=4.380064% status=quarantined

## TON

- Binance `TONUSDT` market status **BREAK** (zero book). Decision: **KEEP_TON_DISABLED**.
