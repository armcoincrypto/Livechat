<?php

declare(strict_types=1);

namespace App\Settings;

use iEXPackages\DynamicConfig\DynamicConfigModel;

/**
 * GatewayConfig
 *
 * Обёртка над DynamicConfig для управления логированием платёжных шлюзов.
 *
 * Ключи:
 *  - gateway.disable_merchant_logs (bool)  — отключить логи мерчантов
 *  - gateway.disable_payout_logs   (bool)  — отключить логи выплат
 *
 * Scope: global (общие для всей системы).
 */
final class GatewayConfig extends DynamicConfigModel
{
    /**
     * Префикс ключей конфига.
     */
    protected function prefix(): string
    {
        return 'gateway';
    }

    /**
     * Отключено ли логирование мерчантов.
     *
     * true  — логи НЕ пишутся
     * false — логи пишутся
     */
    public function isMerchantLogDisabled(): bool
    {
        return $this->getBool('disable_merchant_logs', false);
    }

    /**
     * Отключено ли логирование выплат.
     *
     * true  — логи НЕ пишутся
     * false — логи пишутся
     */
    public function isPayoutLogDisabled(): bool
    {
        return $this->getBool('disable_payout_logs', false);
    }

    /**
     * Удобные инверсные методы (для использования в коде).
     */
    public function isMerchantLogEnabled(): bool
    {
        return ! $this->isMerchantLogDisabled();
    }

    public function isPayoutLogEnabled(): bool
    {
        return ! $this->isPayoutLogDisabled();
    }

    /**
     * Список полей gateway-конфига.
     */
    protected function fields(): array
    {
        return [
            'disable_merchant_logs',
            'disable_payout_logs',
        ];
    }

    public function update(array $data): void
    {
        $normalized = [];

        if (array_key_exists('disable_merchant_logs', $data)) {
            $normalized['disable_merchant_logs'] = (bool) $data['disable_merchant_logs'];
        }

        if (array_key_exists('disable_payout_logs', $data)) {
            $normalized['disable_payout_logs'] = (bool) $data['disable_payout_logs'];
        }

        if ($normalized === []) {
            return;
        }

        parent::update($normalized);
    }
}
