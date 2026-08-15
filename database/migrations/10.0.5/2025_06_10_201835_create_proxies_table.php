<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proxies', function (Blueprint $table) {
            $table->id();
            $table->string('host');
            $table->unsignedSmallInteger('port');
            $table->string('login')->nullable();
            $table->string('password')->nullable();
            $table->enum('type', ['http', 'https', 'socks4', 'socks5'])->default('http');
            $table->boolean('status')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedSmallInteger('fail_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proxies');
    }
};
