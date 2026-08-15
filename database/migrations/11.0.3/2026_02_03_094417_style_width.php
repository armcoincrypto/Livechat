<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_programs', function (Blueprint $table) {
            if (Schema::hasColumn('referral_programs', 'style_width')) {
                $table->dropColumn('style_width');
            }
        });

        Schema::table('reward_programs', function (Blueprint $table) {
            if (Schema::hasColumn('reward_programs', 'style_width')) {
                $table->dropColumn('style_width');
            }
        });
    }

    public function down(): void
    {
        Schema::table('referral_programs', function (Blueprint $table) {
            $table->string('style_width')->nullable();
        });

        Schema::table('reward_programs', function (Blueprint $table) {
            $table->string('style_width')->nullable();
        });
    }
};
