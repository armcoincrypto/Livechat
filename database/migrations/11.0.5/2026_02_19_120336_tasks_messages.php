<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks_messages', function (Blueprint $table): void {
            if (!Schema::hasColumn('tasks_messages', 'ai_meta')) {
                $table->json('ai_meta')->nullable()->after('file_path');
            }

            if (!Schema::hasColumn('tasks_messages', 'ai_error')) {
                $table->text('ai_error')->nullable()->after('ai_meta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('tasks_messages', 'ai_meta')) {
                $table->dropColumn('ai_meta');
            }
            if (Schema::hasColumn('tasks_messages', 'ai_error')) {
                $table->dropColumn('ai_error');
            }
        });
    }
};
