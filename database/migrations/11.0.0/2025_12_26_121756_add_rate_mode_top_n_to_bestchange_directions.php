<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bestchange_directions', function (Blueprint $table) {
            $table->string('rate_mode', 30)->nullable()->comment('position|median_top_n|weighted_avg_top_n');
            $table->unsignedSmallInteger('top_n')->nullable()->comment('N for median/weighted (e.g. 5)');
        });
    }

    public function down(): void
    {
        Schema::table('bestchange_directions', function (Blueprint $table) {
            $table->dropColumn(['rate_mode', 'top_n']);
        });
    }
};
