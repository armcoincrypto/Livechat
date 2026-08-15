<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks_meta', function (Blueprint $table) {
            // Новый формат: json-снапшоты выбора
            $table->json('selected_fees')->nullable()->after('selected_fee_type');
            $table->json('selected_fees_common')->nullable()->after('selected_fees');
            $table->json('selected_fees_directions')->nullable()->after('selected_fees_common');

            // По желанию (MySQL 8+): быстрые фильтры, если часто будут выборки
            // $table->boolean('has_direction_fees')
            //     ->virtualAs("(json_search(`selected_fees`, 'one', 'individual', null, '$**.scope') is not null)")
            //     ->after('selected_fees_directions');
            // $table->index('has_direction_fees', 'tasks_meta_has_dir_fees_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tasks_meta', function (Blueprint $table) {
            // $table->dropIndex('tasks_meta_has_dir_fees_idx');
            // $table->dropColumn('has_direction_fees');
            $table->dropColumn([
                'selected_fees_directions',
                'selected_fees_common',
                'selected_fees',
            ]);
        });
    }
};
