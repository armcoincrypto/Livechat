<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('checkbox_agreements', function (Blueprint $table) {
            // all_except = чекбокс работает для всех, кроме указанных направлений
            // only_selected = чекбокс работает только для выбранных направлений
            $table->string('apply_mode', 20)
                ->default('all_except')
                ->after('key_id');
        });
    }

    public function down(): void
    {
        Schema::table('checkbox_agreements', function (Blueprint $table) {
            $table->dropColumn('apply_mode');
        });
    }
};
