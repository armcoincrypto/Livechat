<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {
            $table->tinyInteger('type_verification')->default(0)->comment('0 - по умолчанию, 1 - индивидуально');

            // Без верификации
            $table->string('no_verification_min_amount')->nullable();
            $table->string('no_verification_max_amount')->nullable();
            $table->string('no_verification_fee', 20)->nullable()->comment('Комиссия, может быть отрицательной или процентом (например: "-1%", "2")');
            $table->json('no_verification_description')->nullable();

            // С верификацией
            $table->string('verification_min_amount')->nullable();
            $table->string('verification_max_amount')->nullable();
            $table->string('verification_fee', 20)->nullable()->comment('Комиссия, может быть отрицательной или процентом (например: "-1%", "2")');
            $table->json('verification_description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('direction_exchange', function (Blueprint $table) {
            $table->dropColumn([
                'type_verification',
                'no_verification_min_amount',
                'no_verification_max_amount',
                'no_verification_fee',
                'no_verification_description',
                'verification_min_amount',
                'verification_max_amount',
                'verification_fee',
                'verification_description'
            ]);
        });
    }
};
