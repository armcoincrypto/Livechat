<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserve_closures', function (Blueprint $table): void {
            $table->integer('ancestor_id');
            $table->integer('descendant_id');
            $table->unsignedSmallInteger('depth');

            $table->primary(['ancestor_id', 'descendant_id']);

            $table->index(['descendant_id', 'depth'], 'idx_rc_desc_depth');
            $table->index(['ancestor_id', 'depth'], 'idx_rc_anc_depth');

            $table->foreign('ancestor_id')->references('id')->on('reserves')->cascadeOnDelete();
            $table->foreign('descendant_id')->references('id')->on('reserves')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserve_closures');
    }
};
