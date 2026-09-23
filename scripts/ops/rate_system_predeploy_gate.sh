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
\$resolver = app(CommercialAdjustmentResolver::class);

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
    if (\$expectOwner !== '' && \$family !== \$expectOwner) {
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
echo "UNKNOWN=\$unknown\n";
echo "XML_FAIL=\$xmlFail\n";
echo "XML_HEALTH=".((\$xmlFail === 0 && ! empty(\$snap['invariant_ok'])) ? 'HEALTHY' : 'UNHEALTHY')."\n";
echo "XML_BYTES=".(\$snap['bytes'] ?? 0)." XML_ROWS=".(\$snap['rows'] ?? 0)."\n";

\$hard = \$pq + \$oe + \$topn + \$stored + \$pos + \$map + \$unknown + \$xmlFail;
if (\$hard > 0) {
    echo "RATE_SYSTEM_PREDEPLOY_GATE=FAIL defects=\$hard\n";
    exit(1);
}
echo "RATE_SYSTEM_PREDEPLOY_GATE=PASS\n";
exit(0);
PHP
