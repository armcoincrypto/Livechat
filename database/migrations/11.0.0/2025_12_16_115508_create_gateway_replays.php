<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gateway_replays', function (Blueprint $table) {
            $table->id();
            $table->string('replay_key', 64)->unique();

            $table->string('gateway', 64)->index();
            $table->string('operation', 64)->index();
            $table->string('direction', 32)->index();

            $table->string('http_method', 16);
            $table->text('url')->nullable();

            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();

            $table->json('response_json')->nullable();
            $table->unsignedSmallInteger('http_status')->default(200);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_replays');
    }
};
