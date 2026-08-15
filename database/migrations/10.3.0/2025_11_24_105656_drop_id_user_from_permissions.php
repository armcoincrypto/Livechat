<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (Schema::hasColumn('permissions', 'id_user')) {
                $table->dropColumn('id_user');
            }
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            // Восстанавливаем колонку, если вдруг понадобится откат
            $table->unsignedBigInteger('id_user')->nullable()->after('id_permissions_group');
        });
    }
};
