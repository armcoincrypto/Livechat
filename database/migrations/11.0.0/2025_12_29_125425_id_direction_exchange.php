<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks_status_log', function (Blueprint $table) {
            $table->integer('id_direction_exchange')->nullable()->after('id_task');
        });

        Schema::table('tasks_status_log', function (Blueprint $table) {
            // 4) добавляем FK снова
            $table->foreign('id_direction_exchange', 'tasks_status_log_direction_fk')
                ->references('id')->on('direction_exchange')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks_status_log', function (Blueprint $table) {
            $table->dropForeign('tasks_status_log_direction_fk');
            // индекс и колонку — по желанию
            // $table->dropColumn('id_direction_exchange');
        });
    }
};
