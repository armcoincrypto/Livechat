# CARDAMD export strategy — 2026-07-20T09:30:01Z

## Internal variants
8 currency rows share `designation_xml=CARDAMD` with distinct `tech_name` / `id_payment` banks:
EVOCA, AGBA, ARARAT, FAST, AMERIA, CONVERS, INECO, VISA CARD AMD.

## Verdict
`ONE_CANONICAL_EXPORT_WITH_INTERNAL_VARIANTS`

## Rationale
- Public BestChange identity is the shared `CARDAMD` code (VERIFIED).
- Internal checkout may keep bank-specific payment rows.
- Do not merge DB rows solely because the normalized code matches.
- Duplicate BestChange economic directions are prevented by single XML `from`/`to` identity (`CARDAMD`).

## Alternate not chosen
`SEPARATE_VERIFIED_VARIANTS` would require distinct verified BestChange codes per bank (not available).
