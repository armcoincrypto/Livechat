<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('export_rates_files', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->nullable();
            $table->integer('type_file')->default(0);
            $table->integer('number_format')->default(10);
            $table->integer('type_number_format')->default(0);
            $table->integer('in_type_tofee')->default(0);
            $table->integer('in_type_fromfee')->default(0);
            $table->integer('cron_update')->default(0);
            $table->integer('is_offline_operator')->default(0);
            $table->integer('status')->default(0);
            $table->index('filename');
            $table->index('is_offline_operator');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_rates_files');
    }
};
