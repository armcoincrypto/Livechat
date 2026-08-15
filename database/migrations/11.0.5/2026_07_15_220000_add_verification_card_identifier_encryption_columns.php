<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only. No backfill. Does not touch card_number / card_number_string.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_card', function (Blueprint $table) {
            if (! Schema::hasColumn('verification_card', 'card_number_ciphertext')) {
                $table->text('card_number_ciphertext')->nullable()->after('card_number_string');
            }
            if (! Schema::hasColumn('verification_card', 'card_number_string_ciphertext')) {
                $table->text('card_number_string_ciphertext')->nullable()->after('card_number_ciphertext');
            }
            if (! Schema::hasColumn('verification_card', 'card_number_lookup')) {
                $table->char('card_number_lookup', 64)->nullable()->after('card_number_string_ciphertext');
            }
            if (! Schema::hasColumn('verification_card', 'card_number_last4')) {
                $table->string('card_number_last4', 8)->nullable()->after('card_number_lookup');
            }
            if (! Schema::hasColumn('verification_card', 'identifier_key_version')) {
                $table->unsignedTinyInteger('identifier_key_version')->default(0)->after('card_number_last4');
            }
        });

        try {
            Schema::table('verification_card', function (Blueprint $table) {
                $table->index('card_number_lookup', 'verification_card_lookup_idx');
            });
        } catch (Throwable) {
            // Index may already exist on re-apply.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('verification_card', function (Blueprint $table) {
                $table->dropIndex('verification_card_lookup_idx');
            });
        } catch (Throwable) {
            // ignore
        }

        $cols = [
            'card_number_ciphertext',
            'card_number_string_ciphertext',
            'card_number_lookup',
            'card_number_last4',
            'identifier_key_version',
        ];
        $drop = array_values(array_filter(
            $cols,
            static fn (string $col): bool => Schema::hasColumn('verification_card', $col)
        ));

        if ($drop !== []) {
            Schema::table('verification_card', function (Blueprint $table) use ($drop) {
                $table->dropColumn($drop);
            });
        }
    }
};
