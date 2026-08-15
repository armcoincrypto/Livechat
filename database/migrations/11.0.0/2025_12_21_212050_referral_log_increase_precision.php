<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('referral_log', function (Blueprint $table) {
            $table->decimal('bonus_number', 24, 2)->default('0')->change();
            $table->decimal('fixed_bonus', 24, 2)->default('0')->change();
        });
    }

    public function down(): void
    {
        Schema::table('referral_log', function (Blueprint $table) {
            $table->decimal('bonus_number', 24, 8)->default('0.00000000')->change();
            $table->decimal('fixed_bonus', 24, 8)->default('0.00000000')->change();
        });
    }
};
