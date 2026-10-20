# Google + Yandex OAuth — Implementation Status (post-certification)

## Isolation
- FE: `feature/oauth-google-yandex-20261020T200136Z` (PRE `a0e8b456…`)
- BE: `feature/oauth-google-yandex-20261020T200136Z` (PRE `432d1992…`)
- Live releases untouched.

## Certification evidence (2026-10-20)
- PHPUnit Auth/OAuth: **32 passed**, 116 assertions (isolated sqlite)
- Migration isolated sqlite: OK + idempotent
- Composer validate: OK
- FE oauth contract tests: **5 passed**
- FE full build/typecheck: skipped (RESOURCE_CONFLICT)
- Production: NOT mutated; flags remain off

## Hardening
- Legacy Google subject fallback; deactivated denied
- Link requires auth + password reauth; no email-only link
- ReturnTo sanitizer; AuthAudit; concurrent subject conflict handling
- Settings link UI (FE)

See `RELEASE_CERTIFICATION.md` for deploy ordering and rollback.
