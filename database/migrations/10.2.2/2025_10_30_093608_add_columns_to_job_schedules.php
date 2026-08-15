<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('job_schedules', function (Blueprint $table) {
            // Для смены типа уже существующих колонок через change() требуется doctrine/dbal.
            // Если он установлен — раскомментируйте строки change().
            // $table->string('from_time', 5)->nullable()->change();
            // $table->string('to_time', 5)->nullable()->change();

            // Новые поля
            $table->boolean('all_day')->default(false)->after('status');
            $table->smallInteger('priority')->default(0)->after('all_day');
            $table->string('timezone', 64)->nullable()->after('to_time');

            $table->date('active_from')->nullable()->after('timezone');
            $table->date('active_to')->nullable()->after('active_from');

            $table->json('include_dates')->nullable()->after('work_days');
            $table->json('exclude_dates')->nullable()->after('include_dates');
            $table->json('date_ranges')->nullable()->after('exclude_dates');

            // Индексы для быстрого отбора
            $table->index(['status', 'priority']);
            $table->index('id_user');
            $table->index(['active_from', 'active_to']);
            $table->index('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('job_schedules', function (Blueprint $table) {
            $table->dropIndex(['job_schedules_status_priority_index']);
            $table->dropIndex(['job_schedules_id_user_index']);
            $table->dropIndex(['job_schedules_active_from_active_to_index']);
            $table->dropIndex(['job_schedules_timezone_index']);

            $table->dropColumn([
                'all_day',
                'priority',
                'timezone',
                'active_from',
                'active_to',
                'include_dates',
                'exclude_dates',
                'date_ranges',
            ]);

            // Типы from_time/to_time откатывать не нужно, так как выше мы их не меняли (строки change() закомментированы)
        });
    }
};
