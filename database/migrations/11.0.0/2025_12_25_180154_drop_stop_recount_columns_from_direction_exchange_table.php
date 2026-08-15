<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {
            // Важно: dropColumn может падать если колонок нет — на проде лучше обернуть проверкой.
            if (Schema::hasColumn('direction_exchange', 'fix_stop_recount_status')) {
                $table->dropColumn('fix_stop_recount_status');
            }
            if (Schema::hasColumn('direction_exchange', 'floating_stop_recount_status')) {
                $table->dropColumn('floating_stop_recount_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {
            // Возвращаем как было (если вдруг откат нужен)
            if (!Schema::hasColumn('direction_exchange', 'fix_stop_recount_status')) {
                $table->unsignedInteger('fix_stop_recount_status')->default(0);
            }
            if (!Schema::hasColumn('direction_exchange', 'floating_stop_recount_status')) {
                $table->unsignedInteger('floating_stop_recount_status')->default(0);
            }
        });
    }
};
