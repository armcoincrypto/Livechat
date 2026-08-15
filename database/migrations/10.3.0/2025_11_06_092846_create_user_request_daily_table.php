<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_request_daily', function (Blueprint $table) {
            // День в UTC (дата без времени)
            $table->date('bucket_day');
            $table->unsignedBigInteger('user_id');

            // Сколько запросов сделал пользователь за день
            $table->unsignedInteger('hits')->default(0);

            // Дополнительно: последнее время активности этого дня (UTC)
            $table->dateTime('last_seen')->nullable();

            $table->timestamps();

            $table->primary(['bucket_day','user_id'], 'urd_pk');

            $table->index(['bucket_day'], 'urd_day_idx');
            $table->index(['user_id'],    'urd_user_idx');
            $table->index(['updated_at'], 'urd_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_request_daily');
    }
};
