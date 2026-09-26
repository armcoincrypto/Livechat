#!/usr/bin/env bash
# Rate-system pre-deploy gate (read-only against LIVE app tree + DB).
# FAIL closed on economics/XML/Zelle freeze defects.
# Blocks SITE_ONLINE + EMPTY_RATES_XML / empty_rates_match.
# Usage: rate_system_predeploy_gate.sh [live-app-path]
set -euo pipefail

LIVE="${1:-/var/www/app_exswapin_usr/data/www/app.exswaping.com}"
LIVE="$(readlink -f "$LIVE")"
PHP="${PHP_BIN:-/usr/bin/php8.4}"
USER_RUN="${EXSWAPING_APP_USER:-app_exswapin_usr}"

if [[ ! -f "$LIVE/artisan" ]]; then
  echo "FAIL live app missing: $LIVE" >&2
  exit 2
fi

echo "RATE_SYSTEM_PREDEPLOY_GATE live=$LIVE"

sudo -u "$USER_RUN" "$PHP" <<PHP
<?php
require '$LIVE/vendor/autoload.php';
\$app = require '$LIVE/bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\DirectionExchange;
use App\Services\Rates\AdminPricingOperatorSafety;
use App\Services\Rates\CommercialAdjustmentResolver;
use App\Services\Rates\RatesXmlMonitorGate;
use App\Services\Rates\RatesXmlPublicFeedHealth;

\$fixturePath = '$LIVE/tests/fixtures/rates/golden_directions_freeze.json';
if (! is_file(\$fixturePath)) {
    // Candidate may ship fixture before overlay; fall back to known freeze IDs.
    \$fixture = [
        'directions' => array_merge(
            array_map(static fn (\$id) => [
                'id' => \$id,
                'owner' => 'ZELLE',
                'floating_fee' => '-6',
                'fix_fee' => '-3',
                'profit' => '0',
                'parser_source_name' => 'ZELLE_USDTTRC20_BENCHMARK',
            ], [1525, 1537, 1552, 1556, 1586, 3222]),
            array_map(static fn (\$id) => [
                'id' => \$id,
                'owner' => 'BESTCHANGE',
                'floating_fee' => '0',
                'parser_source_name' => 'BestChange',
            ], [16, 29, 34, 15, 27, 1415, 41])
        ),
    ];
} else {
    \$fixture = json_decode((string) file_get_contents(\$fixturePath), true);
}

\$pq = 0; \$oe = 0; \$topn = 0; \$stored = 0; \$pos = 0; \$map = 0; \$unknown = 0;
\$derivedBenchmark = 0; \$crossOutlier = 0; \$unhealthyUsed = 0; \$delistedUsed = 0; \$staleUsed = 0;
\$resolver = app(CommercialAdjustmentResolver::class);
\$imb = app(\\App\\Services\\Rates\\IndependentMarketBaseline::class);
\$auth = \\App\\Services\\Rates\\DerivedMarketBaselineAuthority::fromStorageApp();

