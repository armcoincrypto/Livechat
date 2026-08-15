#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * P-BESTCHANGE-3 Phase 4 — Safe single-task completion (dry-run by default).
 *
 * Uses normal path: TransactionFacade::find()->success(['skip_auto_payment' => true])
 * Same as PayPendingWithdrawalCommand::finalizeAsSuccess after outbound payout.
 *
 * Usage:
 *   php scripts/complete-bestchange-paid-task-safe.php --task-id=2858
 *   php scripts/complete-bestchange-paid-task-safe.php --task-id=2858 --execute
 */

require __DIR__ . '/lib/bestchange-recovery-common.php';

use App\Models\Task;
use iEXPackages\ReferralSystem\ReferralSystemFacade;
use iEXPackages\Transaction\Facades\TransactionFacade;

$root = dirname(__DIR__);
$args = bc_parse_args($argv ?? []);

if ($args['help'] || $args['task_id'] <= 0) {
    echo "Usage: php scripts/complete-bestchange-paid-task-safe.php --task-id=ID [--execute]\n";
    exit($args['help'] ? 0 : 1);
}

$taskId = $args['task_id'];
$env    = bc_load_env($root);
$pdo    = bc_pdo($env);

$row = bc_fetch_one($pdo, $taskId);
if ($row === null) {
    fwrite(STDERR, "Task {$taskId} not found.\n");
    exit(1);
}

$eval = bc_evaluate_candidate($row);
$balanceBefore = bc_partner_balance($pdo);

echo "=== BestChange Safe Task Completion ===\n";
echo 'Mode: ' . ($args['execute'] ? 'EXECUTE' : 'DRY-RUN') . "\n";
echo "Task ID: {$taskId}\n\n";

echo "--- Pre-checks ---\n";
foreach ($eval['reasons'] as $reason) {
    echo "  FAIL: {$reason}\n";
}
if ($eval['safe']) {
    echo "  PASS: all safety checks\n";
}

$give = (float) $row['give_price'];
$screenExpected = bc_screen_commission($give, (float) $row['profit_partner']);

echo "\n--- Task ---\n";
echo "  status: {$row['status']} (7=PAID)\n";
echo "  referral_hash: {$row['referral_hash']}\n";
echo "  pair: {$row['from_code']} -> {$row['to_code']}\n";
echo "  give: {$give} | receive: {$row['receiving_price']}\n";
echo "  profit_partner: {$row['profit_partner']}\n";
echo "  inbound payment: " . ($row['has_inbound_payment'] ? 'yes' : 'no') . "\n";
echo "  outbound payout record: " . ($row['has_outbound_payout'] ? 'yes' : 'no') . "\n";
echo "  screening expected commission (~30% of margin): {$screenExpected} USD\n";
echo "  partner balance before: " . ($balanceBefore['balance'] ?? 'n/a') . " USD\n";

if (!$eval['safe']) {
    echo "\nREFUSED: task fails safety checks. Not executing.\n";
    echo "Fix blockers first (typically: record outbound payout before completion).\n";
    exit(2);
}

if (!$args['execute']) {
    echo "\nDRY-RUN: would call TransactionFacade::find({$taskId})->success(['skip_auto_payment'=>true])\n";
    echo "Re-run with --execute after operator confirms outbound payout was sent.\n";
    exit(0);
}

echo "\n--- Executing via Laravel ---\n";

try {
    bc_bootstrap_laravel($root);

    $task = Task::query()->findOrFail($taskId);
    $preview = ReferralSystemFacade::previewForTask($task);
    $previewAmount = $preview->amount ?? null;

    echo 'Referral preview eligible: ' . ($preview->eligible ? 'yes' : 'no') . "\n";
    if (!$preview->eligible) {
        echo 'Referral preview message: ' . $preview->message . "\n";
        echo "STOP: not eligible for referral credit.\n";
        exit(3);
    }
    if ($previewAmount !== null) {
        echo "Referral preview amount: {$previewAmount} USD\n";
        if ($screenExpected > 0 && abs((float) $previewAmount - $screenExpected) / $screenExpected > 0.50) {
            echo "STOP: preview differs from screening by >50%. Aborting before success().\n";
            exit(3);
        }
    }

    $tx = TransactionFacade::find($taskId);
    $tx->success(['skip_auto_payment' => true]);

    echo "success() completed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'EXECUTE FAILED: ' . $e->getMessage() . "\n");
    exit(4);
}

echo "\n--- Post-verify (read-only) ---\n";
$after = bc_fetch_one($pdo, $taskId);
$balAfter = bc_partner_balance($pdo);
$log = bc_fetch_referral_log($pdo, $taskId);

echo '  task status: ' . ($after['status'] ?? '?') . "\n";
echo '  partner balance after: ' . ($balAfter['balance'] ?? 'n/a') . " USD\n";
if ($log) {
    echo "  referral_log id: {$log['id']}, bonus: {$log['bonus_number']} USD, status: {$log['status']}\n";
} else {
    echo "  referral_log: NOT CREATED — investigate ReferralBonusService\n";
}

exit(0);

function bc_fetch_one(PDO $pdo, int $taskId): ?array
{
    $sql = "
        SELECT
            t.id AS task_id, t.public_id, t.created_at, t.status, t.referral_hash,
            t.give_price, t.receiving_price, t.to_shot, t.is_spam, t.is_frozen,
            de.profit_partner, de.allow_autopay AS direction_allow_autopay,
            cc1.name AS from_code, cc2.name AS to_code,
            COALESCE(ti.is_freeze_scam, 0) AS is_freeze_scam,
            (mtd.id IS NOT NULL) AS has_inbound_payment,
            (ptd.id IS NOT NULL OR wh.id IS NOT NULL) AS has_outbound_payout,
            (rl.id IS NOT NULL) AS has_referral_log,
            rl.bonus_number AS actual_commission
        FROM tasks t
        JOIN direction_exchange de ON de.id = t.id_direction_exchange
        LEFT JOIN currencies c1 ON c1.id = de.id_currency1
        LEFT JOIN currencies c2 ON c2.id = de.id_currency2
        LEFT JOIN code_currency cc1 ON cc1.id = c1.id_code_currency
        LEFT JOIN code_currency cc2 ON cc2.id = c2.id_code_currency
        LEFT JOIN tasks_info ti ON ti.id_task = t.id
        LEFT JOIN merchants_transaction_data mtd ON mtd.id_task = t.id
        LEFT JOIN pays_transaction_data ptd ON ptd.id_task = t.id
        LEFT JOIN wallets_history wh ON wh.id_task = t.id
        LEFT JOIN referral_log rl ON rl.id_task = t.id AND rl.id_user = :partner_id
        WHERE t.id = :task_id AND t.referral_hash = :ref_hash
        LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    $st->execute([
        'task_id' => $taskId,
        'partner_id' => BC_PARTNER_USER_ID,
        'ref_hash' => BC_REFERRAL_HASH,
    ]);
    $row = $st->fetch();
    return $row ?: null;
}

function bc_fetch_referral_log(PDO $pdo, int $taskId): ?array
{
    $st = $pdo->prepare('SELECT id, bonus_number, status, created_at FROM referral_log WHERE id_task = ? AND id_user = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$taskId, BC_PARTNER_USER_ID]);
    $row = $st->fetch();
    return $row ?: null;
}
