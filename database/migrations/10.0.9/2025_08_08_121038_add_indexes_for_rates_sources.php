<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // parser_exchange: (status, code)
        $this->ensureIndex('parser_exchange', ['status', 'code'], 'idx_parser_exchange_status_code');

        // competitor_rates: (status, code)
        $this->ensureIndex('competitor_rates', ['status', 'code'], 'idx_competitor_rates_status_code');

        // file_parser_rates: (status, code)
        $this->ensureIndex('file_parser_rates', ['status', 'code'], 'idx_file_parser_rates_status_code');

        // parser_formula_coefficient: (type_index, alias)
        $this->ensureIndex('parser_formula_coefficient', ['type_index', 'alias'], 'idx_parser_formula_coef_type_alias');

        // bestchange_directions: (status, code)
        $this->ensureIndex('bestchange_directions', ['status', 'code'], 'idx_bestchange_directions_status_code');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('parser_exchange', 'idx_parser_exchange_status_code');
        $this->dropIndexIfExists('competitor_rates', 'idx_competitor_rates_status_code');
        $this->dropIndexIfExists('file_parser_rates', 'idx_file_parser_rates_status_code');
        $this->dropIndexIfExists('parser_formula_coefficient', 'idx_parser_formula_coef_type_alias');
        $this->dropIndexIfExists('bestchange_directions', 'idx_bestchange_directions_status_code');
    }

    // ---------- helpers ----------

    private function ensureIndex(string $table, array $columns, string $indexName): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $indexName)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
            $t->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $indexName)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($indexName) {
            $t->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $row = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
        ", [$table, $indexName]);

        return (int)($row->c ?? 0) > 0;
    }
};
