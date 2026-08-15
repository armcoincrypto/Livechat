<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants_transaction_data', function (Blueprint $table): void {
            $table->string('account_validator_type', 64)
                ->nullable()
                ->after('is_checkout_url')
                ->comment('Тип применённого валидатора');

            $table->boolean('account_validator_passed')
                ->nullable()
                ->after('account_validator_type')
                ->comment('Прошёл ли адрес проверку валидатором');
        });
    }

    public function down(): void
    {
        Schema::table('merchants_transaction_data', function (Blueprint $table): void {
            $table->dropColumn([
                'account_validator_type',
                'account_validator_passed',
            ]);
        });
    }
};
