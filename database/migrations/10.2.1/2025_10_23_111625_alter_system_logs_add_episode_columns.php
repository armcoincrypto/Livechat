<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            // состояние проблемы (если ещё нет)
            if (!Schema::hasColumn('system_logs', 'state')) {
                $table->enum('state', ['active','resolved'])
                    ->default('active')
                    ->index()
                    ->after('level');
            }

            // поля эпизода:
            if (!Schema::hasColumn('system_logs', 'first_seen_at')) {
                $table->timestamp('first_seen_at')->nullable()->index()->after('occurred_at');
            }
            if (!Schema::hasColumn('system_logs', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->index()->after('first_seen_at');
            }
            if (!Schema::hasColumn('system_logs', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->index()->after('last_seen_at');
            }
            if (!Schema::hasColumn('system_logs', 'times_seen')) {
                $table->unsignedBigInteger('times_seen')->default(0)->after('resolved_at');
            }
            if (!Schema::hasColumn('system_logs', 'last_message')) {
                $table->text('last_message')->nullable()->after('times_seen');
            }
            if (!Schema::hasColumn('system_logs', 'last_context')) {
                $table->json('last_context')->nullable()->after('last_message');
            }

            // === Проверка индексов без Doctrine (через information_schema)
            $dbName = DB::getDatabaseName();

            $indexExists = function (string $indexName) use ($dbName): bool {
                $row = DB::table('information_schema.STATISTICS')
                    ->select(DB::raw('COUNT(1) AS cnt'))
                    ->where('TABLE_SCHEMA', $dbName)
                    ->where('TABLE_NAME', 'system_logs')
                    ->where('INDEX_NAME', $indexName)
                    ->first();

                return (int)($row->cnt ?? 0) > 0;
            };

            if (! $indexExists('system_logs_module_name_code_entity_state_index')) {
                $table->index(
                    ['module','name','code','entity_type','entity_id','state'],
                    'system_logs_module_name_code_entity_state_index'
                );
            }

            if (! $indexExists('syslogs_key_lookup')) {
                $table->index(
                    ['module','name','code','entity_type','entity_id','occurred_at','id'],
                    'syslogs_key_lookup'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            // Откат по необходимости (обычно индексы и episode-поля можно безопасно удалить)
            // Пример:
            // if (Schema::hasColumn('system_logs', 'last_context')) { $table->dropColumn('last_context'); }
            // if (Schema::hasColumn('system_logs', 'last_message')) { $table->dropColumn('last_message'); }
            // if (Schema::hasColumn('system_logs', 'times_seen')) { $table->dropColumn('times_seen'); }
            // if (Schema::hasColumn('system_logs', 'resolved_at')) { $table->dropColumn('resolved_at'); }
            // if (Schema::hasColumn('system_logs', 'last_seen_at')) { $table->dropColumn('last_seen_at'); }
            // if (Schema::hasColumn('system_logs', 'first_seen_at')) { $table->dropColumn('first_seen_at'); }
            // if (Schema::hasColumn('system_logs', 'state')) { $table->dropColumn('state'); }

//             if (DB::getSchemaBuilder()->hasIndex('system_logs', 'system_logs_module_name_code_entity_state_index')) {
//                 $table->dropIndex('system_logs_module_name_code_entity_state_index');
//             }
            // if (DB::getSchemaBuilder()->hasIndex('system_logs', 'syslogs_key_lookup')) {
            //     $table->dropIndex('syslogs_key_lookup');
            // }
        });
    }
};
