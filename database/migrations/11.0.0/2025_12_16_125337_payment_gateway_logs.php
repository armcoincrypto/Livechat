<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_gateway_logs', 'is_sandbox')) {
                $table->boolean('is_sandbox')
                    ->default(false)
                    ->after('meta')
                    ->index();
            }

            if (!Schema::hasColumn('payment_gateway_logs', 'replay_key')) {
                $table->string('replay_key', 191)
                    ->nullable()
                    ->after('is_sandbox')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            if (Schema::hasColumn('payment_gateway_logs', 'replay_key')) {
                $table->dropIndex(['replay_key']);
                $table->dropColumn('replay_key');
            }

            if (Schema::hasColumn('payment_gateway_logs', 'is_sandbox')) {
                $table->dropIndex(['is_sandbox']);
                $table->dropColumn('is_sandbox');
            }
        });
    }
};
