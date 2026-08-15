<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('online_daily_stats', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->date('day')->unique();

            $table->unsignedInteger('auth_unique')->default(0);
            $table->unsignedInteger('guest_unique')->default(0);

            $table->unsignedBigInteger('auth_hits')->default(0);
            $table->unsignedBigInteger('guest_hits')->default(0);

            $table->unsignedInteger('peak_concurrent')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_daily_stats');
    }
};
