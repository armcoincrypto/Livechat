<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks accounts created via OAuth with a random unusable password hash.
 * Cleared when the user sets a real password (change/reset).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }
        if (Schema::hasColumn('users', 'oauth_password_is_placeholder')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('oauth_password_is_placeholder')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'oauth_password_is_placeholder')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('oauth_password_is_placeholder');
        });
    }
};
