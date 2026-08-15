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
        Schema::create('tasks_requisites_manual_data', function (Blueprint $table) {
            $table->id();
            $table->integer('id_requisites')->default(0);
            $table->bigInteger('id_task')->default(0);
            $table->string('account_number')->nullable();
            $table->json('ext_params')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks_requisites_data');
    }
};