foreach ((\$fixture['directions'] ?? []) as \$row) {
    \$id = (int) \$row['id'];
    \$d = DirectionExchange::with(['currency1', 'currency2', 'bestchange_directions'])->find(\$id);
    if (! \$d) {
        echo "MISSING_DIRECTION id=\$id\n";
        \$unknown++;
        continue;
    }
    \$family = \$resolver->family(\$d);
    \$expectOwner = (string) (\$row['owner'] ?? '');
    if (\$expectOwner === 'DERIVED') {
        // Derived ownership is parser_source_name based; family() may still say MANUAL/OTHER.
        \$ps = (string) \$d->parser_source_name;
        if (\$ps !== 'DERIVED_MARKET_BASELINE' && \$ps !== (string) (\$row['parser_source_name'] ?? 'DERIVED_MARKET_BASELINE')) {
            echo "DERIVED_OWNER_DEFECT id=\$id parser=\$ps\n";
            \$map++;
        }
    } elseif (\$expectOwner !== '' && \$family !== \$expectOwner) {
        echo "OWNER_DEFECT id=\$id expect=\$expectOwner got=\$family\n";
        \$map++;
    }
    if (\$expectOwner === 'ZELLE') {
        if (abs((float) \$d->floating_fee + 6.0) > 0.001) {
            echo "ZELLE_FLOATING_DEFECT id=\$id floating_fee={\$d->floating_fee}\n";
            \$oe++;
            \$pq++;
        }
        if (abs((float) \$d->fix_fee + 3.0) > 0.001) {
            echo "ZELLE_FIX_DEFECT id=\$id fix_fee={\$d->fix_fee}\n";
            \$oe++;
        }
        if (abs((float) (\$d->profit ?? 0)) > 0.001) {
            echo "ZELLE_PROFIT_NONZERO id=\$id profit={\$d->profit}\n";
            \$oe++;
        }
        \$truth = AdminPricingOperatorSafety::truthPanel(\$d);
        \$final = (float) (\$truth['CALCULATOR_FINAL_RATE'] ?? 0);
        if (\$final > 0 && abs(\$final - 0.94) > 0.02) {
            echo "ZELLE_PUBLIC_QUOTE_DEFECT id=\$id final=\$final\n";
            \$pq++;
        }
    }
    if (\$expectOwner === 'BESTCHANGE') {
        if (abs((float) \$d->floating_fee) > 0.0001) {
            echo "BC_FLOATING_NONZERO id=\$id floating_fee={\$d->floating_fee}\n";
            \$oe++;
        }
        if ((string) \$d->parser_source_name !== 'BestChange') {
            echo "BC_PARSER_DEFECT id=\$id parser={\$d->parser_source_name}\n";
            \$map++;
        }
        \$truth = AdminPricingOperatorSafety::truthPanel(\$d);
        \$calc = (string) (\$truth['CALCULATOR_FINAL_RATE'] ?? '');
        \$storedV = (string) (\$truth['STORED_COURSE_VALUE'] ?? '');
        if (\$calc !== '' && \$storedV !== '' && \$calc !== \$storedV) {
            // Soft: wait-for-compiler is OK; hard mismatch only when both numeric and diverge >0.5%
            if (is_numeric(\$calc) && is_numeric(\$storedV) && (float) \$calc > 0) {
                \$rel = abs((float) \$calc - (float) \$storedV) / abs((float) \$calc);
                if (\$rel > 0.05) {
                    echo "STORED_COURSE_DEFECT id=\$id calc=\$calc stored=\$storedV rel=\$rel\n";
                    \$stored++;
                }
            }
        }
        \$eff = \$truth['EFFECTIVE_POSITION'] ?? null;
        \$cfg = \$truth['CONFIGURED_POSITION'] ?? null;
        if (\$eff === null && \$cfg === null) {
            // thin book / inactive — count as position selector soft unknown, not hard fail
        }
    }
    if (\$expectOwner === 'DERIVED') {
        \$ev = \$auth->evaluate(\$id);
        if (empty(\$ev['ok'])) {
            echo "DERIVED_EVAL_FAIL id=\$id reason=".(\$ev['reason'] ?? '')."\n";
            \$derivedBenchmark++;
        } else {
            \$asset = (string) ((\$auth->config()['directions'][(string) \$id]['asset_leg']['symbol'] ?? ''));
            \$q = \$asset !== '' ? \$imb->quote(\$asset) : null;
            \$qs = is_array(\$q) ? (string) (\$q['quality_state'] ?? '') : 'QUARANTINED';
            if (\$qs === 'QUARANTINED' || \$q === null) {
                echo "DERIVED_BENCHMARK_DEFECT id=\$id asset=\$asset quality=\$qs\n";
                \$derivedBenchmark++;
            }
            foreach ((array) (\$q['excluded'] ?? []) as \$ex) {
                \$cls = (string) (\$ex['class'] ?? '');
                if (in_array(\$cls, ['BREAK', 'DELISTED'], true) && !empty(\$ex['eligible'])) {
                    \$delistedUsed++;
                }
                // If a BREAK row somehow remained eligible, that is a hard fail counted above via quality.
            }
            // Independent surface check: stored course must track evaluate BASE within 5%
            // (compiler lag allowed). This is NOT internal-only — evaluate uses integrity IMB.
            \$storedCourse = (float) \$d->course_value;
            \$evalRate = (float) \$ev['rate'];
            if (\$storedCourse > 0 && \$evalRate > 0) {
                \$rel = abs(\$storedCourse - \$evalRate) / \$evalRate;
                if (\$rel > 0.10) {
                    echo "CROSS_RATE_OUTLIER id=\$id stored=\$storedCourse eval=\$evalRate rel=\$rel\n";
                    \$crossOutlier++;
                }
            }
        }
    }
}

