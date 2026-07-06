<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ai_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_code', 64)->unique();
            $table->string('intent', 64)->index();
            $table->string('category', 64)->index();
            $table->string('title', 255);
            $table->text('template_text');
            $table->string('template_type', 32)->index();
            $table->string('language', 8)->default('ru')->index();
            $table->unsignedInteger('frequency')->default(0);
            $table->string('confidence', 16)->default('medium');
            $table->boolean('active')->default(true);
            $table->boolean('requires_validation')->default(false);
            $table->json('source_conversation_ids')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ai_templates');
    }
};
