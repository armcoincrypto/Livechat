<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proxies', function (Blueprint $table) {
            // Временная блокировка (cooldown) после fail_threshold
            $table->timestamp('auto_disabled_until')->nullable()->after('status');

            // Важно для быстрых проверок по доступности
            $table->index(['status', 'auto_disabled_until'], 'proxies_status_auto_disabled_idx');
        });
    }

    public function down(): void
    {
        Schema::table('proxies', function (Blueprint $table) {
            $table->dropIndex('proxies_status_auto_disabled_idx');
            $table->dropColumn('auto_disabled_until');
        });
    }
};
