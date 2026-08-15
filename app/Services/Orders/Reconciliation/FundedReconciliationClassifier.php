<?php

declare(strict_types=1);

namespace App\Services\Orders\Reconciliation;

/**
 * Pure classification. Status labels are never treated as financial proof.
 */
final class FundedReconciliationClassifier
{
    public const CONFIRMED_FUNDS = 'CONFIRMED_FUNDS';

    public const STRONG_PAYMENT_EVIDENCE = 'STRONG_PAYMENT_EVIDENCE';

    public const CUSTOMER_CLAIM_ONLY = 'CUSTOMER_CLAIM_ONLY';

    public const NO_EVIDENCE = 'NO_EVIDENCE';

    public const AMBIGUOUS = 'AMBIGUOUS';

    public static function inboundStrength(array $row): string
    {
        $merchant = (int) ($row['has_merchant'] ?? 0) === 1;
        $wallet = (int) ($row['has_wallet'] ?? 0) === 1;
        $txid = trim((string) ($row['wallet_txid'] ?? '')) !== '';
        $webhook = (int) ($row['has_webhook'] ?? 0) === 1;
        $inAmount = is_numeric($row['in_amount_merchant'] ?? null) && (float) $row['in_amount_merchant'] > 0;

        // merchants_transaction_data is created at checkout/invoice — not payment proof.
        if ($wallet && $txid) {
            return self::CONFIRMED_FUNDS;
        }
        if ($wallet || $webhook || $inAmount) {
            return self::STRONG_PAYMENT_EVIDENCE;
        }
        if ((int) ($row['status'] ?? 0) === 3) {
            return self::CUSTOMER_CLAIM_ONLY;
        }

        return self::NO_EVIDENCE;
    }

    public static function classification(array $row): string
    {
        $status = (int) ($row['status'] ?? 0);
        $strength = self::inboundStrength($row);
        $confirmed = $strength === self::CONFIRMED_FUNDS;
        $outbound = (int) ($row['has_pays'] ?? 0) === 1
            || (int) ($row['has_autosender'] ?? 0) === 1
            || (int) ($row['has_wallets_history'] ?? 0) === 1;
        $duplicate = (int) ($row['duplicate_inbound'] ?? 0) === 1;
        $ageHours = (int) ($row['age_hours'] ?? 0);
        $bot = (int) ($row['is_bot'] ?? 0) === 1;

        if ($duplicate && $confirmed) {
            return 'MANUAL_REVIEW';
        }
        if ($outbound && $status !== 4) {
            return 'SETTLED_STATUS_STALE';
        }
        if ($status === 4) {
            if (!empty($row['manual_settlement'])) {
                return 'COMPLETED_CONSISTENT';
            }

            return 'HISTORICAL_LEGACY_COMPLETE';
        }
        if ($confirmed) {
            if ($status === 1) {
                return 'EXPIRED_WITH_FUNDS';
            }
            if ($status === 7 && $bot) {
                return 'BOT_FLAGGED_BUT_FUNDED';
            }
            if ($status === 7) {
                return 'FUNDED_AWAITING_MANUAL_SETTLEMENT';
            }

            return 'FUNDED_WRONG_STATUS';
        }
        if ($strength === self::STRONG_PAYMENT_EVIDENCE) {
            return 'PAYMENT_EVIDENCE_UNCONFIRMED';
        }
        if ($status === 2) {
            return 'WAITING_PAYMENT';
        }
        if ($status === 1) {
            return 'EXPIRED_NO_FUNDS';
        }
        if ($status === 3) {
            if ($ageHours >= 72) {
                return 'EXPIRED_NO_FUNDS';
            }

            return 'CUSTOMER_CLAIM_ONLY';
        }

        return 'AMBIGUOUS';
    }

    public static function waitingHandleDetail(array $row): ?string
    {
        if ((int) ($row['status'] ?? 0) !== 3) {
            return null;
        }
        $strength = self::inboundStrength($row);
        if ($strength === self::CONFIRMED_FUNDS) {
            if (self::amountMismatch($row)) {
                return 'AMOUNT_MISMATCH';
            }
            if (self::isLatePayment($row)) {
                return 'LATE_PAYMENT';
            }

            return 'CONFIRMED_FUNDS_WRONG_STATUS';
        }
        if ($strength === self::STRONG_PAYMENT_EVIDENCE) {
            return 'STRONG_EVIDENCE_NEEDS_CONFIRMATION';
        }
        if ((int) ($row['age_hours'] ?? 0) >= 72) {
            return 'EXPIRED_CANDIDATE_NO_FUNDS';
        }

        return 'CUSTOMER_CLAIM_ONLY';
    }

