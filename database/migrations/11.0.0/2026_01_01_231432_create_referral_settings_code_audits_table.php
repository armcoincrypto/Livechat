<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_settings_code_audits', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedInteger('actor_id')->nullable()->index();
            $table->string('actor_email', 191)->nullable();
            $table->integer('before_code_currency_id')->nullable()->index();
            $table->integer('after_code_currency_id')->nullable()->index();

            $table->json('before_fallback_codes')->nullable();
            $table->json('after_fallback_codes')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 191)->nullable();

            $table->timestamps();

            // Если хочешь FK — раскомментируй (но следи за существованием таблиц/ключей)
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('before_code_currency_id')->references('id')->on('code_currency')->nullOnDelete();
            $table->foreign('after_code_currency_id')->references('id')->on('code_currency')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_settings_code_audits');
    }
};
