<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_programs', function (Blueprint $table) {
            // на всякий случай — чтобы миграция не падала на разных базах
            if (Schema::hasColumn('referral_programs', 'type')) {
                $table->dropColumn('type');
            }

            if (Schema::hasColumn('referral_programs', 'count')) {
                $table->dropColumn('count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('referral_programs', function (Blueprint $table) {
            // возвращаем обратно (как было по твоему дампу)
            if (!Schema::hasColumn('referral_programs', 'type')) {
                $table->integer('type')->default(1)->after('percent');
            }

            if (!Schema::hasColumn('referral_programs', 'count')) {
                $table->integer('count')->default(0)->after('type');
            }
        });
    }
};
