<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table): void {
            $table->unsignedTinyInteger('merchant_validator_fail_strategy')
                ->default(1)
                ->comment('0=ignore, 1=hard fail');
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table): void {
            $table->dropColumn('merchant_validator_fail_strategy');
        });
    }
};
