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
        Schema::create('merchants_transaction_data', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_task')->default(0);
            $table->integer('id_currency')->default(0);
            $table->string('id_from_merchant')->nullable();
            $table->string('service_name')->nullable();
            $table->json('ext_data')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchants_transaction_data');
    }
};
