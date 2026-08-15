<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bestchange_market_reports', function (Blueprint $table) {
            $table->id();
            $table->date('day')->index();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['day'], 'ux_bc_market_report_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bestchange_market_reports');
    }
};
