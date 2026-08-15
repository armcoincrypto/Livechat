<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks_meta', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks_meta', 'geo_data')) {
                $table->json('geo_data')
                    ->nullable()
                    ->after('updated_at')
                    ->comment('Гео-данные клиента (страна, регион, город и т.д.)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks_meta', function (Blueprint $table) {
            if (Schema::hasColumn('tasks_meta', 'geo_data')) {
                $table->dropColumn('geo_data');
            }
        });
    }
};
