<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'is_from_identity_verification')) {
                $table->boolean('is_from_identity_verification')
                    ->default(false)
                    ->after('is_from_verification_card')
                    ->comment('Заявка создана из сценария верификации личности (KYC)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'is_from_identity_verification')) {
                $table->dropColumn('is_from_identity_verification');
            }
        });
    }
};
