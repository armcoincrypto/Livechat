<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_ai_learning_candidates', function (Blueprint $table): void {
            $table->decimal('evaluation_score', 5, 2)->nullable()->after('score');
            $table->string('evaluation_result', 32)->nullable()->index()->after('evaluation_score');
            $table->text('evaluation_summary')->nullable()->after('evaluation_result');
            $table->json('evaluation_flags')->nullable()->after('evaluation_summary');
            $table->timestamp('evaluated_at')->nullable()->after('evaluation_flags');
            $table->timestamp('auto_promoted_at')->nullable()->after('evaluated_at');
        });
    }

    public function down(): void
    {
        Schema::table('support_ai_learning_candidates', function (Blueprint $table): void {
            $table->dropColumn([
                'evaluation_score',
                'evaluation_result',
                'evaluation_summary',
                'evaluation_flags',
                'evaluated_at',
                'auto_promoted_at',
            ]);
        });
    }
};
