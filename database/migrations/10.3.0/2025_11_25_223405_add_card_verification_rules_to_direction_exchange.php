<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Добавляем JSON под настройки верификации карт
     * и удаляем старые числовые/строковые поля.
     */
    public function up(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {
            // Новое JSON-поле для настроек верификации карт
            if (! Schema::hasColumn('direction_exchange', 'card_verification_rules')) {
                $table->json('card_verification_rules')
                    ->nullable()
                    ->after('card_verification_type')
                    ->comment('Настройки верификации карт (no/with)');
            }
        });

        // Отдельным вызовом, потому что dropColumn с проверками удобнее разнести
        Schema::table('direction_exchange', function (Blueprint $table) {
            $columnsToDrop = [
                'no_verification_min_amount',
                'no_verification_max_amount',
                'no_verification_fee',
                'verification_min_amount',
                'verification_max_amount',
                'verification_fee',
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('direction_exchange', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Откат: возвращаем старые поля и убираем JSON.
     * Данные из JSON обратно не восстанавливаем.
     */
    public function down(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {
            // Восстанавливаем старые поля с дефолтами
            if (! Schema::hasColumn('direction_exchange', 'no_verification_min_amount')) {
                $table->decimal('no_verification_min_amount', 24, 8)
                    ->default(0)
                    ->after('card_verification_type');
            }

            if (! Schema::hasColumn('direction_exchange', 'no_verification_max_amount')) {
                $table->decimal('no_verification_max_amount', 24, 8)
                    ->default(0)
                    ->after('no_verification_min_amount');
            }

            if (! Schema::hasColumn('direction_exchange', 'no_verification_fee')) {
                $table->string('no_verification_fee', 32)
                    ->default('0')
                    ->after('no_verification_max_amount');
            }

            if (! Schema::hasColumn('direction_exchange', 'verification_min_amount')) {
                $table->decimal('verification_min_amount', 24, 8)
                    ->default(0)
                    ->after('no_verification_fee');
            }

            if (! Schema::hasColumn('direction_exchange', 'verification_max_amount')) {
                $table->decimal('verification_max_amount', 24, 8)
                    ->default(0)
                    ->after('verification_min_amount');
            }

            if (! Schema::hasColumn('direction_exchange', 'verification_fee')) {
                $table->string('verification_fee', 32)
                    ->default('0')
                    ->after('verification_max_amount');
            }

            // Убираем JSON-поле
            if (Schema::hasColumn('direction_exchange', 'card_verification_rules')) {
                $table->dropColumn('card_verification_rules');
            }
        });
    }
};
