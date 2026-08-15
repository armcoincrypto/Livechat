<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directions_city_profile_pivot', function (Blueprint $table) {
            $table->id();

            // Связь с конкретной записью direction_exchange_cities
            $table->unsignedBigInteger('id_direction_exchange_city');

            // Связь с профилем города/направления
            $table->unsignedBigInteger('direction_city_profile_id');

            $table->timestamps();

            // FK на direction_exchange_cities
            $table->foreign('id_direction_exchange_city', 'dcp_pivot_dec_fk')
                ->references('id')
                ->on('direction_exchange_cities')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            // FK на direction_city_profiles
            $table->foreign('direction_city_profile_id', 'dcp_pivot_profile_fk')
                ->references('id')
                ->on('direction_city_profiles')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            // Для удобства: один профиль на одну запись direction_exchange_cities
            // (если захочешь несколько профилей на один город/направление — уберёшь unique)
            $table->unique(['id_direction_exchange_city']);

            // Частый фильтр по профилю
            $table->index(['direction_city_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directions_city_profile_pivot');
    }
};