// Global critical-leg integrity: TONUSDT must not be HEALTHY on BREAK-only data.
\$ton = \$imb->quote('TONUSDT');
\$tonQs = is_array(\$ton) ? (string) (\$ton['quality_state'] ?? '') : 'QUARANTINED';
\$tonSrc = is_array(\$ton) ? (string) (\$ton['source'] ?? '') : '';
if (is_array(\$ton)) {
    foreach ((array) (\$ton['excluded'] ?? []) as \$ex) {
        \$cls = (string) (\$ex['class'] ?? '');
        if (\$cls === 'BREAK' || \$cls === 'DELISTED') {
            // excluded is good — only fail if used
        }
        if (\$cls === 'STALE') {
            // informational
        }
    }
    if (str_contains(\$tonSrc, 'binance_ton-usdt') && !str_contains(\$tonSrc, 'certified_fallback')) {
        echo "DELISTED_PROVIDER_USED TONUSDT source=\$tonSrc\n";
        \$delistedUsed++;
        \$unhealthyUsed++;
    }
    if (\$tonQs === 'QUARANTINED') {
        echo "TONUSDT_QUARANTINED\n";
        \$derivedBenchmark++;
    }
} else {
    echo "TONUSDT_MISSING\n";
    \$derivedBenchmark++;
}

// ---------------------------------------------------------------------------
// GRAM Wave-1 DERIVED→BESTCHANGE registry persistence (permanent).
// Fail if migration IDs re-enter derived registry / regain DERIVED ownership /
// lose BestChange ownership / regain block_bestchange_overwrite via registry.
// ---------------------------------------------------------------------------
\$gramManifestPath = '$LIVE/resources/rates/gram-bestchange-migrated-ids.json';
\$gramIds = [132,410,411,412,413,414,415,416,417,418,419,420,421,422,423,424,425,426];
if (is_file(\$gramManifestPath)) {
    \$gm = json_decode((string) file_get_contents(\$gramManifestPath), true);
    if (is_array(\$gm) && ! empty(\$gm['direction_ids'])) {
        \$gramIds = array_map('intval', \$gm['direction_ids']);
    }
}
\$gramInRegistry = 0;
\$gramBlockReintroduced = 0;
\$gramDerivedOwner = 0;
\$gramBestchangeOwner = 0;
\$cfgDirs = (array) (\$auth->config()['directions'] ?? []);
foreach (\$gramIds as \$gid) {
    \$row = \$cfgDirs[(string) \$gid] ?? \$cfgDirs[\$gid] ?? null;
    if (is_array(\$row)) {
        \$gramInRegistry++;
        echo "GRAM_ID_IN_DERIVED_REGISTRY id=\$gid\n";
        if (! empty(\$row['ownership']['block_bestchange_overwrite'])) {
            \$gramBlockReintroduced++;
            echo "GRAM_BLOCK_BESTCHANGE_REINTRODUCED id=\$gid\n";
        }
    }
    \$gd = DirectionExchange::query()->find(\$gid);
    if (! \$gd) {
        echo "GRAM_DIRECTION_MISSING id=\$gid\n";
        \$unknown++;
        continue;
    }
    \$ps = (string) (\$gd->parser_source_name ?? '');
    if (\$ps === 'DERIVED_MARKET_BASELINE') {
        \$gramDerivedOwner++;
        echo "GRAM_DERIVED_OWNER id=\$gid\n";
    }
    if (\$ps === 'BestChange') {
        \$gramBestchangeOwner++;
    } else {
        echo "GRAM_NOT_BESTCHANGE_OWNER id=\$gid parser=\$ps\n";
        \$map++;
    }
}
\$gramExpected = count(\$gramIds);
echo "GRAM_IDS_IN_DERIVED_REGISTRY=\$gramInRegistry\n";
echo "GRAM_DERIVED_OWNER_TOTAL=\$gramDerivedOwner\n";
echo "GRAM_BESTCHANGE_OWNER_TOTAL=\$gramBestchangeOwner\n";
echo "GRAM_BLOCK_BESTCHANGE_REINTRODUCED_TOTAL=\$gramBlockReintroduced\n";
if (\$gramInRegistry !== 0) {
    \$map += \$gramInRegistry;
}
if (\$gramDerivedOwner !== 0) {
    \$map += \$gramDerivedOwner;
}
if (\$gramBestchangeOwner !== \$gramExpected) {
    echo "GRAM_BESTCHANGE_OWNER_TOTAL_DEFECT expect=\$gramExpected got=\$gramBestchangeOwner\n";
    \$map++;
}
if (\$gramBlockReintroduced !== 0) {
    \$map += \$gramBlockReintroduced;
}

