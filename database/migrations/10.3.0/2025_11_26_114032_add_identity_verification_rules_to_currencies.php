<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            if (! Schema::hasColumn('currencies', 'identity_verification_rules')) {
                $table->json('identity_verification_rules')
                    ->nullable()
                    ->comment('Настройки верификации личности (mode, min_amount и др.)');
                // без ->after(), чтобы не ловить ошибки на разных схемах
            }
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            if (Schema::hasColumn('currencies', 'identity_verification_rules')) {
                $table->dropColumn('identity_verification_rules');
            }
        });
    }
};
