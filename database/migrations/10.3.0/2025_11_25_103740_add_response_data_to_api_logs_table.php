<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_logs', function (Blueprint $table) {
            // Если поля ещё нет — добавляем
            if (!Schema::hasColumn('api_logs', 'response_data')) {
                $table->longText('response_data')->nullable()->after('post_data');
            }

            if (!Schema::hasColumn('api_logs', 'status_code')) {
                $table->unsignedSmallInteger('status_code')->nullable()->after('post_data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_logs', function (Blueprint $table) {
            if (Schema::hasColumn('api_logs', 'response_data')) {
                $table->dropColumn('response_data');
            }

            if (Schema::hasColumn('api_logs', 'status_code')) {
                $table->dropColumn('status_code');
            }
        });
    }
};
