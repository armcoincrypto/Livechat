<?php

declare(strict_types=1);

/**
 * P-BESTCHANGE-9: Governed manual partner balance adjustment.
 *
 * Uses the same ledger pattern as Administrator\UserController::balances():
 *   1) user_balance_log row (from_balance / to_balance)
 *   2) referral_audit_logs row (manual_partner_adjustment)
 *   3) user_balance.balance set to ledger to_balance (not raw increment SQL)
 *
 * Usage:
 *   php scripts/bestchange-manual-partner-adjustment.php --dry-run
 *   php scripts/bestchange-manual-partner-adjustment.php --apply
 *   php scripts/bestchange-manual-partner-adjustment.php --amount=67.00 --reason="..." --apply
 *   php scripts/bestchange-manual-partner-adjustment.php --reverse --log-id=<user_balance_log.id> --apply
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\UserBalance;
use App\Models\UserBalanceLog;
use iEXPackages\ReferralSystem\Models\ReferralLink;
use iEXPackages\ReferralSystem\Services\ReferralAuditLogger;
use iEXPackages\ReferralSystem\Support\ReferralAuditEvent;

const PARTNER_USER_ID = 436;
const PARTNER_CODE = 'MLyn';
const DEFAULT_ADJUSTMENT_AMOUNT = 700.00;
const DEFAULT_ADJUSTMENT_REASON = 'BestChange referral attribution incident correction';
const APPROVED_BY_MANAGER_ID = 7; // admin2@exswaping.com — prior balance log author

$dryRun = in_array('--dry-run', $argv, true);
$apply = in_array('--apply', $argv, true);
$reverse = in_array('--reverse', $argv, true);
$reverseLogId = 0;
$adjustmentAmount = DEFAULT_ADJUSTMENT_AMOUNT;
$adjustmentReason = DEFAULT_ADJUSTMENT_REASON;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--log-id=')) {
        $reverseLogId = (int) substr($arg, strlen('--log-id='));
    }
    if (str_starts_with($arg, '--amount=')) {
        $adjustmentAmount = round((float) substr($arg, strlen('--amount=')), 2);
    }
    if (str_starts_with($arg, '--reason=')) {
        $adjustmentReason = substr($arg, strlen('--reason='));
    }
}

if ($adjustmentAmount <= 0) {
    echo "Amount must be > 0 (use --amount=)\n";
    exit(2);
}

$eventKey = sprintf(
    'manual_partner_adjustment:%s:%s:P-BESTCHANGE-9',
    PARTNER_CODE,
    number_format($adjustmentAmount, 2, '.', '')
);

if (!$dryRun && !$apply) {
    echo "Specify --dry-run or --apply\n";
    exit(2);
}

function snapshotPartner(int $userId): array
{
    $balance = UserBalance::query()->where('id_user', $userId)->first();
    $link = ReferralLink::query()->where('user_id', $userId)->first();
    $referralLogCount = (int) DB::table('referral_log')->where('id_user', $userId)->count();
    $latestLedger = UserBalanceLog::query()->where('id_user', $userId)->orderByDesc('id')->first();

    return [
        'user_id' => $userId,
        'email' => User::query()->where('id', $userId)->value('email'),
        'referral_code' => $link?->code,
        'referral_link_id' => $link?->id,
        'balance' => (float) ($balance?->balance ?? 0),
        'hold_balance' => (float) ($balance?->hold_balance ?? 0),
        'referral_total_profit' => (float) ($balance?->referral_total_profit ?? 0),
        'referral_total_withdrawal' => (float) ($balance?->referral_total_withdrawal ?? 0),
        'currency_id' => (int) ($balance?->id_code_currency ?? iEXSetting('id_referral_code_currency')),
        'referral_log_count' => $referralLogCount,
        'latest_ledger_log_id' => $latestLedger?->id,
    ];
}

function partnerCabinetBalance(int $userId): float
{
    return (float) User::with('user_balance')->find($userId)?->user_balance?->balance ?? 0;
}

echo "=== BEFORE ===\n";
$before = snapshotPartner(PARTNER_USER_ID);
echo json_encode($before, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

if ($reverse) {
    if ($reverseLogId <= 0) {
        echo "Reverse requires --log-id=<user_balance_log.id>\n";
        exit(2);
    }
    runReverse($reverseLogId, $dryRun, $before);
    exit(0);
}

// Idempotency: skip if this event_key already applied
$existingAudit = DB::table('referral_audit_logs')
    ->where('event', ReferralAuditEvent::MANUAL_PARTNER_ADJUSTMENT)
    ->where('partner_user_id', PARTNER_USER_ID)
    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.event_key')) = ?", [$eventKey])
    ->first();

if ($existingAudit) {
    echo "\nSKIP: Adjustment already recorded (audit id {$existingAudit->id}, event_key {$eventKey})\n";
    exit(0);
}

$currentBalance = $before['balance'];
$newBalance = round($currentBalance + $adjustmentAmount, 2);
$logText = sprintf(
    '[manual_partner_adjustment] +%.2f USD — %s (approved_by admin id=%d, ticket P-BESTCHANGE-9)',
    $adjustmentAmount,
    $adjustmentReason,
    APPROVED_BY_MANAGER_ID
);

echo "\n=== PLANNED ADJUSTMENT ===\n";
echo json_encode([
    'amount' => $adjustmentAmount,
    'currency' => 'USD',
    'type' => 'manual_partner_adjustment',
    'reason' => $adjustmentReason,
    'approved_by_manager_id' => APPROVED_BY_MANAGER_ID,
    'from_balance' => $currentBalance,
    'to_balance' => $newBalance,
    'event_key' => $eventKey,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

if ($dryRun) {
    echo "\nDRY-RUN: no changes written.\n";
    exit(0);
}

$ledgerLogId = null;
$auditLogId = null;

DB::transaction(function () use ($currentBalance, $newBalance, $logText, $adjustmentAmount, $adjustmentReason, $eventKey, &$ledgerLogId, &$auditLogId): void {
    /** @var UserBalance $balanceRow */
    $balanceRow = UserBalance::query()
        ->where('id_user', PARTNER_USER_ID)
        ->lockForUpdate()
        ->firstOrFail();

    $fromBalance = (float) $balanceRow->balance;
    if (abs($fromBalance - $currentBalance) > 0.0001) {
        throw new RuntimeException("Balance changed since snapshot: expected {$currentBalance}, found {$fromBalance}");
    }

    $toBalance = round($fromBalance + $adjustmentAmount, 2);

    // 1) Ledger row first
    $ledger = UserBalanceLog::create([
        'id_manager' => APPROVED_BY_MANAGER_ID,
        'id_user' => PARTNER_USER_ID,
        'route_type' => 1,
        'type' => 1, // increase
        'text' => $logText,
        'from_balance' => (string) $fromBalance,
        'to_balance' => (string) $toBalance,
    ]);
    $ledgerLogId = $ledger->id;

    // 2) Referral audit row
    app(ReferralAuditLogger::class)->info(
        ReferralAuditEvent::MANUAL_PARTNER_ADJUSTMENT,
        'Manual partner balance adjustment (goodwill / correction)',
        [
            'partner_user_id' => PARTNER_USER_ID,
            'referral_link_id' => (int) ReferralLink::query()->where('user_id', PARTNER_USER_ID)->value('id'),
        ],
        [
            'event_key' => $eventKey,
            'amount' => $adjustmentAmount,
            'currency' => 'USD',
            'reason' => $adjustmentReason,
            'approved_by' => 'admin',
            'approved_by_manager_id' => APPROVED_BY_MANAGER_ID,
            'from_balance' => $fromBalance,
            'to_balance' => $toBalance,
            'user_balance_log_id' => $ledgerLogId,
            'reversible' => true,
        ],
    );
    $auditLogId = (int) DB::table('referral_audit_logs')
        ->where('event', ReferralAuditEvent::MANUAL_PARTNER_ADJUSTMENT)
        ->where('partner_user_id', PARTNER_USER_ID)
        ->orderByDesc('id')
        ->value('id');

    // 3) Apply balance from ledger to_balance (not increment SQL)
    $balanceRow->update(['balance' => $toBalance]);
});

