<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_logs', function (Blueprint $table) {
            // Добавляем token_id (FK на personal_access_tokens)
            if (!Schema::hasColumn('api_logs', 'token_id')) {
                $table->unsignedBigInteger('token_id')
                    ->nullable()
                    ->after('api_token');

                // если хочешь реальный FK — можно включить строчку ниже
                // $table->foreign('token_id')->references('id')->on('personal_access_tokens')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_logs', function (Blueprint $table) {
            if (Schema::hasColumn('api_logs', 'token_id')) {
                // если был fk, сначала дропаем его
                // $table->dropForeign(['token_id']);
                $table->dropColumn('token_id');
            }
        });
    }
};
