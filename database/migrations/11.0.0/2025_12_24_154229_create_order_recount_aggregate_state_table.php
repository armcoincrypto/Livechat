<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_recount_aggregate_state', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->unsignedBigInteger('last_audit_id')->default(0);
            $table->timestamp('updated_at')->useCurrent();
        });

        // один ключ
        DB::table('order_recount_aggregate_state')->insert([
            'key' => 'daily',
            'last_audit_id' => 0,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_recount_aggregate_state');
    }
};
