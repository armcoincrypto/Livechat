<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire temporary compatibility plaintext columns after encrypted-only cutover.
 * Does not touch ciphertext / lookup / last4 / key version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_card', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('verification_card', 'card_number')) {
                $drop[] = 'card_number';
            }
            if (Schema::hasColumn('verification_card', 'card_number_string')) {
                $drop[] = 'card_number_string';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('verification_card', function (Blueprint $table) {
            if (! Schema::hasColumn('verification_card', 'card_number')) {
                $table->string('card_number', 191)->nullable();
            }
            if (! Schema::hasColumn('verification_card', 'card_number_string')) {
                $table->string('card_number_string', 191)->nullable();
            }
        });
        // Note: down() recreates EMPTY columns only.
        // Full value restore requires protected pre-retirement mysqldump import.
    }
};
