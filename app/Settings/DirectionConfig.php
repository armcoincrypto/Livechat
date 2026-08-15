<?php

declare(strict_types=1);

namespace App\Settings;

use iEXPackages\DynamicConfig\DynamicConfigModel;

/**
 * DirectionConfig
 *
 * Настройки блока "Неоплаченные заявки" и генерации мин/макс цены.
 */
final class DirectionConfig extends DynamicConfigModel
{
    protected function prefix(): string
    {
        return 'direction_settings';
    }

    public function isUnpaidAutoDeleteEnabled(): bool
    {
        return $this->getBool('unpaid_auto_delete', false);
    }

    /**
     * @return int[]
     */
    public function unpaidOrderStatuses(): array
    {
        $value = $this->getArray('unpaid_order_status', []);

        return array_map(static fn ($v) => (int) $v, $value);
    }

    public function unpaidTimeDay(): int
    {
        return $this->getInt('unpaid_time_day', 0);
    }

    public function unpaidTimeHour(): int
    {
        return $this->getInt('unpaid_time_hour', 0);
    }

    public function unpaidTimeMinute(): int
    {
        return $this->getInt('unpaid_time_minute', 0);
    }

    public function generateMinPrice(): int
    {
        return $this->getInt('generate_min_price', 0);
    }

    public function generateMaxPrice(): int
    {
        return $this->getInt('generate_max_price', 0);
    }

    public function profitCalculationType(): int
    {
        return $this->getInt('profit_calculation_type', 0);
    }

    /**
     * @return string[]
     */
    protected function fields(): array
    {
        return [
            'unpaid_auto_delete',
            'unpaid_order_status',
            'unpaid_time_day',
            'unpaid_time_hour',
            'unpaid_time_minute',
            'generate_min_price',
            'generate_max_price',
            'profit_calculation_type',
        ];
    }
}
