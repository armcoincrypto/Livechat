#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * P-BESTCHANGE-3 Phase 5 — Batch safe completion (dry-run by default).
 *
 * Usage:
 *   php scripts/complete-bestchange-paid-backlog-safe.php --limit=5
 *   php scripts/complete-bestchange-paid-backlog-safe.php --limit=5 --execute
 */

require __DIR__ . '/lib/bestchange-recovery-common.php';

$root = dirname(__DIR__);
$args = bc_parse_args($argv ?? []);

if ($args['help']) {
    echo "Usage: php scripts/complete-bestchange-paid-backlog-safe.php [--limit=N] [--execute]\n";
    exit(0);
}

$env = bc_load_env($root);
$pdo = bc_pdo($env);

$rows = bc_fetch_status7_tasks($pdo);
$balanceBefore = bc_partner_balance($pdo);

$report = [];
$processed = 0;
$limit = $args['limit'];

echo "=== BestChange Paid Backlog Batch ===\n";
echo 'Mode: ' . ($args['execute'] ? 'EXECUTE' : 'DRY-RUN') . "\n";
echo "Limit: {$limit}\n";
echo 'Partner balance before: ' . ($balanceBefore['balance'] ?? 'n/a') . " USD\n\n";

foreach ($rows as $row) {
    if ($processed >= $limit) {
        break;
    }
    $eval = bc_evaluate_candidate($row);
    if (!$eval['safe']) {
        continue;
    }

    $taskId = (int) $row['task_id'];
    $expected = bc_screen_commission((float) $row['give_price'], (float) $row['profit_partner']);

    $entry = [
        'task_id' => $taskId,
        'created_at' => $row['created_at'],
        'expected_screening' => $expected,
        'status_before' => 7,
        'status_after' => null,
        'actual_commission' => null,
        'result' => 'pending',
        'error' => '',
    ];

    if (!$args['execute']) {
        $entry['result'] = 'dry-run';
        $report[] = $entry;
        $processed++;
        echo "DRY-RUN would complete task {$taskId}, expected~ {$expected} USD\n";
        continue;
    }

    $phpBin = is_executable('/usr/bin/php8.4') ? '/usr/bin/php8.4' : PHP_BINARY;
    $cmd = $phpBin . ' ' . escapeshellarg($root . '/scripts/complete-bestchange-paid-task-safe.php')
        . ' --task-id=' . $taskId . ' --execute 2>&1';
    exec($cmd, $output, $code);

    $entry['result'] = $code === 0 ? 'ok' : 'failed';
    $entry['error'] = implode("\n", $output);

    $after = $pdo->query("SELECT status FROM tasks WHERE id = {$taskId}")->fetchColumn();
    $entry['status_after'] = $after !== false ? (int) $after : null;

    $log = $pdo->query('SELECT bonus_number FROM referral_log WHERE id_task = ' . $taskId . ' AND id_user = ' . BC_PARTNER_USER_ID . ' ORDER BY id DESC LIMIT 1')->fetchColumn();
    $entry['actual_commission'] = $log !== false ? (float) $log : null;

    if ($entry['status_after'] !== 4 || $entry['actual_commission'] === null) {
        echo "STOP: task {$taskId} verification failed.\n";
        $report[] = $entry;
        break;
    }

    if ($expected > 0 && abs($entry['actual_commission'] - $expected) / $expected > 0.50) {
        echo "STOP: commission mismatch on task {$taskId} (expected~ {$expected}, actual {$entry['actual_commission']}).\n";
        $entry['result'] = 'mismatch';
        $report[] = $entry;
        break;
    }

    echo "OK task {$taskId}: status=4, commission={$entry['actual_commission']} USD\n";
    $report[] = $entry;
    $processed++;
}

$csvPath = $root . '/storage/audits/bestchange-backlog-completion-' . gmdate('Ymd-His') . '.csv';
@mkdir(dirname($csvPath), 0755, true);
$fh = fopen($csvPath, 'w');
fputcsv($fh, ['task_id', 'created_at', 'expected_screening', 'status_before', 'status_after', 'actual_commission', 'result', 'error']);
foreach ($report as $r) {
    fputcsv($fh, [
        $r['task_id'], $r['created_at'], $r['expected_screening'], $r['status_before'],
        $r['status_after'], $r['actual_commission'], $r['result'], $r['error'],
    ]);
}
fclose($fh);

$balanceAfter = bc_partner_balance($pdo);
echo "\nProcessed: {$processed}\n";
echo 'Partner balance after: ' . ($balanceAfter['balance'] ?? 'n/a') . " USD\n";
echo "Report: {$csvPath}\n";

if ($processed === 0 && !$args['execute']) {
    echo "\nNo safe candidates in batch window. Run dry-run-bestchange-autopay-backlog.php first.\n";
    exit(2);
}

exit(0);
