<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_recount_states', function (Blueprint $table) {
            $table->unsignedInteger('status_last_global')->nullable()->after('last_rate_value_global');
            $table->timestamp('status_entered_at_global')->nullable()->after('status_last_global');
            $table->unsignedInteger('recount_in_status_count_global')->default(0)->after('status_entered_at_global');

            $table->unsignedInteger('status_last_floating')->nullable()->after('last_rate_value_floating');
            $table->timestamp('status_entered_at_floating')->nullable()->after('status_last_floating');
            $table->unsignedInteger('recount_in_status_count_floating')->default(0)->after('status_entered_at_floating');

            $table->dropColumn(['status_last', 'status_entered_at', 'recount_in_status_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
