<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('extra_fields', function (Blueprint $table) {
            $table->id();

            $table->string('key_id', 100)->unique();

            $table->json('name');

            // где используется: user_registration | user_profile | order (если понадобится)
            $table->string('scope', 50)->index();

            // переносить ли в снапшот заявки
            $table->boolean('attach_to_order')->default(true);

            // правила
            $table->unsignedSmallInteger('min_char')->default(0);
            $table->unsignedSmallInteger('max_char')->default(0);

            // как у тебя: 0 => required, иначе nullable
            $table->unsignedTinyInteger('obligatory_field')->default(1);

            $table->unsignedTinyInteger('remove_spaces')->default(0);

            $table->string('start_with', 191)->nullable();
            $table->string('end_with', 191)->nullable();

            $table->string('validator_type', 191)->nullable();

            $table->unsignedInteger('sorting')->default(0);

            // как у тебя: status=1 выключено
            $table->unsignedTinyInteger('status')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_fields');
    }
};
