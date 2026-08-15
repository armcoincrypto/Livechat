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
        Schema::table('auth_audit_events', function (Blueprint $table) {
            $table->string('session_id', 191)->nullable()->index();
            $table->string('session_prev_id', 191)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auth_audit_events', function (Blueprint $table) {
            //
        });
    }
};
