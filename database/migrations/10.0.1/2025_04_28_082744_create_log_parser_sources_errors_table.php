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
        Schema::create('log_parser_sources_errors', function (Blueprint $table) {
            $table->id();
            $table->integer('id_group')->default(0);
            $table->string('source_name')->nullable();
            $table->integer('type_parsing')->default(0);
            $table->string('pair_name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_parser_sources_errors');
    }
};
