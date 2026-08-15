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
        Schema::create('currency_payments', function (Blueprint $table) {
            $table->id();
            $table->integer('model_id');
            $table->string('model_type');
            $table->bigInteger('gateway_payment_id')->unsigned();
            $table->foreign('model_id')->references('id')->on('currencies');
            $table->foreign('gateway_payment_id')->references('id')->on('gateways_payments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_payments');
    }
};
