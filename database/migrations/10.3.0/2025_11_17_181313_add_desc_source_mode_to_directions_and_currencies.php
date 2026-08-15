<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // --- ДЛЯ НАПРАВЛЕНИЙ ---
        if (Schema::hasTable('direction_exchange')) {
            Schema::table('direction_exchange', function (Blueprint $table) {
                if (!Schema::hasColumn('direction_exchange', 'desc_source_mode')) {
                    $table->string('desc_source_mode', 20)
                        ->default('auto')
                        ->after('instruction_source_mode');
                }
            });
        }

        // --- ДЛЯ ВАЛЮТ ---
        if (Schema::hasTable('currencies')) {
            Schema::table('currencies', function (Blueprint $table) {
                if (!Schema::hasColumn('currencies', 'desc_source_mode')) {
                    $table->string('desc_source_mode', 20)
                        ->default('auto')
                        ->after('instruction_source_mode');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('direction_exchange')) {
            Schema::table('direction_exchange', function (Blueprint $table) {
                $table->dropColumn(['desc_source_mode']);
            });
        }

        if (Schema::hasTable('currencies')) {
            Schema::table('currencies', function (Blueprint $table) {
                $table->dropColumn(['desc_source_mode']);
            });
        }
    }
};
