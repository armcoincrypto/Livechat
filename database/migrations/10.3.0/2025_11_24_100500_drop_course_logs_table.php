<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('course_logs');
    }

    public function down(): void
    {
        // Если когда-то понадобится откат —
        // здесь можно восстановить структуру.
        // Оставляем пустым, чтобы не создавать лишних таблиц.
    }
};
