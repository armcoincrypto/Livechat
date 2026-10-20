<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated OAuth identity links (Architecture C).
 *
 * Does not replace legacy users.provider / users.provider_id columns.
 * One linked identity per (user, provider); globally unique provider subjects.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_oauth_identities')) {
            return;
        }

        Schema::create('user_oauth_identities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->index();
            $table->string('provider', 32);
            $table->string('provider_subject', 191);
            $table->string('provider_email', 191)->nullable();
            $table->boolean('provider_email_verified')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_subject'], 'user_oauth_identities_provider_subject_unique');
            $table->unique(['user_id', 'provider'], 'user_oauth_identities_user_provider_unique');
            $table->index(['provider_email'], 'user_oauth_identities_provider_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_oauth_identities');
    }
};
