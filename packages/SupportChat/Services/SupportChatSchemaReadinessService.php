<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Central schema contract for Support Chat delivery telemetry (P4.2 / LC-P5).
 */
final class SupportChatSchemaReadinessService
{
    /** @var list<string> table.column */
    private const REQUIRED_COLUMNS = [
        'support_messages.telegram_outbound_message_id',
        'support_messages.telegram_delivery_failed_at',
        'support_messages.telegram_delivery_error',
        'support_attachments.telegram_delivery_failed_at',
        'support_attachments.telegram_delivery_error',
    ];

    /** @var list<string> table.index_name */
    private const REQUIRED_INDEXES = [
        'support_messages.support_messages_telegram_inbound_uid',
    ];

    /**
     * @return array{
     *     support_chat_schema_ready: bool,
     *     missing_columns: list<string>,
     *     missing_indexes: list<string>,
     *     diagnostics_available: bool
     * }
     */
    public function assess(): array
    {
        $missingColumns = [];
        foreach (self::REQUIRED_COLUMNS as $qualified) {
            [$table, $column] = explode('.', $qualified, 2);
            if (! Schema::hasColumn($table, $column)) {
                $missingColumns[] = $qualified;
            }
        }

        $missingIndexes = [];
        foreach (self::REQUIRED_INDEXES as $qualified) {
            [$table, $indexName] = explode('.', $qualified, 2);
            if (! $this->hasIndex($table, $indexName)) {
                $missingIndexes[] = $qualified;
            }
        }

        $ready = $missingColumns === [] && $missingIndexes === [];

        return [
            'support_chat_schema_ready' => $ready,
            'missing_columns' => $missingColumns,
            'missing_indexes' => $missingIndexes,
            'diagnostics_available' => $ready,
        ];
    }

    public function isDiagnosticsAvailable(): bool
    {
        return $this->assess()['diagnostics_available'];
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        try {
            $rows = DB::select(
                'SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?',
                [$indexName]
            );
        } catch (\Throwable) {
            return false;
        }

        return $rows !== [];
    }
}
