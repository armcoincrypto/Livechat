<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('selector_fees', function (Blueprint $table) {
            // если таблица большая, лучше добавить индекс при необходимости
            $table->string('fee_type', 16)->default('dynamic')->after('fee');
        });
    }

    public function down(): void
    {
        Schema::table('selector_fees', function (Blueprint $table) {
            $table->dropColumn('fee_type');
        });
    }
};
