<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks_info', function (Blueprint $table) {
            if (Schema::hasColumn('tasks_info', 'code_country')) {
                $table->dropColumn('code_country');
            }

            if (Schema::hasColumn('tasks_info', 'country')) {
                $table->dropColumn('country');
            }

            if (Schema::hasColumn('tasks_info', 'city')) {
                $table->dropColumn('city');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks_info', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks_info', 'code_country')) {
                $table->string('code_country', 2)
                    ->nullable()
                    ->comment('ISO код страны');
            }

            if (!Schema::hasColumn('tasks_info', 'country')) {
                $table->string('country', 191)
                    ->nullable()
                    ->comment('Название страны');
            }

            if (!Schema::hasColumn('tasks_info', 'city')) {
                $table->string('city', 191)
                    ->nullable()
                    ->comment('Название города');
            }
        });
    }
};