    public static function recommendedAction(string $classification): string
    {
        return match ($classification) {
            'FUNDED_AWAITING_MANUAL_SETTLEMENT' => 'manual_settle_then_complete',
            'BOT_FLAGGED_BUT_FUNDED' => 'treat_as_funded_liability_triage',
            'FUNDED_WRONG_STATUS' => 'review_do_not_auto_promote',
            'EXPIRED_WITH_FUNDS' => 'review_do_not_discard_funds',
            'SETTLED_STATUS_STALE' => 'review_status_vs_payout_markers',
            'PAYMENT_EVIDENCE_UNCONFIRMED' => 'operator_confirm_or_escalate',
            'CUSTOMER_CLAIM_ONLY' => 'await_or_verify_payment',
            'EXPIRED_NO_FUNDS' => 'no_funds_no_action',
            'WAITING_PAYMENT' => 'await_customer_payment',
            'COMPLETED_CONSISTENT', 'HISTORICAL_LEGACY_COMPLETE' => 'none',
            'MANUAL_REVIEW', 'AMBIGUOUS' => 'manual_review',
            default => 'manual_review',
        };
    }

    public static function operatorPriorityGroup(array $row, string $classification): string
    {
        $age = (int) ($row['age_hours'] ?? 0);
        $bot = (int) ($row['is_bot'] ?? 0) === 1;
        if ($classification === 'FUNDED_WRONG_STATUS' || $classification === 'EXPIRED_WITH_FUNDS') {
            return 'FUNDED_WRONG_STATUS';
        }
        if ($classification === 'MANUAL_REVIEW' || $classification === 'AMBIGUOUS' || $classification === 'PAYMENT_EVIDENCE_UNCONFIRMED') {
            return 'AMBIGUOUS';
        }
        if (in_array($classification, ['FUNDED_AWAITING_MANUAL_SETTLEMENT', 'BOT_FLAGGED_BUT_FUNDED'], true)) {
            if ($bot) {
                return 'BOT_FLAGGED_BUT_FUNDED';
            }
            if ($age < 168) {
                return 'RECENT_REAL_PRIORITY';
            }

            return 'OLD_CONFIRMED_BACKLOG';
        }

        return 'NONE';
    }

    public static function ageBucket(int $hours): string
    {
        return match (true) {
            $hours < 1 => 'lt1h',
            $hours < 6 => 'h1_6',
            $hours < 24 => 'h6_24',
            $hours < 72 => 'd1_3',
            $hours < 168 => 'd3_7',
            $hours < 720 => 'd7_30',
            default => 'gt30d',
        };
    }

    public static function destinationFamily(?string $code): string
    {
        $c = strtoupper(trim((string) $code));
        if ($c === '') {
            return 'UNKNOWN';
        }
        if (str_contains($c, 'ZELLE')) {
            return 'ZELLE';
        }
        if (str_starts_with($c, 'USDT') || in_array($c, ['BTC', 'ETH', 'LTC', 'TRX', 'TON', 'GRAM', 'XMR', 'BNB'], true)) {
            return 'CRYPTO';
        }
        if (str_ends_with($c, 'RUB') || str_contains($c, 'SBER') || str_contains($c, 'TCSB') || str_contains($c, 'SBP')) {
            return 'BANK_RUB';
        }
        if (str_starts_with($c, 'CARD') || str_contains($c, 'VISA') || str_contains($c, 'MC')) {
            return 'CARD';
        }

        return 'OTHER';
    }

    private static function amountMismatch(array $row): bool
    {
        $got = $row['wallet_amount'] ?? null;
        $exp = $row['give_price'] ?? null;
        if (!is_numeric($got) || !is_numeric($exp) || (float) $exp <= 0) {
            return false;
        }
        $g = (float) $got;
        $e = (float) $exp;

        return abs($g - $e) / $e > 0.02;
    }

    private static function isLatePayment(array $row): bool
    {
        $payAt = $row['inbound_confirmed_at'] ?? null;
        $created = $row['created_at'] ?? null;
        if (!$payAt || !$created) {
            return false;
        }
        try {
            $pay = new \DateTimeImmutable((string) $payAt);
            $start = new \DateTimeImmutable((string) $created);

            return $pay > $start->modify('+72 hours');
        } catch (\Throwable) {
            return false;
        }
    }
}
