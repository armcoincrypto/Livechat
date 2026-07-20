<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * Single eligibility evaluator for quote / order / export surfaces.
 *
 * Fail-closed: missing evidence → not eligible.
 */
final class RateDirectionEligibility
{
    public function __construct(
        private readonly RateExportQuarantine $quarantine,
        private readonly BestChangeMappingVerifier $mappingVerifier,
    ) {
    }

    public static function make(): self
    {
        return new self(
            quarantine: new RateExportQuarantine(),
            mappingVerifier: BestChangeMappingVerifier::fromStorageApp(),
        );
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public function explain(array $row): array
    {
        $status = (int) ($row['status'] ?? -1);
        $allowExport = (int) ($row['allow_export'] ?? -1);
        $course = (string) ($row['course_value'] ?? '');
        $from = strtoupper((string) ($row['from'] ?? ''));
        $to = strtoupper((string) ($row['to'] ?? ''));
        $deleted = !empty($row['deleted_at']);

        $reasons = [];
        $active = !$deleted && $status === 1;
        $quarantined = !$deleted && $status === 0 && $allowExport === 2;
        $deprecated = !$deleted && $status === 2;

        if ($deleted) {
            $reasons[] = 'soft_deleted';
        }
        if ($deprecated) {
            $reasons[] = 'status_deprecated_or_removed';
        }
        if ($quarantined) {
            $reasons[] = 'quarantined_status0_allow_export2';
        }
        if ($status !== 1) {
            $reasons[] = 'direction_not_active';
        }
        if ($allowExport === 2) {
            $reasons[] = 'export_hard_disabled';
        }

        $q = $this->quarantine->evaluate($course, [
            'profit_percent' => (string) ($row['profit'] ?? '0'),
            'baseline' => isset($row['baseline']) ? (string) $row['baseline'] : null,
            'force_block_reason' => $row['force_block_reason'] ?? null,
            // Quote tooling may inspect rows before an independent baseline is attached.
            // Public XML export applies stricter require_independent_baseline separately.
            'allow_no_baseline' => empty($row['require_independent_baseline']),
        ]);
        if (!$q['allowed']) {
            $reasons[] = 'rate_' . ($q['reason'] ?? 'blocked');
        }

        $mappingStatuses = [];
        foreach ([$from, $to] as $code) {
            if ($code === '') {
                continue;
            }
            $m = $this->mappingVerifier->verifyCode($code);
            $mappingStatuses[$code] = $m['status'] ?? 'UNKNOWN';
            $st = strtoupper((string) ($m['status'] ?? ''));
            if ($st !== 'VERIFIED' && in_array($code, ['PRUSD', 'PREUR', 'PRRUB', 'TON', 'BNB'], true)) {
                $reasons[] = 'mapping_' . strtolower($code) . '_' . strtolower($st);
            }
            if ($st === 'DRIFTED' || $st === 'AMBIGUOUS' || $st === 'ABSENT' || $st === 'DEPRECATED') {
                // Export-blocking only when identity is unresolved for known risk codes
                // or when caller requires verified export mapping.
                if (!empty($row['require_verified_export_mapping'])) {
                    $reasons[] = 'export_mapping_not_verified_' . $code;
                }
            }
        }

        $reserveOk = !empty($row['reserve_ok']);
        if (array_key_exists('reserve_ok', $row) && !$reserveOk) {
            $reasons[] = 'reserve_inadequate';
        }

        $mappingBlocksExport = false;
        if (!empty($row['require_verified_export_mapping'])) {
            foreach ($mappingStatuses as $st) {
                if (strtoupper((string) $st) !== 'VERIFIED') {
                    $mappingBlocksExport = true;
                    break;
                }
            }
        }

        $eligibleForQuote = $active && $q['allowed'] && !$quarantined && !$deprecated;
        // Export may proceed without display-reserve metadata; order creation still requires reserve when evaluated.
        $eligibleForExport = $eligibleForQuote && $allowExport !== 2 && !$mappingBlocksExport;
        $eligibleForOrder = $eligibleForQuote && (array_key_exists('reserve_ok', $row) ? $reserveOk : true);

        // Deduplicate reasons while preserving order.
        $seen = [];
        $uniq = [];
        foreach ($reasons as $r) {
            if (isset($seen[$r])) {
                continue;
            }
            $seen[$r] = true;
            $uniq[] = $r;
        }

        return [
            'direction_id' => $row['id'] ?? null,
            'from' => $from,
            'to' => $to,
            'active' => $active,
            'quarantined' => $quarantined,
            'deprecated' => $deprecated,
            'eligible_for_quote' => $eligibleForQuote,
            'eligible_for_order' => $eligibleForOrder,
            'eligible_for_export' => $eligibleForExport,
            'reasons' => $uniq,
            'mapping_status' => $mappingStatuses,
            'provider_status' => $row['provider_status'] ?? null,
            'reserve_status' => array_key_exists('reserve_ok', $row)
                ? ($reserveOk ? 'adequate' : 'inadequate_or_missing')
                : 'not_evaluated',
            'rate_quarantine' => $q,
            'course_value' => $course,
            'status' => $status,
            'allow_export' => $allowExport,
        ];
    }
}
