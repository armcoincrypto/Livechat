<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$taskId = (int) ($argv[1] ?? 0);

echo "=== Latest tasks (referral fields) ===\n";
foreach (DB::select(
    'SELECT id, created_at, status, referral_hash, id_referral_link, is_pay_referral_bonus, profit_partner FROM tasks ORDER BY id DESC LIMIT 5'
) as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

if ($taskId > 0) {
    echo "\n=== Task #{$taskId} ===\n";
    $task = DB::selectOne('SELECT * FROM tasks WHERE id = ?', [$taskId]);
    echo json_encode($task, JSON_UNESCAPED_UNICODE) . "\n";

    echo "\n=== referral_audit_logs for task ===\n";
    foreach (DB::select('SELECT * FROM referral_audit_logs WHERE task_id = ? ORDER BY id DESC', [$taskId]) as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }

    echo "\n=== referral_log for task ===\n";
    foreach (DB::select('SELECT * FROM referral_log WHERE id_task = ?', [$taskId]) as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

$partnerUserId = DB::selectOne('SELECT user_id FROM referral_links WHERE code = ? LIMIT 1', ['MLyn']);
if ($partnerUserId) {
    echo "\n=== BestChange user_balances (user_id={$partnerUserId->user_id}) ===\n";
    foreach (DB::select(
        'SELECT id, id_user, balance, referral_total_profit, referral_total_withdrawal, updated_at FROM user_balances WHERE id_user = ?',
        [$partnerUserId->user_id]
    ) as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
}
