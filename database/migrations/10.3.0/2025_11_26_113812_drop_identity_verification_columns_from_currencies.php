<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            if (Schema::hasColumn('currencies', 'identity_verification_mode')) {
                $table->dropColumn('identity_verification_mode');
            }

            if (Schema::hasColumn('currencies', 'identity_min_amount')) {
                $table->dropColumn('identity_min_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            if (! Schema::hasColumn('currencies', 'identity_verification_mode')) {
                $table->string('identity_verification_mode', 32)
                    ->default('disabled')
                    ->comment('Режим верификации личности (устаревшее поле)');
            }

            if (! Schema::hasColumn('currencies', 'identity_min_amount')) {
                $table->decimal('identity_min_amount', 24, 8)
                    ->default(0)
                    ->comment('Мин. сумма для верификации личности (устаревшее поле)');
            }
        });
    }
};