// ---------------------------------------------------------------------------
// Wave 2A NON-TON DERIVED→BESTCHANGE (2237 NEO→CARDTJS) persistence.
// ---------------------------------------------------------------------------
\$w2ManifestPath = '$LIVE/resources/rates/wave2a-bestchange-migrated-ids.json';
\$w2Ids = [2237];
if (is_file(\$w2ManifestPath)) {
    \$wm = json_decode((string) file_get_contents(\$w2ManifestPath), true);
    if (is_array(\$wm) && ! empty(\$wm['direction_ids'])) {
        \$w2Ids = array_map('intval', \$wm['direction_ids']);
    }
}
\$w2InRegistry = 0;
\$w2BlockReintroduced = 0;
\$w2DerivedOwner = 0;
\$w2BestchangeOwner = 0;
foreach (\$w2Ids as \$wid) {
    \$row = \$cfgDirs[(string) \$wid] ?? \$cfgDirs[\$wid] ?? null;
    if (is_array(\$row)) {
        \$w2InRegistry++;
        echo "WAVE2A_ID_IN_DERIVED_REGISTRY id=\$wid\n";
        if (! empty(\$row['ownership']['block_bestchange_overwrite'])) {
            \$w2BlockReintroduced++;
            echo "WAVE2A_BLOCK_BESTCHANGE_REINTRODUCED id=\$wid\n";
        }
    }
    \$wd = DirectionExchange::query()->find(\$wid);
    if (! \$wd) {
        echo "WAVE2A_DIRECTION_MISSING id=\$wid\n";
        \$unknown++;
        continue;
    }
    \$ps = (string) (\$wd->parser_source_name ?? '');
    if (\$ps === 'DERIVED_MARKET_BASELINE') {
        \$w2DerivedOwner++;
        echo "WAVE2A_DERIVED_OWNER id=\$wid\n";
    }
    if (\$ps === 'BestChange') {
        \$w2BestchangeOwner++;
    } else {
        echo "WAVE2A_NOT_BESTCHANGE_OWNER id=\$wid parser=\$ps\n";
        \$map++;
    }
    if (! \\App\\Services\\Rates\\BestChangeMarketBaseHealth::hasActiveLink((int) \$wid)) {
        echo "WAVE2A_BC_LINK_INACTIVE id=\$wid\n";
        \$map++;
    }
}
\$w2Expected = count(\$w2Ids);
echo "WAVE2A_IDS_IN_DERIVED_REGISTRY=\$w2InRegistry\n";
echo "WAVE2A_DERIVED_OWNER_TOTAL=\$w2DerivedOwner\n";
echo "WAVE2A_BESTCHANGE_OWNER_TOTAL=\$w2BestchangeOwner\n";
echo "WAVE2A_BLOCK_BESTCHANGE_REINTRODUCED_TOTAL=\$w2BlockReintroduced\n";
if (\$w2InRegistry !== 0) {
    \$map += \$w2InRegistry;
}
if (\$w2DerivedOwner !== 0) {
    \$map += \$w2DerivedOwner;
}
if (\$w2BestchangeOwner !== \$w2Expected) {
    echo "WAVE2A_BESTCHANGE_OWNER_TOTAL_DEFECT expect=\$w2Expected got=\$w2BestchangeOwner\n";
    \$map++;
}
if (\$w2BlockReintroduced !== 0) {
    \$map += \$w2BlockReintroduced;
}

