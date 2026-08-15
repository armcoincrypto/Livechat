<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auth_audit_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedInteger('user_id')->nullable()->index();
            $table->string('email', 191)->nullable()->index();

            $table->string('guard', 32)->nullable()->index();   // web/admin/api
            $table->string('channel', 32)->nullable()->index(); // frontend/admin/api/system

            $table->string('event', 64)->index();
            $table->string('result', 32)->index();
            $table->string('reason_code', 64)->nullable()->index();
            $table->string('message', 255)->nullable();

            $table->string('ip', 64)->nullable()->index();
            $table->string('ip_prev', 64)->nullable();

            $table->text('user_agent')->nullable();

            $table->string('device_id', 80)->nullable()->index();
            $table->boolean('is_new_device')->default(false)->index();

            $table->string('browser', 64)->nullable();
            $table->string('os', 64)->nullable();
            $table->string('device', 64)->nullable();

            $table->string('country', 128)->nullable()->index();
            $table->string('city', 128)->nullable()->index();
            $table->string('iso_code', 8)->nullable()->index();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'auth_audit_user_created_idx');
            $table->index(['ip', 'created_at'], 'auth_audit_ip_created_idx');
            $table->index(['event', 'created_at'], 'auth_audit_event_created_idx');
            $table->index(['result', 'created_at'], 'auth_audit_result_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_audit_events');
    }
};
