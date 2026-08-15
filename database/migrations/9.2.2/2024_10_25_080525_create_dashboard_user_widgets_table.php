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
        Schema::create('dashboard_user_widgets', function (Blueprint $table) {
            $table->id();
            $table->integer('id_user')->default(0);
            $table->integer('sorting')->default(0);
            $table->integer('row')->default(0);
            $table->integer('col')->default(0);
            $table->string('alias')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_user_widgets');
    }
};
