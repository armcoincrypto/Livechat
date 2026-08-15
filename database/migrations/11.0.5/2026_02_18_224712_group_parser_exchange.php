<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('group_parser_exchange', function (Blueprint $table): void {
            if (!Schema::hasColumn('group_parser_exchange', 'last_duration_ms')) {
                $table->unsignedInteger('last_duration_ms')->default(0)->after('last_updated_at');
            }
            if (!Schema::hasColumn('group_parser_exchange', 'last_total')) {
                $table->unsignedInteger('last_total')->default(0)->after('last_duration_ms');
            }
            if (!Schema::hasColumn('group_parser_exchange', 'last_updated')) {
                $table->unsignedInteger('last_updated')->default(0)->after('last_total');
            }
            if (!Schema::hasColumn('group_parser_exchange', 'last_errors')) {
                $table->unsignedInteger('last_errors')->default(0)->after('last_updated');
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_parser_exchange', function (Blueprint $table): void {
            $table->dropColumn(['last_duration_ms', 'last_total', 'last_updated', 'last_errors']);
        });
    }
};
