<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks_meta', function (Blueprint $table) {
            if (Schema::hasColumn('tasks_meta', 'is_verification_required')) {
                $table->renameColumn('is_verification_required', 'card_verification_required');
            }

            if (Schema::hasColumn('tasks_meta', 'type_verification')) {
                $table->renameColumn('type_verification', 'card_verification_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks_meta', function (Blueprint $table) {
            if (Schema::hasColumn('tasks_meta', 'card_verification_required')) {
                $table->renameColumn('card_verification_required', 'is_verification_required');
            }

            if (Schema::hasColumn('tasks_meta', 'card_verification_type')) {
                $table->renameColumn('card_verification_type', 'type_verification');
            }
        });
    }
};
