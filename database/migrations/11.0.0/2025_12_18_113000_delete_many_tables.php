<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Удаление устаревших таблиц логов.
     */
    public function up(): void
    {
        Schema::dropIfExists('log_merchants_events');
        Schema::dropIfExists('logs_autopayment_orders_events');
        Schema::dropIfExists('gateways_payments_logs');
    }

    /**
     * Восстановление таблиц (если потребуется откат).
     *
     * ВАЖНО:
     * Структура таблиц здесь не восстанавливается намеренно,
     * так как таблицы признаны устаревшими и выведены из системы.
     */
    public function down(): void
    {
        // intentionally left empty
    }
};
