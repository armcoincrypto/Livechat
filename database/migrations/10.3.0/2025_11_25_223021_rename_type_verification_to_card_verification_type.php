<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {

            // Переименовываем колонку, если она существует
            if (
                Schema::hasColumn('direction_exchange', 'type_verification') &&
                !Schema::hasColumn('direction_exchange', 'card_verification_type')
            ) {
                $table->renameColumn('type_verification', 'card_verification_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {

            if (
                Schema::hasColumn('direction_exchange', 'card_verification_type') &&
                !Schema::hasColumn('direction_exchange', 'type_verification')
            ) {
                $table->renameColumn('card_verification_type', 'type_verification');
            }
        });
    }
};
