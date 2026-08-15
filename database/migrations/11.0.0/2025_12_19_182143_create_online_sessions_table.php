<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('online_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('type', 10); // user|guest
            $table->string('identity', 80); // user:123 / guest:ULID

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('gid', 64)->nullable();

            // Денормализация — чтобы отдавать id,name,email без join
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();

            $table->timestamp('first_seen')->index();
            $table->timestamp('last_seen')->index();

            $table->unsignedInteger('hits')->default(0);

            $table->string('ip', 45)->nullable();
            $table->binary('ua_hash')->nullable(); // md5 raw 16 bytes

            $table->timestamps();

            $table->unique(['identity']);
            $table->index(['type', 'last_seen']);
            $table->index(['user_id']);
            $table->index(['gid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_sessions');
    }
};
