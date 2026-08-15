<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Находим все дубликаты по id_task
        $duplicates = DB::table('referral_log')
            ->select('id_task', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('id_task')
            ->groupBy('id_task')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            // Для каждой группы дублей оставляем запись с MIN(id)
            $keepId = DB::table('referral_log')
                ->where('id_task', $dup->id_task)
                ->min('id');

            // Удаляем остальные
            DB::table('referral_log')
                ->where('id_task', $dup->id_task)
                ->where('id', '<>', $keepId)
                ->delete();
        }

        Schema::table('referral_log', function (Blueprint $table) {
            // Перед добавлением индекса убедись, что нет дублей
            // и что имя индекса уникально в твоей БД
            $table->unique('id_task', 'referral_log_id_task_unique');
        });
    }

    public function down(): void
    {
        Schema::table('referral_log', function (Blueprint $table) {
            $table->dropUnique('referral_log_id_task_unique');
        });
    }
};
