<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proxies', function (Blueprint $table) {
            // Важно: для encrypted cast payload может быть длинным
            $table->text('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('proxies', function (Blueprint $table) {
            // Возвращаем обратно (если вдруг потребуется откат)
            // Лучше не меньше 255, иначе encrypted payload может не влезть
            $table->string('password', 255)->nullable()->change();
        });
    }
};
