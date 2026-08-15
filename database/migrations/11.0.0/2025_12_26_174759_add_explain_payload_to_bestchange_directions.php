<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bestchange_directions', function (Blueprint $table) {
            if (!Schema::hasColumn('bestchange_directions', 'explain_payload')) {
                $table->json('explain_payload')
                    ->nullable()
                    ->comment('Explain / audit данных расчёта курса BestChange');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bestchange_directions', function (Blueprint $table) {
            if (Schema::hasColumn('bestchange_directions', 'explain_payload')) {
                $table->dropColumn('explain_payload');
            }
        });
    }
};
