<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Добавляем новую группу прав "Аналитика", если её ещё нет
        $exists = DB::table('permissions_group')
            ->where('name', 'Аналитика')
            ->exists();

        if (! $exists) {
            DB::table('permissions_group')->insert([
                'name'       => 'Аналитика',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Удаляем именно эту запись при откате
        DB::table('permissions_group')
            ->where('name', 'Аналитика')
            ->delete();
    }
};
