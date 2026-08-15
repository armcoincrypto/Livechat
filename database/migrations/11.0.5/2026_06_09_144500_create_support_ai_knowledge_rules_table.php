<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ai_knowledge_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('rule_code', 32)->unique();
            $table->string('category', 64)->index();
            $table->string('title', 191);
            $table->string('intent', 64)->nullable()->index();
            $table->string('language', 16)->nullable()->index();
            $table->text('rule_text');
            $table->text('answer_template')->nullable();
            $table->text('safe_phrasing')->nullable();
            $table->json('question_patterns')->nullable();
            $table->json('source_conversation_ids')->nullable();
            $table->unsignedInteger('source_message_count')->default(0);
            $table->string('confidence', 16)->default('medium')->index();
            $table->boolean('requires_validation')->default(true)->index();
            $table->boolean('active')->default(false)->index();
            $table->string('risk_level', 16)->default('medium')->index();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['active', 'requires_validation']);
            $table->index(['category', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ai_knowledge_rules');
    }
};
