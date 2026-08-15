<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // (необязательно) подстрахуемся: приведём все NULL к '0'
        DB::table('task_extra_outs')
            ->whereNull('amount')
            ->update(['amount' => 0]);

        // Меняем тип DECIMAL(36,18) -> VARCHAR(100)
        Schema::table('task_extra_outs', function (Blueprint $table) {
            // Потребуется doctrine/dbal
            $table->string('amount')->default(0)->change();
        });

        // (опционально) Уберём лидирующие/хвостовые пробелы, если вдруг появились
        DB::statement("UPDATE task_extra_outs SET amount = TRIM(amount)");
    }

    public function down(): void
    {
        // Попытаемся привести строки обратно к DECIMAL(36,18)
        // Непарсабельные значения превратим в 0
        DB::statement("
            UPDATE task_extra_outs
            SET amount = CASE
                WHEN amount REGEXP '^[0-9]+(\\.[0-9]+)?$' THEN amount
                ELSE '0'
            END
        ");

        Schema::table('task_extra_outs', function (Blueprint $table) {
            $table->decimal('amount', 36, 18)->default(0)->change();
        });
    }
};
