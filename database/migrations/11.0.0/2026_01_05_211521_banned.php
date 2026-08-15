<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('banned', function (Blueprint $table) {
            // тип фильтра: ip/email/domain/cidr
            $table->string('type', 16)->nullable()->after('id');

            // нормализованный ключ (для уникальности и поиска)
            $table->string('filter_key', 160)->nullable()->after('type');

            // диапазоны для ip/cidr (IPv4 как unsigned int)
            $table->unsignedBigInteger('ip_from')->nullable()->after('expired_at');
            $table->unsignedBigInteger('ip_to')->nullable()->after('ip_from');

            // индексы
            $table->index(['type'], 'banned_type_idx');
            $table->index(['expired_at'], 'banned_expired_at_idx');
            $table->index(['ip_from', 'ip_to'], 'banned_ip_range_idx');

            // уникальность по filter_key
            $table->unique(['filter_key'], 'banned_filter_key_uniq');
        });
    }

    public function down(): void
    {
        Schema::table('banned', function (Blueprint $table) {
            $table->dropUnique('banned_filter_key_uniq');
            $table->dropIndex('banned_type_idx');
            $table->dropIndex('banned_expired_at_idx');
            $table->dropIndex('banned_ip_range_idx');

            $table->dropColumn(['type', 'filter_key', 'ip_from', 'ip_to']);
        });
    }
};
