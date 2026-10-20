# Google + Yandex OAuth — Implementation Status

## SHAs
- Live FE: `a0e8b4569f92ce4a95777321d0c3e3a1ff1d45aa`
- Live BE: `e394ca14c86ed6013b890138643a6a757fe2f575`
- OAuth FE branch HEAD: `f63164923e95609a93e44c19655f694dfbc8ed48`
- OAuth BE branch HEAD (rebased onto live): `38ed2bb18ed9461c8404fbe242fbc97e4d79ef17`

## Tests
- BE PHPUnit Auth/OAuth: 32 PASS (post-rebase)
- FE oauth contracts: 5 PASS
- MySQL isolated migration: PASS
- FE production build: PENDING capacity

## Production
- Not deployed; flags off; migration not applied
- See PRODUCTION_DELIVERY_STATUS.md
