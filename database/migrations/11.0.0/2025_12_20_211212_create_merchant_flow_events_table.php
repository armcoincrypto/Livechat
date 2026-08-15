<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_flow_events', function (Blueprint $table) {
            $table->id();

            // Кому относится
            $table->unsignedBigInteger('task_id')->nullable()->index();
            $table->unsignedBigInteger('merchant_id')->nullable()->index();

            // Для checkout / callback
            $table->string('gateway_alias', 64)->nullable()->index();
            $table->string('flow', 16)->default('merchant')->index();          // merchant|checkout|callback
            $table->string('stage', 32)->nullable()->index();                 // validate|lookup|security|purchase|complete_purchase|finalize...
            $table->string('event', 64)->index();                             // checkout.opened, callback.ip_blocked, callback.signature_fail ...

            // Уровень события
            $table->string('level', 16)->default('info')->index();            // info|warning|error|security

            // Контекст
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 255)->nullable();

            // Человеческое описание
            $table->string('message', 512)->nullable();

            // Любые детали (payload, вычисления, ids)
            $table->json('context')->nullable();

            // Технические поля для удобства
            $table->string('checkout_id', 191)->nullable()->index();          // если есть (ext_data.checkout.id)
            $table->string('external_id', 191)->nullable()->index();          // id_from_merchant / ac_order_id / etc

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_flow_events');
    }
};
