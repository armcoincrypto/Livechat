<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks_meta', function (Blueprint $table): void {
            if (!Schema::hasColumn('tasks_meta', 'chat_handoff_to_human')) {
                $table->boolean('chat_handoff_to_human')
                    ->default(false)
                    ->after('freeze_scam')
                    ->comment('true — чат передан оператору, AI не отвечает');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks_meta', function (Blueprint $table): void {
            if (Schema::hasColumn('tasks_meta', 'chat_handoff_to_human')) {
                $table->dropColumn('chat_handoff_to_human');
            }
        });
    }
};
