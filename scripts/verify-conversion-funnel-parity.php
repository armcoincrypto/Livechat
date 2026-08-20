<?php

declare(strict_types=1);

/**
 * Read-only parity check: ConversionFunnelService vs direct SQL.
 * Run against production bootstrap as app user (no mutations).
 */

use App\Services\Analytics\ConversionCohortClassifier;
use iEXPackages\Analytics\Services\Exchanges\ConversionFunnelService;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = new ConversionFunnelService;
$failures = 0;

foreach (['24h', '7d', '30d'] as $period) {
    foreach ([null, 'REAL_CUSTOMER', 'TEST_CANARY'] as $cohort) {
        $report = $service->report($period, $cohort);
        [$from, $to] = $service->resolvePeriod($period);

        $cohortSql = ConversionCohortClassifier::sqlCaseExpression('t');
        $paidList = implode(',', ConversionCohortClassifier::PAYMENT_RECEIVED_OR_BEYOND);

        $sql = "
            SELECT COUNT(*) created,
              SUM(status IN ({$paidList})) paid,
              SUM(status=4) done,
              SUM(status=1) exp,
              SUM(status=6) cancel,
              SUM(session_attribution_id IS NOT NULL) attr
            FROM tasks t
            WHERE t.created_at BETWEEN ? AND ? AND t.deleted_at IS NULL
        ";
        $bindings = [$from->toDateTimeString(), $to->toDateTimeString()];
        if ($cohort !== null) {
            $sql .= " AND ({$cohortSql}) = ?";
            $bindings[] = $cohort;
        }
        $row = DB::selectOne($sql, $bindings);

        $checks = [
            'orders_created' => (int) $row->created,
            'orders_payment_received' => (int) $row->paid,
            'orders_completed' => (int) $row->done,
            'orders_expired' => (int) $row->exp,
            'orders_cancelled' => (int) $row->cancel,
            'orders_attributed' => (int) $row->attr,
        ];

        foreach ($checks as $key => $expected) {
            $got = (int) $report['totals'][$key];
            if ($got !== $expected) {
                $failures++;
                echo "FAIL period={$period} cohort=".($cohort ?? 'ALL')." {$key} got={$got} expected={$expected}\n";
            }
        }

        $sess = (int) DB::table('session_attributions')
            ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->count();
        if ((int) $report['sessions']['attributed_sessions'] !== $sess) {
            $failures++;
            echo "FAIL sessions period={$period}\n";
        }
    }
}

// Empty / invalid period
try {
    $service->report('90d');
    $failures++;
    echo "FAIL expected InvalidArgumentException for 90d\n";
} catch (InvalidArgumentException $e) {
    echo "OK invalid period rejected\n";
}

echo $failures === 0 ? "DASHBOARD_TOTALS_MATCH_SOURCE_OF_TRUTH\n" : "PARITY_FAILURES={$failures}\n";
exit($failures === 0 ? 0 : 1);
