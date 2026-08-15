<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks_meta', function (Blueprint $table) {
            $table->tinyInteger('type_verification')->default(0)->after('is_verification_required');
        });
    }

    public function down(): void
    {
        Schema::table('tasks_meta', function (Blueprint $table) {
            $table->dropColumn('type_verification');
        });
    }
};
