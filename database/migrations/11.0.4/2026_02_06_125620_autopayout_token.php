<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks_info', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks_info', 'autopayout_token')) {
                $table->string('autopayout_token', 64)
                    ->nullable()
                    ->after('is_recounted_by_merchant');
            }
        });

        // Индекс отдельно, чтобы корректно работать на разных БД/драйверах
        Schema::table('tasks_info', function (Blueprint $table) {
            $indexName = 'tasks_info_autopayout_token_index';

            // hasIndex нет в Schema, поэтому просто пытаемся добавить индекс один раз.
            // На повторном запуске миграций up не вызовется.
            $table->index('autopayout_token', $indexName);
        });
    }

    public function down(): void
    {
        Schema::table('tasks_info', function (Blueprint $table) {
            $indexName = 'tasks_info_autopayout_token_index';

            if (Schema::hasColumn('tasks_info', 'autopayout_token')) {
                $table->dropIndex($indexName);
                $table->dropColumn('autopayout_token');
            }
        });
    }
};
