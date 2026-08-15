<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bestchange_exchanger_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('changer_id')->unique();
            $table->unsignedBigInteger('seen_count')->default(0);
            $table->unsignedBigInteger('selected_count')->default(0);
            $table->unsignedBigInteger('rejected_count')->default(0);
            $table->unsignedBigInteger('error_count')->default(0);
            $table->unsignedInteger('quality_score_sum')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_selected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bestchange_exchanger_stats');
    }
};
