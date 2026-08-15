<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_recount_states', function (Blueprint $table) {
            $table->timestamp('last_recalculated_at_global')->nullable()->after('task_id');
            $table->string('last_rate_value_global', 64)->nullable()->after('last_recalculated_at_global');

            $table->timestamp('last_recalculated_at_floating')->nullable()->after('last_rate_value_global');
            $table->string('last_rate_value_floating', 64)->nullable()->after('last_recalculated_at_floating');

            // старые общие поля можно оставить (для миграции), но лучше удалить после переноса
            $table->dropColumn(['last_recalculated_at', 'last_rate_value']);
        });
    }

    public function down(): void
    {
        Schema::table('order_recount_states', function (Blueprint $table) {
            $table->dropColumn([
                'last_recalculated_at_global',
                'last_rate_value_global',
                'last_recalculated_at_floating',
                'last_rate_value_floating',
            ]);
        });
    }
};
