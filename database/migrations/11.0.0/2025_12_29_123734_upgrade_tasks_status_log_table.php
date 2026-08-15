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
            if (Schema::hasColumn('tasks_status_log', 'place_change')) {
                $table->unsignedTinyInteger('place_change')
                    ->default(0)
                    ->comment('0=site, 1=admin, 2=merchant, 3=system')
                    ->change();
            } else {
                $table->unsignedTinyInteger('place_change')
                    ->default(0)
                    ->comment('0=site, 1=admin, 2=merchant, 3=system');
            }


            if (!Schema::hasColumn('tasks_status_log', 'course_display_text')) {
                $table->string('course_display_text', 255)
                    ->nullable()
                    ->after('course_display')
                    ->comment('Курс строкой для UI, например "87.12 RUB = 1 USDT"');
            }


            if (!Schema::hasColumn('tasks_status_log', 'in_amount')) {
                $table->decimal('in_amount', 30, 18)
                    ->nullable()
                    ->after('in_price')
                    ->comment('Числовая сумма отдаю (snapshot)');
            }

            if (!Schema::hasColumn('tasks_status_log', 'out_amount')) {
                $table->decimal('out_amount', 30, 18)
                    ->nullable()
                    ->after('out_price')
                    ->comment('Числовая сумма получаю (snapshot)');
            }

            if (!Schema::hasColumn('tasks_status_log', 'course_display_rate')) {
                $table->decimal('course_display_rate', 30, 18)
                    ->nullable()
                    ->after('course_display_text')
                    ->comment('Курс числом (показанный пользователю)');
            }

            if (!Schema::hasColumn('tasks_status_log', 'course_float_rate')) {
                $table->decimal('course_float_rate', 30, 18)
                    ->nullable()
                    ->after('course_display_rate')
                    ->comment('Курс числом (плавающий/реальный)');
            }

            if (!Schema::hasColumn('tasks_status_log', 'course_diff_percent')) {
                $table->decimal('course_diff_percent', 10, 6)
                    ->nullable()
                    ->after('course_float_rate')
                    ->comment('Процент отличия course_display_rate от course_float_rate');
            }

            /**
             * 5) Индексы (ускоряют историю по заявке и фильтры)
             */
            if (!Schema::hasColumn('tasks_status_log', 'created_at')) {
                // у тебя created_at есть, но на всякий случай
                $table->timestamp('created_at')->nullable();
            }

            $table->index(['id_task', 'created_at'], 'tasks_status_log_task_created_idx');
            $table->index('new_status', 'tasks_status_log_new_status_idx');
            $table->index('user_id', 'tasks_status_log_user_id_idx');
            $table->index('place_change', 'tasks_status_log_place_change_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tasks_status_log', function (Blueprint $table) {
            // Индексы
            $table->dropIndex('tasks_status_log_task_created_idx');
            $table->dropIndex('tasks_status_log_new_status_idx');
            $table->dropIndex('tasks_status_log_user_id_idx');
            $table->dropIndex('tasks_status_log_place_change_idx');

            // Колонки, которые добавили
            if (Schema::hasColumn('tasks_status_log', 'course_display_text')) {
                $table->dropColumn('course_display_text');
            }
            if (Schema::hasColumn('tasks_status_log', 'in_amount')) {
                $table->dropColumn('in_amount');
            }
            if (Schema::hasColumn('tasks_status_log', 'out_amount')) {
                $table->dropColumn('out_amount');
            }
            if (Schema::hasColumn('tasks_status_log', 'course_display_rate')) {
                $table->dropColumn('course_display_rate');
            }
            if (Schema::hasColumn('tasks_status_log', 'course_float_rate')) {
                $table->dropColumn('course_float_rate');
            }
            if (Schema::hasColumn('tasks_status_log', 'course_diff_percent')) {
                $table->dropColumn('course_diff_percent');
            }
        });
    }
};