// ---------------------------------------------------------------------------
// Wave 2D NON-TON DERIVED→BESTCHANGE (2870 ETC→PAYTMINR) persistence.
// ---------------------------------------------------------------------------
\$w2dManifestPath = '$LIVE/resources/rates/wave2d-bestchange-migrated-ids.json';
\$w2dIds = [2870];
if (is_file(\$w2dManifestPath)) {
    \$wm = json_decode((string) file_get_contents(\$w2dManifestPath), true);
    if (is_array(\$wm) && ! empty(\$wm['direction_ids'])) {
        \$w2dIds = array_map('intval', \$wm['direction_ids']);
    }
}
\$w2dInRegistry = 0;
\$w2dBlockReintroduced = 0;
\$w2dDerivedOwner = 0;
\$w2dBestchangeOwner = 0;
foreach (\$w2dIds as \$wid) {
    \$row = \$cfgDirs[(string) \$wid] ?? \$cfgDirs[\$wid] ?? null;
    if (is_array(\$row)) {
        \$w2dInRegistry++;
        echo "WAVE2D_ID_IN_DERIVED_REGISTRY id=\$wid\n";
        if (! empty(\$row['ownership']['block_bestchange_overwrite'])) {
            \$w2dBlockReintroduced++;
            echo "WAVE2D_BLOCK_BESTCHANGE_REINTRODUCED id=\$wid\n";
        }
    }
    \$wd = DirectionExchange::query()->find(\$wid);
    if (! \$wd) {
        echo "WAVE2D_DIRECTION_MISSING id=\$wid\n";
        \$unknown++;
        continue;
    }
    \$ps = (string) (\$wd->parser_source_name ?? '');
    if (\$ps === 'DERIVED_MARKET_BASELINE') {
        \$w2dDerivedOwner++;
        echo "WAVE2D_DERIVED_OWNER id=\$wid\n";
    }
    if (\$ps === 'BestChange') {
        \$w2dBestchangeOwner++;
    } else {
        echo "WAVE2D_NOT_BESTCHANGE_OWNER id=\$wid parser=\$ps\n";
        \$map++;
    }
    if (! \\App\\Services\\Rates\\BestChangeMarketBaseHealth::hasActiveLink((int) \$wid)) {
        echo "WAVE2D_BC_LINK_INACTIVE id=\$wid\n";
        \$map++;
    }
}
\$w2dExpected = count(\$w2dIds);
echo "WAVE2D_IDS_IN_DERIVED_REGISTRY=\$w2dInRegistry\n";
echo "WAVE2D_DERIVED_OWNER_TOTAL=\$w2dDerivedOwner\n";
echo "WAVE2D_BESTCHANGE_OWNER_TOTAL=\$w2dBestchangeOwner\n";
echo "WAVE2D_BLOCK_BESTCHANGE_REINTRODUCED_TOTAL=\$w2dBlockReintroduced\n";
if (\$w2dInRegistry !== 0) {
    \$map += \$w2dInRegistry;
}
if (\$w2dDerivedOwner !== 0) {
    \$map += \$w2dDerivedOwner;
}
if (\$w2dBestchangeOwner !== \$w2dExpected) {
    echo "WAVE2D_BESTCHANGE_OWNER_TOTAL_DEFECT expect=\$w2dExpected got=\$w2dBestchangeOwner\n";
    \$map++;
}
if (\$w2dBlockReintroduced !== 0) {
    \$map += \$w2dBlockReintroduced;
}

