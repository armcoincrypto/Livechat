<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('currency_label_currency', function (Blueprint $table) {
            $table->id();
            $table->integer('currency_id')->unsigned(false);

            $table->unsignedBigInteger('label_id');
            $table->enum('side', ['give','receive'])->default('give');
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['currency_id', 'label_id', 'side'], 'uniq_currency_label_side');
            $table->index('label_id');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('cascade');
            $table->foreign('label_id')->references('id')->on('currencies_labels')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_label_currency');
    }
};
