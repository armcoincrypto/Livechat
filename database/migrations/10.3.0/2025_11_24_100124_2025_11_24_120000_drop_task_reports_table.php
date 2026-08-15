<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('task_reports');
    }

    public function down(): void
    {
        Schema::create('task_reports', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedBigInteger('id_task');
            $table->decimal('profit', 24, 8)->nullable();
            $table->decimal('add_course', 24, 8)->nullable();
            $table->decimal('your_add_course', 24, 8)->nullable();
            $table->decimal('commission', 24, 8)->nullable();
            $table->decimal('commission_s', 24, 8)->nullable();

            $table->boolean('is_group_commission')->default(0);
            $table->decimal('group_commission_value', 24, 8)->nullable();
            $table->string('sign')->nullable();
            $table->decimal('profit_usd', 24, 8)->nullable();
            $table->boolean('is_bestchange')->default(0);

            $table->timestamps();

            // FK (если было)
            $table->foreign('id_task')
                ->references('id')
                ->on('tasks')
                ->onDelete('cascade');
        });
    }
};
