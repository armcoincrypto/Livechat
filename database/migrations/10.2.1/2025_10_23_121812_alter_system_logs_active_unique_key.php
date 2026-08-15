<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            // 1️⃣ Добавляем вычисляемую колонку для уникальности только активных записей
            if (!Schema::hasColumn('system_logs', 'active_unique_key')) {
                $table->string('active_unique_key', 768)
                    ->nullable()
                    ->storedAs("CASE WHEN active_flag = 1 THEN CONCAT_WS('#', module, COALESCE(name,''), COALESCE(code,''), COALESCE(entity_type,''), COALESCE(entity_id,'')) ELSE NULL END")
                    ->after('active_flag')
                    ->comment('Технический ключ уникальности только для активных записей');
            }

            // 2️⃣ Удаляем старый уникальный индекс (если он существует)
            $oldIndex = 'syslogs_unique_active_key';
            $hasOld = collect(DB::select("SHOW INDEX FROM system_logs WHERE Key_name = ?", [$oldIndex]))->isNotEmpty();
            if ($hasOld) {
                $table->dropUnique($oldIndex);
            }

            // 3️⃣ Создаём новый уникальный индекс только по active_unique_key
            $newIndex = 'syslogs_unique_active_only';
            $hasNew = collect(DB::select("SHOW INDEX FROM system_logs WHERE Key_name = ?", [$newIndex]))->isNotEmpty();
            if (! $hasNew) {
                $table->unique('active_unique_key', $newIndex);
            }
        });

        // 4️⃣ (опционально) — чистим возможные дубликаты активных перед созданием индекса
        //   оставляем только самую позднюю запись, остальные переводим в resolved
        $duplicates = DB::select("
            SELECT module, name, code, entity_type, entity_id
            FROM system_logs
            WHERE active_flag = 1
            GROUP BY module, name, code, entity_type, entity_id
            HAVING COUNT(*) > 1
        ");

        foreach ($duplicates as $dup) {
            $rows = DB::table('system_logs')
                ->where([
                    'module'       => $dup->module,
                    'name'         => $dup->name,
                    'code'         => $dup->code,
                    'entity_type'  => $dup->entity_type,
                    'entity_id'    => $dup->entity_id,
                    'active_flag'  => 1,
                ])
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->get();

            $keep = $rows->shift();
            foreach ($rows as $row) {
                DB::table('system_logs')
                    ->where('id', $row->id)
                    ->update(['state' => 'resolved', 'active_flag' => 0]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            // Удаляем новый индекс
            $newIndex = 'syslogs_unique_active_only';
            $hasNew = collect(DB::select("SHOW INDEX FROM system_logs WHERE Key_name = ?", [$newIndex]))->isNotEmpty();
            if ($hasNew) {
                $table->dropUnique($newIndex);
            }

            // Возвращаем старый индекс (по необходимости, можно оставить закомментированным)
            // $table->unique(['module','name','code','entity_type','entity_id','active_flag'], 'syslogs_unique_active_key');

            // Удаляем колонку, если была добавлена
            if (Schema::hasColumn('system_logs', 'active_unique_key')) {
                $table->dropColumn('active_unique_key');
            }
        });
    }
};
