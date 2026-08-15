#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * P-BESTCHANGE-3 Phase 2 — Read-only dry-run for status-7 BestChange backlog.
 *
 * Usage: php scripts/dry-run-bestchange-autopay-backlog.php
 */

require __DIR__ . '/lib/bestchange-recovery-common.php';

$root = dirname(__DIR__);
$env  = bc_load_env($root);
$pdo  = bc_pdo($env);

$rows = bc_fetch_status7_tasks($pdo);

$totals = [
    'total_status_7' => count($rows),
    'total_profit_partner_gt_0' => 0,
    'total_safe_candidates' => 0,
    'expected_commission_screening' => 0.0,
    'excluded_by_reason' => [],
];

$candidates = [];

foreach ($rows as $row) {
    $profitPartner = (float) $row['profit_partner'];
    if ($profitPartner > 0) {
        $totals['total_profit_partner_gt_0']++;
    }

    $eval = bc_evaluate_candidate($row);
    $give = (float) $row['give_price'];
    $expected = bc_screen_commission($give, $profitPartner);

    $line = [
        'task_id' => (int) $row['task_id'],
        'public_id' => $row['public_id'],
        'created_at' => $row['created_at'],
        'status' => (int) $row['status'],
        'from_code' => $row['from_code'],
        'to_code' => $row['to_code'],
        'give_price' => $give,
        'receiving_price' => (float) $row['receiving_price'],
        'profit_partner' => $profitPartner,
        'expected_partner_commission_screening' => $expected,
        'has_existing_referral_log' => (bool) $row['has_referral_log'],
        'has_inbound_payment' => (bool) $row['has_inbound_payment'],
        'has_outbound_payout' => (bool) $row['has_outbound_payout'],
        'direction_allow_autopay' => (int) $row['direction_allow_autopay'],
        'safe_to_complete' => $eval['safe'] ? 'yes' : 'no',
        'reason_if_no' => $eval['safe'] ? '' : implode('; ', $eval['reasons']),
    ];

    if (!$eval['safe']) {
        foreach ($eval['reasons'] as $reason) {
            $totals['excluded_by_reason'][$reason] = ($totals['excluded_by_reason'][$reason] ?? 0) + 1;
        }
    } else {
        $totals['total_safe_candidates']++;
        $totals['expected_commission_screening'] += $expected;
    }

    $candidates[] = $line;
}

$autopay = q_setting($pdo, 'is_enabled_autopay_cron');
$balance = bc_partner_balance($pdo);

echo "=== BestChange Autopay Backlog DRY-RUN ===\n";
echo 'Generated: ' . gmdate('c') . "\n\n";

echo "--- Settings ---\n";
echo 'is_enabled_autopay_cron: ' . json_encode($autopay) . "\n";
echo 'partner balance (USD): ' . ($balance['balance'] ?? 'n/a') . "\n";
echo 'partner referral_total_profit: ' . ($balance['referral_total_profit'] ?? 'n/a') . "\n\n";

echo "--- Totals ---\n";
foreach ($totals as $k => $v) {
    if ($k === 'excluded_by_reason') {
        continue;
    }
    echo str_pad($k, 42) . (is_float($v) ? number_format($v, 2, '.', '') : $v) . "\n";
}
echo "\n--- Excluded counts by reason ---\n";
if ($totals['excluded_by_reason'] === []) {
    echo "(none)\n";
} else {
    arsort($totals['excluded_by_reason']);
    foreach ($totals['excluded_by_reason'] as $reason => $cnt) {
        echo "  [{$cnt}] {$reason}\n";
    }
}

echo "\n--- Safe candidates (all) ---\n";
$safe = array_filter($candidates, static fn ($c) => $c['safe_to_complete'] === 'yes');
if ($safe === []) {
    echo "NONE — do not run --execute completion until outbound payout is verified.\n";
} else {
    printf("%-8s %-20s %-8s %-8s %12s %12s\n", 'TASK', 'CREATED', 'FROM', 'TO', 'GIVE', 'EXPECTED~');
    foreach ($safe as $c) {
        printf(
            "%-8d %-20s %-8s %-8s %12.2f %12.2f\n",
            $c['task_id'],
            substr($c['created_at'], 0, 19),
            $c['from_code'],
            $c['to_code'],
            $c['give_price'],
            $c['expected_partner_commission_screening']
        );
    }
}

echo "\n--- Sample stuck tasks (first 15 profitable, unsafe) ---\n";
printf(
    "%-8s %-20s %-8s %10s %8s %-8s inbound outbound safe reason\n",
    'TASK', 'CREATED', 'FROM', 'GIVE', 'PROF%', 'AUTO'
);
$shown = 0;
foreach ($candidates as $c) {
    if ($c['profit_partner'] <= 0 || $c['safe_to_complete'] === 'yes') {
        continue;
    }
    printf(
        "%-8d %-20s %-8s %10.2f %8.2f %-8d %s      %s      %s %s\n",
        $c['task_id'],
        substr($c['created_at'], 0, 19),
        $c['from_code'],
        $c['give_price'],
        $c['profit_partner'],
        $c['direction_allow_autopay'],
        $c['has_inbound_payment'] ? 'Y' : 'N',
        $c['has_outbound_payout'] ? 'Y' : 'N',
        $c['safe_to_complete'],
        substr($c['reason_if_no'], 0, 60)
    );
    if (++$shown >= 15) {
        break;
    }
}

echo "\n--- STOP recommendation ---\n";
if ($totals['total_safe_candidates'] === 0) {
    echo "STOP: Zero safe completion candidates. Outbound payout not recorded on any status-7 task.\n";
    echo "Do NOT call success(skip_auto_payment) until operators confirm fiat/crypto outbound was sent.\n";
    echo "Enabling global autopay will NOT help directions with allow_autopay=0 (all sampled tasks).\n";
} else {
    echo "Proceed with single-task certification on oldest safe candidate.\n";
}

echo "\nDone (read-only).\n";

function q_setting(PDO $pdo, string $key): mixed
{
    $st = $pdo->prepare('SELECT value FROM dynamic_config_settings WHERE `key` = ? LIMIT 1');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    if ($v === false) {
        return null;
    }
    $decoded = json_decode((string) $v, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $v;
}
