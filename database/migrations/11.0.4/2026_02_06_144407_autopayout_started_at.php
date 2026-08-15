<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks_info', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks_info', 'autopayout_token')) {
                $table->string('autopayout_token', 64)->nullable();
                $table->index('autopayout_token', 'tasks_info_autopayout_token_index');
            }

            if (!Schema::hasColumn('tasks_info', 'autopayout_started_at')) {
                $table->timestamp('autopayout_started_at')->nullable()->after('autopayout_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks_info', function (Blueprint $table) {
            if (Schema::hasColumn('tasks_info', 'autopayout_started_at')) {
                $table->dropColumn('autopayout_started_at');
            }

            if (Schema::hasColumn('tasks_info', 'autopayout_token')) {
                $table->dropIndex('tasks_info_autopayout_token_index');
                $table->dropColumn('autopayout_token');
            }
        });
    }
};
