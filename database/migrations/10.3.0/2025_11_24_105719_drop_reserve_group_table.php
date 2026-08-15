<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('reserve_group');
    }

    public function down(): void
    {
        // Оставляем пустым — чтобы не создавать неправильную структуру
    }
};
