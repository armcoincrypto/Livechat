# XML feed invariants

Public monitor feed: `public/static/exports/currencies.xml` (nginx alias `/currencies.xml`).

## Online + technical mode OFF

- Must **never** publish `EMPTY_RATES_XML` (~177 B).
- Hide flag (`RatesXmlMonitorGate::flagPath()`, typically `/var/lib/exswaping/rates-xml-hidden.flag`) must be **absent**.
- Invalid / empty / concurrent candidates must keep **last-good** full XML (`AtomicPublicXmlPublisher`).
- Partial XML must never become public.

## Offline / tech / force empty

Empty publish is allowed only via explicit `forceEmptyPolicy` (offline/tech hide paths). Covered by unit tests in `RatesXmlMonitorReliabilityTest` + `ExportCoursesOfflineClearTest`.

## Health

`RatesXmlPublicFeedHealth::snapshot()` / `assertOnlineInvariant($context)`:

- bytes/rows/sha256
- gate_hidden, site_online, technical_mode, must_keep_full
- empty_rates_match, invariant_ok
- publish_age_sec / publish_age_sane (warn if stale while online)

Critical log: `rates_xml_online_invariant_violated`  
Warn log: `rates_xml_publish_age_stale`

## Publish lock

`/var/lock/exswaping-xml-publish.lock` — flock target; report via `scripts/ops/rate_system_lock_status.sh`. Never auto-delete while held by a live PID.
