<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->string('public_support_id', 16)->nullable()->unique()->after('uuid');
            $table->unsignedBigInteger('last_operator_telegram_user_id')->nullable()->after('last_operator_message_at');
            $table->string('last_operator_telegram_username', 64)->nullable()->after('last_operator_telegram_user_id');
            $table->string('last_operator_display_name', 191)->nullable()->after('last_operator_telegram_username');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("UPDATE support_conversations SET public_support_id = CONCAT('S-', LPAD(id, 7, '0')) WHERE public_support_id IS NULL");
        } else {
            foreach (DB::table('support_conversations')->orderBy('id')->cursor() as $row) {
                DB::table('support_conversations')->where('id', $row->id)->update([
                    'public_support_id' => 'S-'.str_pad((string) $row->id, 7, '0', STR_PAD_LEFT),
                ]);
            }
        }

        DB::table('support_conversations')->where('status', 'open')->update(['status' => 'waiting_operator']);
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->dropUnique(['public_support_id']);
        });

        DB::table('support_conversations')->whereIn('status', ['waiting_operator', 'waiting_visitor'])->update(['status' => 'open']);

        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->dropColumn([
                'public_support_id',
                'last_operator_telegram_user_id',
                'last_operator_telegram_username',
                'last_operator_display_name',
            ]);
        });
    }
};
