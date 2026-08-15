<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 1) Приводим поля к реальным типам дат
            $table->timestamp('email_verified_at')->nullable()->change();
            $table->timestamp('role_expired_at')->nullable()->change();

            // Оборот пользователя в USD
            $table->decimal('order_total_exchanges', 24, 8)->default(0)->change();

            // 3) Удаляем больше не нужное поле
            $table->dropColumn('min_withdrawal');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Откат типов дат обратно в varchar(191)
            $table->string('email_verified_at', 191)->nullable()->change();
            $table->string('role_expired_at', 191)->nullable()->change();

            // Откат денежных полей обратно в double
            $table->double('personal_discount')->default(0)->change();
            $table->double('personal_ref_discount')->default(0)->change();
            $table->double('max_ref_discount')->default(0)->change();
            $table->double('order_total_exchanges')->default(0)->change();

            // Возвращаем колонку min_withdrawal (как было)
            $table->float('min_withdrawal')->default(0);
        });
    }
};
