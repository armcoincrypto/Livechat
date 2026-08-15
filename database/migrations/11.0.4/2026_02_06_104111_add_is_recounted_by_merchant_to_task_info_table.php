<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks_info', function (Blueprint $table) {
            $table->boolean('is_recounted_by_merchant')
                ->default(false)
                ->comment('Флаг: заявка уже была пересчитана по поздней оплате (защита от повторного recount)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks_info', function (Blueprint $table) {
            $table->dropColumn('is_recounted_by_merchant');
        });
    }
};
