<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('checkbox_agreement_direction_exchange', function (Blueprint $table) {
            $table->id();

            $table->foreignId('checkbox_agreement_id')
                ->constrained('checkbox_agreements', 'id', 'chkbx_agreement_id_foreign')
                ->cascadeOnDelete();

            // явно укажем integer без unsigned:
            $table->integer('direction_exchange_id')->unsigned(false);

            $table->foreign('direction_exchange_id', 'dir_exchange_id_foreign')
                ->references('id')->on('direction_exchange')
                ->cascadeOnDelete();

            $table->unique(['checkbox_agreement_id', 'direction_exchange_id'], 'checkbox_direction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkbox_agreement_direction_exchange');
    }
};
