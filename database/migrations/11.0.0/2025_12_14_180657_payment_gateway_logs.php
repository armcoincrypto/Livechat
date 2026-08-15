<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_gateway_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Связи (nullable, потому что не всегда есть task/merchant/payment)
            $table->unsignedBigInteger('task_id')->nullable()->index();
            $table->unsignedBigInteger('merchant_id')->nullable()->index();
            $table->unsignedBigInteger('payment_id')->nullable()->index();

            // Кто/что
            $table->string('gateway_alias', 64)->index();     // westwallet, supermoney...
            $table->string('direction', 16)->index();         // incoming|outgoing|callback|options|balance
            $table->string('operation', 32)->index();         // purchase|payout|fetch_payment|fetch_payout|complete_purchase|options|balance

            // Идентификаторы
            $table->string('transaction_id', 128)->nullable()->index(); // твой (Task id или extId)
            $table->string('external_id', 128)->nullable()->index();    // провайдер: order_id/txn/withdrawal_id

            // HTTP
            $table->string('http_method', 10)->nullable();
            $table->string('url', 512)->nullable();
            $table->unsignedSmallInteger('response_status')->nullable(); // HTTP status

            // Статус (унифицированный)
            $table->string('status', 32)->nullable()->index(); // success|pending|cancelled|failure

            // Диагностика
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('attempt')->default(1)->index(); // retry/polling attempt
            $table->string('idempotency_key', 128)->nullable()->index();

            // Payload (маскированный)
            $table->json('request_headers')->nullable();  // хранить безопасно: только ключи или masked
            $table->json('request_body')->nullable();     // masked
            $table->json('response_body')->nullable();    // masked (или кратко)

            // Ошибки
            $table->string('error_class', 191)->nullable();
            $table->text('error_message')->nullable();

            // Любые доп. данные
            $table->json('meta')->nullable();

            $table->timestamps();

            // Быстрые выборки по времени + шлюзу
            $table->index(['gateway_alias', 'operation', 'created_at']);
            $table->index(['direction', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_logs');
    }
};