echo "\n=== APPLIED ===\n";
echo "user_balance_log.id={$ledgerLogId}\n";
echo "referral_audit_logs.id={$auditLogId}\n";

$after = snapshotPartner(PARTNER_USER_ID);
echo "\n=== AFTER ===\n";
echo json_encode($after, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
echo 'partner_cabinet_balance=' . partnerCabinetBalance(PARTNER_USER_ID) . "\n";

$newReferralLogs = (int) DB::table('referral_log')
    ->where('id_user', PARTNER_USER_ID)
    ->where('created_at', '>=', now()->subMinute())
    ->count();
echo "new_referral_log_rows_last_minute={$newReferralLogs}\n";

function runReverse(int $logId, bool $dryRun, array $before): void
{
    $origin = UserBalanceLog::query()->find($logId);
    if (!$origin || (int) $origin->id_user !== PARTNER_USER_ID) {
        throw new RuntimeException("Ledger log #{$logId} not found for partner " . PARTNER_USER_ID);
    }

    $reversalKey = 'reverse:user_balance_log:' . $logId;
    $exists = DB::table('referral_audit_logs')
        ->where('event', ReferralAuditEvent::MANUAL_PARTNER_ADJUSTMENT_REVERSED)
        ->where('partner_user_id', PARTNER_USER_ID)
        ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.reversal_of_user_balance_log_id')) = ?", [(string) $logId])
        ->exists();

    if ($exists) {
        echo "Reversal already applied for ledger log #{$logId}\n";
        return;
    }

    $current = (float) UserBalance::query()->where('id_user', PARTNER_USER_ID)->value('balance');
    $amount = round((float) $origin->to_balance - (float) $origin->from_balance, 2);
    $newBalance = round($current - $amount, 2);

    echo "\n=== PLANNED REVERSAL of ledger #{$logId} ===\n";
    echo json_encode([
        'amount' => -$amount,
        'from_balance' => $current,
        'to_balance' => $newBalance,
    ], JSON_PRETTY_PRINT) . "\n";

    if ($dryRun) {
        echo "DRY-RUN reversal\n";
        return;
    }

    DB::transaction(function () use ($origin, $logId, $current, $newBalance, $amount, $reversalKey): void {
        $balanceRow = UserBalance::query()->where('id_user', PARTNER_USER_ID)->lockForUpdate()->firstOrFail();
        $fromBalance = (float) $balanceRow->balance;

        $ledger = UserBalanceLog::create([
            'id_manager' => APPROVED_BY_MANAGER_ID,
            'id_user' => PARTNER_USER_ID,
            'route_type' => 1,
            'type' => 0,
            'text' => "[manual_partner_adjustment_reversed] -{$amount} USD — reversal of log #{$logId}",
            'from_balance' => (string) $fromBalance,
            'to_balance' => (string) $newBalance,
        ]);

        app(ReferralAuditLogger::class)->info(
            ReferralAuditEvent::MANUAL_PARTNER_ADJUSTMENT_REVERSED,
            'Reversal of manual partner balance adjustment',
            ['partner_user_id' => PARTNER_USER_ID],
            [
                'event_key' => $reversalKey,
                'reversal_of_user_balance_log_id' => $logId,
                'amount' => -$amount,
                'from_balance' => $fromBalance,
                'to_balance' => $newBalance,
                'user_balance_log_id' => $ledger->id,
            ],
        );

        $balanceRow->update(['balance' => $newBalance]);
    });

    echo "Reversal applied.\n";
}
