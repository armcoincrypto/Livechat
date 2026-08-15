<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Legacy alias for {@see OrderExchangeTotal}.
 *
 * Table `tasks_convert_log` was renamed to `order_exchange_totals` and column
 * `to_usd` → `exchange_usd` in migration 2025_11_22_165255_rename_tasks_convert_log.
 *
 * @deprecated Use OrderExchangeTotal directly.
 *
 * @property-read float|string|null $to_usd Legacy accessor for exchange_usd
 */
class TaskConvertLog extends OrderExchangeTotal
{
    /**
     * @return float|string|null
     */
    public function getToUsdAttribute(): float|string|null
    {
        return $this->exchange_usd;
    }
}
