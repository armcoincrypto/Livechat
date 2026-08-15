<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sumsub_ids', function (Blueprint $table) {
            $table->boolean('is_completed')
                ->default(false)
                ->after('status');
            $table->index('is_completed');

            $table->string('provider')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('sumsub_ids', function (Blueprint $table) {
            $table->dropIndex(['is_completed']);
            $table->dropColumn('is_completed');
        });
    }
};
