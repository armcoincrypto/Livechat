<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'tasks_status';

        // 1) Если нет ключевых новых статусов — база битая/неполная
        $has13 = DB::table($table)->where('id', 13)->exists();
        $has14 = DB::table($table)->where('id', 14)->exists();
        $has15 = DB::table($table)->where('id', 15)->exists();

        // 2) В плохом дампе id=12 назывался "Выплата в процессе"
        $status12Name = (string) (DB::table($table)->where('id', 12)->value('name') ?? '');
        $looksLikeBad12 = str_contains($status12Name, 'Выплата') || str_contains($status12Name, '\\"Выплата');

        // 3) Частый признак кривого импорта — у 1..11 sorting = 0
        $sortingOnes = (int) DB::table($table)
            ->whereIn('id', range(1, 11))
            ->where('sorting', '>', 0)
            ->count();

        $isBroken = (!$has13 || !$has14 || !$has15) || $looksLikeBad12 || ($sortingOnes === 0);

        if (!$isBroken) {
            // Старые обменники, где всё хорошо — не трогаем.
            return;
        }

        // Канонический набор 1..15 (без 16)
        $rows = [
            ['id' => 1,  'name' => '{"ru":"Время истекло"}',                 'color' => '#f44336', 'class' => 'st-delete',           'is_export' => 0, 'updated_at' => '2024-06-28 08:10:13', 'allow_delete' => 1, 'sorting' => 3],
            ['id' => 2,  'name' => '{"ru":"Ожидается оплата"}',              'color' => '#7b93a3', 'class' => 'st-pending-payment',  'is_export' => 0, 'updated_at' => '2024-06-28 08:10:13', 'allow_delete' => 1, 'sorting' => 0],
            ['id' => 3,  'name' => '{"ru":"Ожидает обработки"}',             'color' => '#2196f3', 'class' => 'st-handler',          'is_export' => 0, 'updated_at' => '2024-06-28 08:10:13', 'allow_delete' => 0, 'sorting' => 1],
            ['id' => 4,  'name' => '{"ru":"Заявка исполнена"}',              'color' => '#4caf50', 'class' => 'st-success',          'is_export' => 1, 'updated_at' => '2024-06-28 08:10:13', 'allow_delete' => 0, 'sorting' => 4],
            ['id' => 5,  'name' => '{"ru":"Заявка отклонена"}',              'color' => '#f44336', 'class' => 'st-delete',           'is_export' => 0, 'updated_at' => '2024-06-28 08:10:13', 'allow_delete' => 1, 'sorting' => 5],
            ['id' => 6,  'name' => '{"ru":"Заявка отменена пользователем"}', 'color' => '#333',    'class' => 'st-cancel',           'is_export' => 0, 'updated_at' => '2024-06-28 08:10:13', 'allow_delete' => 1, 'sorting' => 6],
            ['id' => 7,  'name' => '{"ru":"Оплаченная заявка"}',             'color' => '#af4c88', 'class' => 'st-merchant',         'is_export' => 0, 'updated_at' => '2024-06-28 08:10:13', 'allow_delete' => 0, 'sorting' => 2],
            ['id' => 8,  'name' => '{"ru":"Отложенная заявка"}',             'color' => '#b2ba0f', 'class' => 'st-frozen',           'is_export' => 0, 'updated_at' => '2024-06-28 08:10:13', 'allow_delete' => 0, 'sorting' => 7],
            ['id' => 9,  'name' => '{"ru":"В процессе оплаты"}',             'color' => '#ab804b', 'class' => 'st-process-payment',  'is_export' => 0, 'updated_at' => '2024-06-28 08:10:13', 'allow_delete' => 0, 'sorting' => 8],
            ['id' => 10, 'name' => '{"ru":"Недействительна"}',               'color' => '#a70000', 'class' => 'st-error',            'is_export' => 0, 'updated_at' => '2024-06-28 08:10:13', 'allow_delete' => 0, 'sorting' => 9],
            ['id' => 11, 'name' => '{"ru":"Заявка удалена"}',                'color' => '#a70000', 'class' => 'st-error',            'is_export' => 0, 'updated_at' => '2024-06-28 08:10:13', 'allow_delete' => 0, 'sorting' => 10],

            ['id' => 12, 'name' => '{"ru":"Проверка оплаты"}',               'color' => '#fffae0', 'class' => 'st-warning',          'is_export' => 0, 'updated_at' => '2024-08-03 19:35:41', 'allow_delete' => 0, 'sorting' => 11],
            ['id' => 13, 'name' => '{"ru":"Подтверждение от мерчанта"}',      'color' => '#fffae0', 'class' => 'st-merchant-waiting', 'is_export' => 0, 'updated_at' => '2024-08-03 19:35:41', 'allow_delete' => 0, 'sorting' => 12],
            ['id' => 14, 'name' => '{"ru":"Ошибка авто-выплаты"}',            'color' => '#a70000', 'class' => 'st-error-light',      'is_export' => 0, 'updated_at' => '2024-08-03 19:35:41', 'allow_delete' => 0, 'sorting' => 13],
            ['id' => 15, 'name' => '{"ru":"Выплата в процессе"}',            'color' => '#a70000', 'class' => null,                 'is_export' => 0, 'updated_at' => null,                 'allow_delete' => 0, 'sorting' => 0],
        ];

        DB::table($table)->upsert(
            $rows,
            ['id'],
            ['name', 'color', 'class', 'is_export', 'updated_at', 'allow_delete', 'sorting']
        );
    }

    public function down(): void
    {
        // Откат не делаем: это миграция-фикс данных.
    }
};
