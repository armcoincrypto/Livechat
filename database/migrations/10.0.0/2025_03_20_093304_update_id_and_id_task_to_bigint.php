<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    // Функция проверки и удаления ключа
    private function dropForeignIfExists($tableName, $foreignKeyName)
    {
        $dbName = DB::getDatabaseName();
        $exists = DB::select("
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND CONSTRAINT_NAME = ?
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$dbName, $tableName, $foreignKeyName]);

        if (!empty($exists)) {
            Schema::table($tableName, function (Blueprint $table) use ($foreignKeyName) {
                $table->dropForeign($foreignKeyName);
            });
        }
    }

    public function up(): void
    {
        // Список всех таблиц, где поле id_task связано с tasks.id
        $tables = [
            'log_autopayments',
            'tasks_card_details',
            'tasks_operators',
            'log_error_merchants',
            'history_excode',
            'tasks_comments',
            'tasks_shots',
            'tasks_operators_logs',
            'tasks_profits',
            'tasks_rates_data',
            'task_reports',
            'wallets_history',
            'applications_steps_logs',
            'history_payment_transactions',
            'task_log',
            'whitebit_withdraw',
            'banned_user',
            'tasks_chat',
            'tasks_files',
            'merchant_account',
            'pay_transaction_hash',
            'tasks_comments_users',
            'history_recalculation',
            'tasks_fields',
            'merchant_transaction_ids',
            'tasks_status_log',
            'tasks_info',
            'merchant_transaction_hash',
            'history_internal_accounts',
            'task_log_confirmation',
            'tasks_history_operators',
            'reviews',
            'histories_codes',
            'referral_log',
            'e_voucher_codes',
            'wallet_transactions',
            'tasks_convert_log',
            'tasks_requisites',
            'task_single_log_confirm'
        ];

        // Удаление существующих внешних ключей безопасно
        foreach ($tables as $tableName) {
            $foreignKeyName = "{$tableName}_id_task_foreign";
            $this->dropForeignIfExists($tableName, $foreignKeyName);
        }

        // 2. Обновляем поле tasks.id на unsignedBigInteger
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement()->change();
        });



        // 3. Обновляем все связанные поля id_task на unsignedBigInteger
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('id_task')->change();
            });
        }

        // 4. Восстанавливаем внешние ключи
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('id_task')->references('id')->on('tasks')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Список таблиц (повторный)
        $tables = [
            'log_autopayments',
            'tasks_card_details',
            'tasks_operators',
            'log_error_merchants',
            'history_excode',
            'tasks_comments',
            'tasks_shots',
            'tasks_operators_logs',
            'tasks_profits',
            'tasks_rates_data',
            'task_reports',
            'wallets_history',
            'applications_steps_logs',
            'history_payment_transactions',
            'task_log',
            'whitebit_withdraw',
            'banned_user',
            'tasks_chat',
            'tasks_files',
            'merchant_account',
            'pay_transaction_hash',
            'tasks_comments_users',
            'history_recalculation',
            'tasks_fields',
            'merchant_transaction_ids',
            'tasks_status_log',
            'tasks_info',
            'merchant_transaction_hash',
            'history_internal_accounts',
            'task_log_confirmation',
            'tasks_history_operators',
            'reviews',
            'histories_codes',
            'referral_log',
            'e_voucher_codes',
            'wallet_transactions',
            'tasks_convert_log',
            'tasks_requisites',
            'task_single_log_confirm'
        ];

        // Удаление внешних ключей
        foreach ($tables as $tableName) {
            $foreignKeyName = "{$tableName}_id_task_foreign";
            $this->dropForeignIfExists($tableName, $foreignKeyName);
        }

        // 2. Возвращаем tasks.id обратно к integer
        Schema::table('tasks', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->change();
        });

        // 3. Возвращаем id_task обратно к integer
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->integer('id_task')->change();
            });
        }

        // 4. Восстанавливаем внешние ключи обратно
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('id_task')->references('id')->on('tasks')->cascadeOnDelete();
            });
        }
    }
};
