<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_statistics', function (Blueprint $table) {
            // Индексы для частых выборок
            $table->index('ref_hash', 'referral_statistics_ref_hash_index');
            $table->index('created_at', 'referral_statistics_created_at_index');
            $table->index(['ref_hash', 'created_at'], 'referral_statistics_ref_hash_created_at_index');
            $table->index('id_user', 'referral_statistics_id_user_index');
        });
    }

    public function down(): void
    {
        Schema::table('referral_statistics', function (Blueprint $table) {
            // Удаляем индексы
            $table->dropIndex('referral_statistics_ref_hash_index');
            $table->dropIndex('referral_statistics_created_at_index');
            $table->dropIndex('referral_statistics_ref_hash_created_at_index');
            $table->dropIndex('referral_statistics_id_user_index');
        });
    }
};