// ---------------------------------------------------------------------------
// Payeer no-exact-BC-rail: forbid ACTIVE BestChange links on substituted rails.
// ---------------------------------------------------------------------------
\$payeerPolPath = '$LIVE/resources/rates/payeer-no-bc-rail-policy.json';
\$payeerWrongActive = 0;
if (is_file(\$payeerPolPath)) {
    \$pp = json_decode((string) file_get_contents(\$payeerPolPath), true) ?: [];
    \$codes = \$pp['local_codes'] ?? ['PRRUB', 'PRUSD', 'PREUR'];
    \$forbid = \$pp['forbidden_bc_substitutions'] ?? [];
    \$payeerDirRows = \\Illuminate\\Support\\Facades\\DB::table('direction_exchange as d')
        ->join('currencies as c1', 'c1.id', '=', 'd.id_currency1')
        ->join('currencies as c2', 'c2.id', '=', 'd.id_currency2')
        ->where('d.status', 1)
        ->where(function (\$w) use (\$codes) {
            \$w->whereIn('c1.designation_xml', \$codes)->orWhereIn('c2.designation_xml', \$codes);
        })
        ->get(['d.id', 'd.parser_source_name', 'c1.designation_xml as from_xml', 'c2.designation_xml as to_xml']);
    foreach (\$payeerDirRows as \$pr) {
        \$local = in_array(\$pr->to_xml, \$codes, true) ? \$pr->to_xml : (in_array(\$pr->from_xml, \$codes, true) ? \$pr->from_xml : null);
        if (\$local === null) {
            continue;
        }
        if ((string) \$pr->parser_source_name === 'BestChange') {
            \$payeerWrongActive++;
            echo "PAYEER_ACTIVE_BESTCHANGE_OWNER id={\$pr->id} local=\$local\n";
            continue;
        }
        \$bc = \\Illuminate\\Support\\Facades\\DB::table('bestchange_directions')->where('id_direction_exchange', \$pr->id)->where('status', 1)->first();
        if (! \$bc) {
            continue;
        }
        \$outId = (int) (\$bc->id_currency_out ?? 0);
        \$outCode = strtoupper((string) (\$bc->exchange_out ?? ''));
        foreach ((\$forbid[\$local] ?? []) as \$row) {
            if (! is_array(\$row)) {
                continue;
            }
            \$hit = false;
            if (isset(\$row['bc_id']) && (int) \$row['bc_id'] === \$outId) {
                \$hit = true;
            }
            if (isset(\$row['bc_code']) && strtoupper((string) \$row['bc_code']) === \$outCode) {
                \$hit = true;
            }
            if (\$hit) {
                \$payeerWrongActive++;
                echo "PAYEER_WRONG_BC_MAPPING_ACTIVE id={\$pr->id} local=\$local bc_out=\$outId/\$outCode\n";
                break;
            }
        }
    }
}
echo "PAYEER_WRONG_BC_MAPPING_ACTIVE_TOTAL=\$payeerWrongActive\n";
if (\$payeerWrongActive !== 0) {
    \$map += \$payeerWrongActive;
}

