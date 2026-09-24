# Partner Accrual Wave A — legacy float balance debt

```text
LEGACY_COLUMN=user_balance.balance
LEGACY_TYPE=float/double
NEW_PLAN_MONEY=DECIMAL(20,8)
NEW_INSTALLMENT_MONEY=DECIMAL(20,8)
APPLICATION_MATH=Brick\Math\BigDecimal (AccrualMoney)
NEW_FEATURE_FLOAT_MATH=NO
GLOBAL_FLOAT_CONVERSION_IN_WAVE_A=NO
```

Reason: converting production `user_balance.balance` would massively increase blast radius.
Wave A converts at the boundary via `AccrualMoney::toLegacyBalanceScalar` / `fromLegacyBalance`.
