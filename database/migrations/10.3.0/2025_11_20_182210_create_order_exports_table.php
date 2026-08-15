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
        Schema::create('order_exports', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id'); // вместо unsignedBigInteger
            $table->string('format', 10)->default('xlsx');
            $table->string('file_name')->nullable();
            $table->string('status', 20)->default('pending'); // pending, processing, done, failed
            $table->unsignedInteger('rows_count')->nullable();
            $table->json('filters')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_exports');
    }
};
