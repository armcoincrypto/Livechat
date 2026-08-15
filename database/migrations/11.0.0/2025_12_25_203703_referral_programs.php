<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        $programId = DB::table('referral_programs')
            ->where('is_reg', 1)
            ->orderBy('id')
            ->value('id');

        if ($programId !== null) {
            DB::table('dynamic_config_settings')->updateOrInsert(
                [
                    'scope_type' => 'global',
                    'scope_id'   => null,
                    'key'        => 'default_referral_program_id',
                ],
                [
                    // value = JSON column → кладём валидный JSON
                    'value'      => json_encode((int) $programId, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        Schema::table('referral_programs', function (Blueprint $table) {
            if (Schema::hasColumn('referral_programs', 'is_reg')) {
                $table->dropColumn('is_reg');
            }
        });
    }

    public function down(): void
    {
        Schema::table('referral_programs', function (Blueprint $table) {
            if (!Schema::hasColumn('referral_programs', 'is_reg')) {
                $table->unsignedTinyInteger('is_reg')->default(0);
            }
        });

        // Откат значения default_referral_program_id не делаем (это ок)
    }
};
