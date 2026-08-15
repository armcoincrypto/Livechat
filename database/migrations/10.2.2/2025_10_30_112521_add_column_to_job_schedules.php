<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('job_schedules', function (Blueprint $table) {
            // Политика поведения вне интервала времени:
            // inverse       — инвертировать статус (по-умолчанию: Online => выключить вне интервала; Offline => включить)
            // no_change     — ничего не делать (сохранить текущий статус системы)
            // force_online  — принудительно включать сайт вне интервала (iex_site_status = 0)
            // force_offline — принудительно выключать сайт вне интервала (iex_site_status = 1)
            $table->string('outside_policy', 32)->default('inverse')->after('all_day');

            $table->index('outside_policy');
        });
    }

    public function down(): void
    {
        Schema::table('job_schedules', function (Blueprint $table) {
            $table->dropIndex(['outside_policy']);
            $table->dropColumn('outside_policy');
        });
    }
};
