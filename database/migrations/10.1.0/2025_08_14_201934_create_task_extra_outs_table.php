<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_extra_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_task')->constrained('tasks')->cascadeOnDelete();
            $table->string('label', 255)->nullable();
            $table->decimal('amount', 36, 18)->default(0);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->index('id_task');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_extra_outs');
    }
};
