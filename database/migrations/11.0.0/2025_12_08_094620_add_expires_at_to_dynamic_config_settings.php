<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dynamic_config_settings', function (Blueprint $table): void {
            $table->dateTime('expires_at')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('dynamic_config_settings', function (Blueprint $table): void {
            $table->dropColumn('expires_at');
        });
    }
};
