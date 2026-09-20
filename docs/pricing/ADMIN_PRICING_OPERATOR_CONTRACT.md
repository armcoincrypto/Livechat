# Admin Pricing Operator Contract

Status: operator safety / transparency. Does **not** change pricing economics,
BestChange selector math, Top-N behavior, profits, positions, ownership, or mappings.

## Authority model

### BESTCHANGE

- **Position** selects **BASE** (source book rate from the BestChange competitor list).
- **Profit** modifies the **commercial / FINAL CUSTOMER RATE** applied on top of BASE.
- `course_value` / STORED_COURSE_VALUE is a **commercial snapshot**, not “the BestChange rate”.
- Manual rate fields are **non-authoritative** while OWNER=BESTCHANGE.

### DERIVED

- Derived market baseline authority owns **BASE**.
- Profit is commercial adjustment; compilers must not silently wipe owner profit.
- Manual rate fields are **non-authoritative**.

### ZELLE

- Zelle USDTTRC20 benchmark / margin path owns the rate construction.
- Displayed “Прибыль” maps to the Zelle commercial field (`floating_fee` storage).
- Manual rate fields are **non-authoritative**.

### MANUAL

- Operator owns the rate (`manual_rate_value` / manual parser source).
- Promoting another owner into MANUAL (or the reverse) requires a **deliberate ownership action**
  (`confirm_ownership_switch=1`), never a side-effect of Position/Profit saves.

## Operator labels

| Label | Meaning |
| --- | --- |
| BASE | Source / book rate used before commercial % |
| PROFIT | Commercial percent adjustment |
| FINAL CUSTOMER RATE | Rate shown to customers / public quote path |
| STORED_COURSE_VALUE | Persisted commercial `course_value` (not BestChange book) |
| XML_RATE | Monitor XML consumer rate (aligned with stored commercial course) |

## Admin surfaces

1. **Truth panel** — `GET /administrator/direction_exchange/{id}/pricing/truth`
2. **Change preview** — `POST /administrator/direction_exchange/{id}/pricing/preview` (no DB write)
3. **Convergence** — `GET|POST /administrator/direction_exchange/{id}/pricing/converge`
4. **Mass preview** — `POST /administrator/pricing/mass-preview`

## Safety rules

- Ordinary Position/Profit updates must **not** promote `manual_rate_value` into pricing authority.
- OWNER / RATE_MODE changes require explicit confirm flags; Mass Editor blocks ownership piggybacks.
- Mass financial edits require `confirm=1` + deterministic `confirm_token`.
- After save: `SAVE_ACCEPTED`, then convergence status `CONVERGED` | `WAITING_FOR_COMPILER` | `PRICING_MISMATCH`.
- Health warnings are surfaced instead of silent acceptance of stale/mismatched state.
- Every financial admin change is audited (operator, timestamp, direction, pair, old/new commercial fields, before/after BASE & customer rate, change/request id).

## Formula contract

Pricing formula, BestChange selector, and Top-N behavior are unchanged by this operator-safety layer.
