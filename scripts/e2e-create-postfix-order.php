<?php

declare(strict_types=1);

/**
 * P-BESTCHANGE-8: Create a post-fix test order with MLyn attribution via the real order API path.
 * Simulates incognito landing (?ref=MLyn) + order POST with ref fallback.
 *
 * Usage: php scripts/e2e-create-postfix-order.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;

$ts = time();
$email = "bestchange-e2e-{$ts}@mailinator.com";

$beforeMaxId = (int) DB::table('tasks')->max('id');

// Simulate landing with ?ref=MLyn (cookie capture middleware on web stack).
$startReq = Request::create('/client-api/v1/start?ref=MLyn', 'GET', ['ref' => 'MLyn']);
$startReq->headers->set('Accept', 'application/json');
$startReq->headers->set('Host', 'app.exswaping.com');
$startResp = $app->handle($startReq);
$setCookie = $startResp->headers->get('Set-Cookie', '');
preg_match('/ref=([^;]+)/', (string) $setCookie, $cookieMatch);
$refCookie = $cookieMatch[1] ?? '';

// Minimum direction from task #3836 pattern: USDT (3) -> KZT Kaspibank (66), min 200 USDT.
$payload = [
    'ref' => 'MLyn',
    'income_payment_system' => 3,
    'outcome_payment_system' => 66,
    'income_amount' => 200,
    'outcome_amount' => 97700,
    'email' => $email,
    'outcome_account' => '4400430000000001',
    'agree_rules' => 1,
    'fields_in' => [
        'income_outcome_income_vas_telegramm_whatsapp_1' => '@bestchange_e2e_test',
    ],
    'fields_out' => [
        'outcome_nomer_karty' => '4400430000000001',
        'sender_fullname' => 'Иванов Иван Иванович',
    ],
];

$orderReq = Request::create('/client-api/v1/order', 'POST', $payload, [], [], [
    'HTTP_ACCEPT' => 'application/json',
    'HTTP_REFERER' => 'https://exswaping.com/ru/?ref=MLyn',
    'HTTP_HOST' => 'app.exswaping.com',
    'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
]);
if ($refCookie !== '') {
    $orderReq->cookies->set('ref', urldecode($refCookie));
}

$orderResp = $app->handle($orderReq);
$status = $orderResp->getStatusCode();
$body = json_decode((string) $orderResp->getContent(), true);

echo "HTTP status: {$status}\n";
echo "Response: " . json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
echo "Start Set-Cookie ref present: " . ($refCookie !== '' ? 'yes' : 'no') . "\n";

$afterMaxId = (int) DB::table('tasks')->max('id');
if ($afterMaxId <= $beforeMaxId) {
    echo "\nFAIL: No new task row created.\n";
    exit(1);
}

$task = DB::selectOne(
    'SELECT id, created_at, status, referral_hash, id_referral_link, is_pay_referral_bonus, give_price
     FROM tasks WHERE id = ?',
    [$afterMaxId]
);

echo "\n=== New task ===\n";
echo json_encode($task, JSON_UNESCAPED_UNICODE) . "\n";

$audit = DB::select(
    'SELECT id, event, task_id, referral_link_id, meta, message, created_at
     FROM referral_audit_logs WHERE task_id = ? ORDER BY id DESC',
    [$afterMaxId]
);

echo "\n=== referral_audit_logs ===\n";
if ($audit === []) {
    echo "(none)\n";
} else {
    foreach ($audit as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

$ok = ($task->referral_hash ?? '') === 'MLyn'
    && (int) ($task->id_referral_link ?? 0) === 436;

$captured = false;
foreach ($audit as $row) {
    if ($row->event === 'referral_captured') {
        $meta = json_decode((string) ($row->meta ?? ''), true);
        $captured = ($meta['code'] ?? '') === 'MLyn';
    }
}

echo $ok ? "\nAttribution OK\n" : "\nAttribution FAIL\n";
echo $captured ? "Audit referral_captured OK\n" : "Audit referral_captured MISSING\n";

exit($ok && $captured ? 0 : 1);
