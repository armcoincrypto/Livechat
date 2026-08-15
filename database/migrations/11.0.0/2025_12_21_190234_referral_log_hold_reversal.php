<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) добавляем новые поля
        Schema::table('referral_log', function (Blueprint $table) {
            $table->string('event_key', 191)->nullable()->after('id_task');
            $table->string('status', 16)->default('confirmed')->after('event_key');
            $table->timestamp('available_at')->nullable()->after('status');
            $table->timestamp('confirmed_at')->nullable()->after('available_at');
            $table->timestamp('reversed_at')->nullable()->after('confirmed_at');

            $table->boolean('is_reversal')->default(false)->after('reversed_at');
            $table->unsignedInteger('reversal_of_id')->nullable()->after('is_reversal');

            $table->string('reason', 500)->nullable()->after('current_percent');

            $table->index('status', 'referral_log_status_index');
            $table->index('available_at', 'referral_log_available_at_index');
            $table->index('reversal_of_id', 'referral_log_reversal_of_id_index');
        });

        // 2) создаём ОБЫЧНЫЙ индекс на id_task (чтобы FK был доволен)
        // ВАЖНО: имя другое, чтобы не конфликтовало
        Schema::table('referral_log', function (Blueprint $table) {
            $table->index('id_task', 'referral_log_id_task_index');
        });

        // 3) теперь можно убрать UNIQUE по id_task
        Schema::table('referral_log', function (Blueprint $table) {
            $table->dropUnique('referral_log_id_task_unique');
        });

        // 4) заполняем event_key для существующих записей (иначе unique не создать)
        DB::table('referral_log')
            ->whereNull('event_key')
            ->update([
                'event_key' => DB::raw("CONCAT('task:', id_task, ':bonus')"),
            ]);

        // 5) делаем unique по event_key + индекс на event_key
        Schema::table('referral_log', function (Blueprint $table) {
            $table->unique('event_key', 'referral_log_event_key_unique');
            $table->index('event_key', 'referral_log_event_key_index');
        });
    }

    public function down(): void
    {
        // 1) снимаем unique/index event_key
        Schema::table('referral_log', function (Blueprint $table) {
            $table->dropUnique('referral_log_event_key_unique');
            $table->dropIndex('referral_log_event_key_index');

            $table->dropIndex('referral_log_status_index');
            $table->dropIndex('referral_log_available_at_index');
            $table->dropIndex('referral_log_reversal_of_id_index');

            $table->dropIndex('referral_log_id_task_index');

            $table->dropColumn([
                'event_key',
                'status',
                'available_at',
                'confirmed_at',
                'reversed_at',
                'is_reversal',
                'reversal_of_id',
                'reason',
            ]);
        });

        // 2) возвращаем UNIQUE по id_task (как было)
        Schema::table('referral_log', function (Blueprint $table) {
            $table->unique('id_task', 'referral_log_id_task_unique');
        });
    }
};
