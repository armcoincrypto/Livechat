<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('selector_fees', function (Blueprint $table) {
            // JSON, чтобы поле было локализуемым (совместимо с HasTranslations)
            $table->json('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('selector_fees', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
