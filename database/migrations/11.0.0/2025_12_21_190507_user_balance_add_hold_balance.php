<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_balance', function (Blueprint $table) {
            $table->double('hold_balance')->default(0)->after('balance');
        });
    }

    public function down(): void
    {
        Schema::table('user_balance', function (Blueprint $table) {
            $table->dropColumn('hold_balance');
        });
    }
};
