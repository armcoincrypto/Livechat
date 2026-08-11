<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DirectionExchange;
use App\Services\Rates\CommercialAdjustmentWriteGate;
use App\Services\Rates\RateWriteAuditLogger;
use Illuminate\Support\Facades\Log;

/**
 * Detects mutations of owner-controlled commercial fields without an approved writer.
 * Fail-open on save (log/alert only) so unknown legacy paths do not break production.
 */
final class CommercialAdjustmentOwnershipObserver
{
    /** @var list<string> */
    private const FIELDS = ['profit', 'floating_fee', 'fix_fee'];

    public function updating(DirectionExchange $direction): void
    {
        $this->inspect($direction, 'updating');
    }

    public function creating(DirectionExchange $direction): void
    {
        $this->inspect($direction, 'creating');
    }

    private function inspect(DirectionExchange $direction, string $phase): void
    {
        if ((int) ($direction->is_type_rate ?? 0) !== 1) {
            return;
        }

        $changed = [];
        foreach (self::FIELDS as $field) {
            if ($phase === 'creating') {
                if ($direction->getAttribute($field) !== null) {
                    $changed[$field] = [
                        'old' => null,
                        'new' => (string) $direction->getAttribute($field),
                    ];
                }
                continue;
            }
            if (!$direction->isDirty($field)) {
                continue;
            }
            $changed[$field] = [
                'old' => (string) $direction->getOriginal($field),
                'new' => (string) $direction->getAttribute($field),
            ];
        }

        if ($changed === []) {
            return;
        }

        $writer = CommercialAdjustmentWriteGate::currentWriter() ?? 'unknown';
        $approved = CommercialAdjustmentWriteGate::isApproved();

        if ($approved) {
            Log::info('commercial_adjustment_write_approved', [
                'direction_id' => $direction->id,
                'writer' => $writer,
                'phase' => $phase,
                'changed' => $changed,
                'timestamp' => gmdate('c'),
            ]);

            return;
        }

        Log::warning(CommercialAdjustmentWriteGate::EVENT_UNAUTHORIZED, [
            'event' => CommercialAdjustmentWriteGate::EVENT_UNAUTHORIZED,
            'direction_id' => $direction->id,
            'writer' => $writer,
            'phase' => $phase,
            'changed' => $changed,
            'old_profit' => $changed['profit']['old'] ?? null,
            'new_profit' => $changed['profit']['new'] ?? null,
            'timestamp' => gmdate('c'),
            'parser_source_name' => (string) ($direction->parser_source_name ?? ''),
        ]);

        if (class_exists(RateWriteAuditLogger::class)) {
            RateWriteAuditLogger::record([
                'event' => CommercialAdjustmentWriteGate::EVENT_UNAUTHORIZED,
                'direction_id' => $direction->id,
                'writer' => $writer,
                'old_base' => $changed['profit']['old'] ?? null,
                'new_base' => $changed['profit']['new'] ?? null,
                'reason' => 'commercial_adjustment_field_mutated_without_approved_writer',
                'source' => 'CommercialAdjustmentOwnershipObserver',
            ]);
        }
    }
}
