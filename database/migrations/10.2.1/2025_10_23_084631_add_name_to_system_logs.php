<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            // Под-название/подмодуль (например: calculator, webhook, sync)
            $table->string('name', 100)->nullable()->after('module')->index();
        });
    }

    public function down(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
