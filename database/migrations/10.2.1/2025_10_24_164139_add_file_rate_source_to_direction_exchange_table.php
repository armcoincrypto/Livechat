<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {
            if (!Schema::hasColumn('direction_exchange', 'file_rate_source')) {
                $table->string('file_rate_source', 20)
                    ->default('fix')
                    ->after('floating_threshold_recount_down');
            }
        });
    }

    public function down(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {
            if (Schema::hasColumn('direction_exchange', 'file_rate_source')) {
                $table->dropColumn('file_rate_source');
            }
        });
    }
};
