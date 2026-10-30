<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Host CPU optimization: stop full-table scan + filesort on parser_exchange
 * for IndependentMarketBaseline / CertifiedSymbolFallback hot path:
 *   WHERE code_in=? AND code_out=? ORDER BY updated_at DESC LIMIT 24
 *
 * Read-path index only. No pricing / freshness / fallback changes.
 */
return new class extends Migration
{
    private const INDEX = 'idx_parser_exchange_code_in_out_updated';

    public function up(): void
    {
        if (! Schema::hasTable('parser_exchange')) {
            return;
        }

        $exists = DB::select('SHOW INDEX FROM parser_exchange WHERE Key_name = ?', [self::INDEX]);
        if ($exists !== []) {
            return;
        }

        Schema::table('parser_exchange', function (Blueprint $table) {
            $table->index(['code_in', 'code_out', 'updated_at'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('parser_exchange')) {
            return;
        }

        $exists = DB::select('SHOW INDEX FROM parser_exchange WHERE Key_name = ?', [self::INDEX]);
        if ($exists === []) {
            return;
        }

        Schema::table('parser_exchange', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }
};
