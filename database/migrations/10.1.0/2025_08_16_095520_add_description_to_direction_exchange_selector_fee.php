<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('direction_exchange_selector_fee', function (Blueprint $table) {
            $table->json('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('direction_exchange_selector_fee', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
