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
        Schema::create('merchant_transaction_webhooks', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_task');
            $table->string('merchant_service_id')->nullable();
            $table->integer('id_currency')->default(0);
            $table->integer('id_merchant')->default(0);
            $table->string('provider')->nullable();
            $table->longText('json_callbacks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_transaction_webhooks');
    }
};
