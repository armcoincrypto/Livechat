<?php

declare(strict_types=1);

/**
 * P-BESTCHANGE-7/8: Post-fix referral capture regression checks.
 *
 * Usage: php scripts/verify-referral-capture-fix.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$failures = 0;
$fixDeployAt = '2026-06-24 19:00:00';

function check(bool $cond, string $msg): void
{
    global $failures;
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        $failures++;
    } else {
        echo "OK: {$msg}\n";
    }
}

echo "=== Resolver unit script ===\n";
passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../tests/Unit/ReferralCaptureResolverTest.php'), $resolverExit);
check($resolverExit === 0, 'ReferralCaptureResolverTest exit 0');

echo "\n=== nginx ref preservation ===\n";
$curlHeaders = shell_exec("curl -sI 'https://exswaping.com/ru/?ref=MLyn&cur_from=USDT' 2>/dev/null | head -1");
check(str_contains((string) $curlHeaders, '200'), 'curl returns HTTP 200 (ref not 301-stripped)');

echo "\n=== Latest tasks ===\n";
$latest = DB::select(
    'SELECT id, referral_hash, id_referral_link, created_at, status
     FROM tasks ORDER BY id DESC LIMIT 5'
);
foreach ($latest as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

$mlynAfterFix = DB::selectOne(
    "SELECT id, created_at, referral_hash, id_referral_link, status
     FROM tasks
     WHERE referral_hash = 'MLyn' AND created_at >= ?
     ORDER BY id DESC LIMIT 1",
    [$fixDeployAt]
);
check($mlynAfterFix !== null, 'Latest MLyn order exists after fix deploy time');
if ($mlynAfterFix) {
    check((int) $mlynAfterFix->id_referral_link === 436, 'Latest MLyn order id_referral_link = 436');
    echo "latest_mlyn_task_id={$mlynAfterFix->id}\n";
}

echo "\n=== referral_captured audit (latest MLyn task) ===\n";
if ($mlynAfterFix) {
    $audit = DB::selectOne(
        "SELECT id, event, task_id, meta, created_at FROM referral_audit_logs
         WHERE task_id = ? AND event = 'referral_captured' ORDER BY id DESC LIMIT 1",
        [$mlynAfterFix->id]
    );
    check($audit !== null, 'Audit referral_captured exists for latest MLyn order');
    if ($audit) {
        $meta = json_decode((string) ($audit->meta ?? ''), true);
        check(($meta['code'] ?? '') === 'MLyn', 'Audit meta.code = MLyn');
        check(in_array($meta['source'] ?? '', ['cookie', 'query', 'body', 'referer', 'user_relationship'], true), 'Audit source is allowed');
        echo json_encode($audit, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\n=== Safety: no fake commission since fix ===\n";
$newReferralLog = (int) DB::selectOne(
    'SELECT COUNT(*) AS c FROM referral_log WHERE created_at >= ?',
    [$fixDeployAt]
)->c;
check($newReferralLog === 0, 'referral_log only after completion (0 rows since fix deploy)');

$partner = DB::selectOne("SELECT user_id FROM referral_links WHERE code = 'MLyn' LIMIT 1");
if ($partner) {
    $bal = DB::selectOne(
        'SELECT balance, referral_total_profit, updated_at FROM user_balance WHERE id_user = ?',
        [$partner->user_id]
    );
    echo 'partner_balance=' . json_encode($bal) . "\n";
    check(
        $bal && ($bal->updated_at ?? '') < $fixDeployAt,
        'No direct balance mutation since fix deploy (updated_at unchanged)'
    );
}

echo "\n=== Task #2828 untouched ===\n";
$t2828 = DB::selectOne('SELECT id, status, referral_hash, is_pay_referral_bonus FROM tasks WHERE id = 2828');
check($t2828 !== null && (int) $t2828->status === 4, 'Task #2828 still status 4');
check((int) ($t2828->is_pay_referral_bonus ?? -1) === 0, 'Task #2828 is_pay_referral_bonus unchanged');

echo $failures === 0
    ? "\nAll verify-referral-capture-fix checks passed.\n"
    : "\n{$failures} check(s) failed.\n";

exit($failures === 0 ? 0 : 1);
