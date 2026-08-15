<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('extra_field_values', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('field_id');

            // Владелец значения: User или Task
            $table->string('owner_type', 191);
            $table->unsignedBigInteger('owner_id');

            // значение (строка), как у тебя в task_fields.field_value
            $table->longText('field_value')->nullable();

            $table->timestamps();

            $table->foreign('field_id')
                ->references('id')
                ->on('extra_fields')
                ->cascadeOnDelete();

            $table->index(['owner_type', 'owner_id']);
            $table->unique(['field_id', 'owner_type', 'owner_id'], 'uniq_field_owner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_field_values');
    }
};
