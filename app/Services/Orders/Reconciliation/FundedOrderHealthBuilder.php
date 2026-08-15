<?php

declare(strict_types=1);

namespace App\Services\Orders\Reconciliation;

final class FundedOrderHealthBuilder
{
    /**
     * @param  list<array<string,mixed>>  $rows
     * @return array<string,mixed>
     */
    public function build(array $rows, array $detector): array
    {
        $paid = array_values(array_filter($rows, fn ($r) => (int) $r['current_status'] === 7));
        $wh = array_values(array_filter($rows, fn ($r) => (int) $r['current_status'] === 3));
        $classes = [];
        foreach ($rows as $r) {
            $c = (string) $r['classification'];
            $classes[$c] = ($classes[$c] ?? 0) + 1;
        }

        $paidAges = array_map(fn ($r) => (int) $r['age_hours'] * 3600, $paid);
        $oldest = $paidAges === [] ? 0 : max($paidAges);

        $countPaidOver = function (int $hours) use ($paid): int {
            return count(array_filter($paid, fn ($r) => (int) $r['age_hours'] >= $hours));
        };

        $whConfirmed = count(array_filter($wh, fn ($r) => $r['inbound_evidence'] === FundedReconciliationClassifier::CONFIRMED_FUNDS));
        $whEvidence = count(array_filter($wh, fn ($r) => in_array($r['inbound_evidence'], [
            FundedReconciliationClassifier::CONFIRMED_FUNDS,
            FundedReconciliationClassifier::STRONG_PAYMENT_EVIDENCE,
        ], true)));

        $paidRecent = array_values(array_filter($paid, fn ($r) => (int) $r['age_hours'] < 168 && (int) $r['is_bot'] === 0));
        $countRecentOver = function (int $hours) use ($paidRecent): int {
            return count(array_filter($paidRecent, fn ($r) => (int) $r['age_hours'] >= $hours));
        };

        return [
            'generated_at' => now()->toIso8601String(),
            'paid_total' => count($paid),
            'paid_recent' => count($paidRecent),
            'paid_oldest_seconds' => $oldest,
            'paid_over_1h' => $countPaidOver(1),
            'paid_over_6h' => $countPaidOver(6),
            'paid_over_24h' => $countPaidOver(24),
            'paid_over_7d' => $countPaidOver(168),
            'paid_recent_over_1h' => $countRecentOver(1),
            'paid_recent_over_6h' => $countRecentOver(6),
            'paid_recent_over_24h' => $countRecentOver(24),
            'waiting_handle_total' => count($wh),
            'waiting_handle_with_confirmed_funds' => $whConfirmed,
            'waiting_handle_with_payment_evidence' => $whEvidence,
            'expired_with_confirmed_funds' => $classes['EXPIRED_WITH_FUNDS'] ?? 0,
            'funded_wrong_status' => $classes['FUNDED_WRONG_STATUS'] ?? 0,
            'settled_status_stale' => $classes['SETTLED_STATUS_STALE'] ?? 0,
            'ambiguous' => ($classes['AMBIGUOUS'] ?? 0) + ($classes['MANUAL_REVIEW'] ?? 0),
            'payment_detector_last_success' => $detector['last_bot_poll_touch_at'] ?? null,
            'payment_detector_state' => $detector['state'] ?? null,
            'class_counts' => $classes,
            'read_only' => true,
        ];
    }

    /**
     * Internal operational thresholds — not a customer SLA.
     *
     * @return array{severity:string,reasons:list<string>}
     */
    public function operationalSeverity(array $health): array
    {
        $reasons = [];
        $severity = 'OK';
        if ((int) ($health['waiting_handle_with_confirmed_funds'] ?? 0) > 0) {
            $severity = 'CRITICAL';
            $reasons[] = 'waiting_handle_with_confirmed_funds';
        }
        if ((int) ($health['expired_with_confirmed_funds'] ?? 0) > 0) {
            $severity = 'CRITICAL';
            $reasons[] = 'expired_with_confirmed_funds';
        }
        if (($health['payment_detector_state'] ?? '') === 'PAYMENT_DETECTOR_STALE') {
            $severity = $severity === 'CRITICAL' ? 'CRITICAL' : 'WARNING';
            $reasons[] = 'payment_detector_stale';
        }
        $paidRecentOver24 = (int) ($health['paid_recent_over_24h'] ?? 0) > 0;
        if ($paidRecentOver24) {
            $severity = $severity === 'CRITICAL' ? 'CRITICAL' : 'WARNING';
            $reasons[] = 'recent_paid_over_24h';
        } elseif ((int) ($health['paid_recent_over_6h'] ?? 0) > 0) {
            if ($severity === 'OK') {
                $severity = 'WARNING';
            }
            $reasons[] = 'recent_paid_over_6h';
        } elseif ((int) ($health['paid_recent_over_1h'] ?? 0) > 0) {
            if ($severity === 'OK') {
                $severity = 'INFO';
            }
            $reasons[] = 'recent_paid_over_1h';
        }

        return ['severity' => $severity, 'reasons' => $reasons];
    }
}
