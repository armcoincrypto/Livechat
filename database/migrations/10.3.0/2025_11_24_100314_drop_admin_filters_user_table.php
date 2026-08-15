<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('admin_filters_user');
    }

    public function down(): void
    {
        // Если вдруг понадобится откатить — здесь можно восстановить структуру.
        // Сейчас оставляем пустым, чтобы не создавать неправильную таблицу.
    }
};
