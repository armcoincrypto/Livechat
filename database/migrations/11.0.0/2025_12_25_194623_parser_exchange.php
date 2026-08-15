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
        // 1) Удаляем курсы, у которых id_group указывает на несуществующую группу
        DB::table('parser_exchange')
            ->whereNotNull('id_group')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('group_parser_exchange')
                    ->whereColumn('group_parser_exchange.id', 'parser_exchange.id_group');
            })
            ->delete();

        // 2) Приводим типы к совместимым (оба unsigned int)
        Schema::table('group_parser_exchange', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement()->change();
        });

        Schema::table('parser_exchange', function (Blueprint $table) {
            $table->unsignedInteger('id_group')->nullable()->change();
        });

        // 3) Индекс под FK (если уже есть — Laravel может ругнуться, поэтому проверяем)
        $indexes = DB::select("SHOW INDEX FROM `parser_exchange` WHERE Key_name = 'parser_exchange_id_group_index'");
        if (count($indexes) === 0) {
            Schema::table('parser_exchange', function (Blueprint $table) {
                $table->index('id_group', 'parser_exchange_id_group_index');
            });
        }

        // 4) FK (если еще не стоит)
        Schema::table('parser_exchange', function (Blueprint $table) {
            // на случай если кто-то руками уже ставил — лучше явно не дропать тут,
            // а ставить под стабильным именем:
            $table->foreign('id_group', 'fk_parser_exchange_group')
                ->references('id')
                ->on('group_parser_exchange')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('parser_exchange', function (Blueprint $table) {
            $table->dropForeign('fk_parser_exchange_group');
        });

        // Типы назад обычно не откатывают (но если хочешь — скажи, сделаю корректный down)
    }
};
