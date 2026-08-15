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
        Schema::create('sumsub_ids', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->index();          // Привязка к пользователю
            $table->string('applicant_id')->unique();                // ID в Sumsub
            $table->string('status')->nullable();                    // Текущий статус (например, pending, approved)
            $table->timestamp('expire_at')->nullable();              // Срок действия accessToken или KYC-сессии
            $table->json('sumsub_data')->nullable();                 // Доп. данные Sumsub (raw response, meta)
            $table->timestamps();

            // Внешний ключ (если User есть)
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sumsub_ids');
    }
};
