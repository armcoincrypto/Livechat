<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks_meta', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks_meta', 'identity_verification_required')) {
                $table->boolean('identity_verification_required')
                    ->default(false)
                    ->after('card_verification_type')
                    ->comment('Флаг: требуется ли верификация личности в момент создания заявки');
            }

            if (!Schema::hasColumn('tasks_meta', 'identity_verification_type')) {
                $table->unsignedTinyInteger('identity_verification_type')
                    ->default(0)
                    ->after('identity_verification_required')
                    ->comment('Тип верификации личности: 0=валюта, 1=направление, 2=индивидуальные правила');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks_meta', function (Blueprint $table) {
            if (Schema::hasColumn('tasks_meta', 'identity_verification_required')) {
                $table->dropColumn('identity_verification_required');
            }

            if (Schema::hasColumn('tasks_meta', 'identity_verification_type')) {
                $table->dropColumn('identity_verification_type');
            }
        });
    }
};
