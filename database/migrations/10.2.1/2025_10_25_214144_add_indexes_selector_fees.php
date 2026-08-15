<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('selector_fees', function (Blueprint $table) {
            // Для запроса: WHERE status = 1 ORDER BY sorting, id
            $table->index(['status', 'sorting', 'id'], 'idx_selector_fees_status_sort');
        });
    }

    public function down(): void
    {
        Schema::table('selector_fees', function (Blueprint $table) {
            $table->dropIndex('idx_selector_fees_status_sort');
        });
    }
};
