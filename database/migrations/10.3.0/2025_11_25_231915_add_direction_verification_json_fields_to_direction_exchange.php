<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {

            // JSON-поля для мультиязычных описаний
            if (!Schema::hasColumn('direction_exchange', 'direction_verification_text')) {
                $table->json('direction_verification_text')
                    ->nullable()
                    ->after('card_verification_rules')
                    ->comment('Мультиязычный текст верификации карты на уровне направления');
            }

            if (!Schema::hasColumn('direction_exchange', 'direction_verification_info')) {
                $table->json('direction_verification_info')
                    ->nullable()
                    ->after('direction_verification_text')
                    ->comment('Мультиязычная подробная информация о верификации карты в направлении');
            }
        });
    }

    public function down(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {

            if (Schema::hasColumn('direction_exchange', 'direction_verification_text')) {
                $table->dropColumn('direction_verification_text');
            }

            if (Schema::hasColumn('direction_exchange', 'direction_verification_info')) {
                $table->dropColumn('direction_verification_info');
            }
        });
    }
};
