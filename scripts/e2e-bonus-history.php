<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== MLyn orders with is_pay_referral_bonus=1 ===\n";
foreach (DB::select(
    "SELECT id, status, is_pay_referral_bonus, give_price, created_at FROM tasks
     WHERE referral_hash = 'MLyn' AND is_pay_referral_bonus = 1 ORDER BY id DESC LIMIT 5"
) as $row) {
    echo json_encode($row) . "\n";
}

echo "\n=== Latest referral_log for MLyn ===\n";
foreach (DB::select(
    "SELECT rl.* FROM referral_log rl
     JOIN tasks t ON t.id = rl.id_task
     WHERE t.referral_hash = 'MLyn' ORDER BY rl.id DESC LIMIT 3"
) as $row) {
    echo json_encode($row) . "\n";
}

$partner = DB::selectOne("SELECT user_id FROM referral_links WHERE code = 'MLyn' LIMIT 1");
if ($partner) {
    echo "\n=== BestChange user_balances ===\n";
    foreach (DB::select(
        'SELECT id, id_user, balance, referral_total_profit, referral_total_withdrawal, updated_at
         FROM user_balances WHERE id_user = ?',
        [$partner->user_id]
    ) as $row) {
        echo json_encode($row) . "\n";
    }
}