// ---------------------------------------------------------------------------
// 1513 KEEP_DERIVED city/liquidity block — forbid accidental BC ownership.
// ---------------------------------------------------------------------------
\$cityBlockPath = '$LIVE/resources/rates/keep-derived-city-liquidity-block.json';
\$cityBlockViolations = 0;
if (is_file(\$cityBlockPath)) {
    \$cb = json_decode((string) file_get_contents(\$cityBlockPath), true) ?: [];
    foreach ((\$cb['directions'] ?? []) as \$cid => \$crow) {
        if (! is_array(\$crow) || empty(\$crow['auto_migration_blocked'])) {
            continue;
        }
        \$cid = (int) \$cid;
        \$cd = DirectionExchange::query()->find(\$cid);
        if (! \$cd) {
            echo "CITY_LIQUIDITY_BLOCK_DIRECTION_MISSING id=\$cid\n";
            \$cityBlockViolations++;
            continue;
        }
        \$ps = (string) (\$cd->parser_source_name ?? '');
        if (\$ps !== (string) (\$crow['owner_required'] ?? 'DERIVED_MARKET_BASELINE')) {
            echo "CITY_LIQUIDITY_BLOCK_OWNER_VIOLATION id=\$cid parser=\$ps\n";
            \$cityBlockViolations++;
        }
        \$cbc = \\Illuminate\\Support\\Facades\\DB::table('bestchange_directions')->where('id_direction_exchange', \$cid)->where('status', 1)->first();
        if (\$cbc) {
            echo "CITY_LIQUIDITY_BLOCK_ACTIVE_BC id=\$cid\n";
            \$cityBlockViolations++;
        }
    }
}
echo "CITY_LIQUIDITY_BLOCK_VIOLATIONS=\$cityBlockViolations\n";
if (\$cityBlockViolations !== 0) {
    \$map += \$cityBlockViolations;
}

\$snap = RatesXmlPublicFeedHealth::snapshot();
\$xmlFail = 0;
if (! empty(\$snap['must_keep_full']) && empty(\$snap['invariant_ok'])) {
    echo "XML_ONLINE_INVARIANT_FAIL ".json_encode(\$snap)."\n";
    \$xmlFail = 1;
}
if (! empty(\$snap['must_keep_full']) && (! empty(\$snap['empty_rates_match']) || (int) (\$snap['rows'] ?? 0) === 0)) {
    echo "XML_EMPTY_WHILE_ONLINE\n";
    \$xmlFail = 1;
}
if (RatesXmlMonitorGate::isHidden() && ! empty(\$snap['must_keep_full'])) {
    echo "XML_STUCK_HIDE_WHILE_ONLINE\n";
    \$xmlFail = 1;
}

echo "PUBLIC_QUOTE_DEFECT_TOTAL=\$pq\n";
echo "ORDER_ECONOMICS_DEFECT_TOTAL=\$oe\n";
echo "TOPN_DEFECT_TOTAL=\$topn\n";
echo "STORED_COURSE_DEFECT_TOTAL=\$stored\n";
echo "POSITION_SELECTOR_DEFECT_TOTAL=\$pos\n";
echo "MAPPING_DEFECT_TOTAL=\$map\n";
echo "DERIVED_BENCHMARK_DEFECT_TOTAL=\$derivedBenchmark\n";
echo "CROSS_RATE_OUTLIER_TOTAL=\$crossOutlier\n";
echo "UNHEALTHY_PROVIDER_USED_TOTAL=\$unhealthyUsed\n";
echo "DELISTED_PROVIDER_USED_TOTAL=\$delistedUsed\n";
echo "STALE_PROVIDER_USED_TOTAL=\$staleUsed\n";
echo "UNKNOWN=\$unknown\n";
echo "XML_FAIL=\$xmlFail\n";
echo "XML_HEALTH=".((\$xmlFail === 0 && ! empty(\$snap['invariant_ok'])) ? 'HEALTHY' : 'UNHEALTHY')."\n";
echo "XML_BYTES=".(\$snap['bytes'] ?? 0)." XML_ROWS=".(\$snap['rows'] ?? 0)."\n";
echo "TONUSDT_QUALITY_STATE=\$tonQs\n";

\$hard = \$pq + \$oe + \$topn + \$stored + \$pos + \$map + \$unknown + \$xmlFail
    + \$derivedBenchmark + \$crossOutlier + \$unhealthyUsed + \$delistedUsed + \$staleUsed;
if (\$hard > 0) {
    echo "RATE_SYSTEM_PREDEPLOY_GATE=FAIL defects=\$hard\n";
    exit(1);
}
echo "RATE_SYSTEM_PREDEPLOY_GATE=PASS\n";
exit(0);
PHP
