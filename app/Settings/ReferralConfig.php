<?php

declare(strict_types=1);

namespace App\Settings;

use iEXPackages\DynamicConfig\DynamicConfigModel;

final class ReferralConfig extends DynamicConfigModel
{
    /**
     * Префикс для всех ключей этого конфига.
     *
     * Пример ключей:
     *  - referral.enabled_referral_system
     *  - referral.referral_storage_periods
     *  - referral.referral_fallback_code_currencies
     */
    protected function prefix(): string
    {
        return '';
    }

    /**
     * Настройки партнёрской системы живут в scope_type = plugins (как модуль).
     */
    protected function scopeType(): string
    {
        return 'global';
    }

    /**
     * Один набор настроек на всю установку.
     * Если в будущем потребуется — можно привязать к license/project и т.п.
     */
    protected function scopeId(): ?int
    {
        return null;
    }

    /**
     * Список полей, за которые отвечает этот конфиг.
     *
     * @return string[]
     */
    protected function fields(): array
    {
        return [
            'enabled_referral_system',
            'referral_storage_periods',
            'referral_lifetime_day',
            'minimum_bonus_payout',
            'is_enabled_referral_logs',
            'type_partner_deductions',
            'id_referral_code_currency',
            'referral_fallback_code_currencies',
            'default_referral_program_id'
        ];
    }

    public function defaultReferralProgramId(): int
    {
        return $this->getInt('default_referral_program_id', 1);
    }

    // ---------------------------------------------------------------------
    // Геттеры
    // ---------------------------------------------------------------------

    /**
     * Включена ли партнёрская система (0/1).
     */
    public function isEnabled(): int
    {
        return $this->getInt('enabled_referral_system', 0);
    }

    /**
     * Период хранения данных партнёрки (число).
     */
    public function storagePeriods(): int
    {
        return $this->getInt('referral_storage_periods', 0);
    }

    /**
     * Срок жизни партнёрского начисления/реферала в днях (число).
     */
    public function lifetimeDay(): int
    {
        return $this->getInt('referral_lifetime_day', 0);
    }

    /**
     * Минимальная сумма выплаты бонуса (число).
     * Если в UI это может быть строкой — хранение всё равно будет int.
     */
    public function minimumBonusPayout(): int
    {
        return $this->getInt('minimum_bonus_payout', 0);
    }

    /**
     * Включены ли логи партнёрской системы (0/1).
     */
    public function isReferralLogsEnabled(): int
    {
        return $this->getInt('is_enabled_referral_logs', 0);
    }

    /**
     * Тип удержаний партнёра (число, enum по твоей логике).
     */
    public function typePartnerDeductions(): int
    {
        return $this->getInt('type_partner_deductions', 0);
    }

    /**
     * ID валюты для реферального кода (число).
     */
    public function referralCodeCurrencyId(): int
    {
        return $this->getInt('id_referral_code_currency', 0);
    }

    /**
     * Запасной список валют (fallback) для реферального кода.
     *
     * @return int[]
     */
    public function referralFallbackCodeCurrencies(): array
    {
        $value = $this->getArray('referral_fallback_code_currencies', []);

        // Разрешаем строку "1,2,3" на всякий случай
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)));
        }

        $intArray = array_map(static fn ($v) => (string) $v, (array) $value);

        return array_values($intArray);
    }



    // ---------------------------------------------------------------------
    // Обновление
    // ---------------------------------------------------------------------

    /**
     * Массовое обновление настроек партнёрской системы.
     *
     * @param array{
     *     enabled_referral_system?: bool|int,
     *     referral_storage_periods?: int|string,
     *     referral_lifetime_day?: int|string,
     *     minimum_bonus_payout?: int|string,
     *     is_enabled_referral_logs?: bool|int,
     *     type_partner_deductions?: int|string,
     *     id_referral_code_currency?: int|string,
     *     referral_fallback_code_currencies?: array<int|string>|string
     * } $data
     */
    public function update(array $data): void
    {
        $normalized = [];

        if (array_key_exists('default_referral_program_id', $data)) {
            $normalized['default_referral_program_id'] = (int) $data['default_referral_program_id'];
        }

        if (array_key_exists('enabled_referral_system', $data)) {
            $normalized['enabled_referral_system'] = (int) $data['enabled_referral_system'];
        }

        if (array_key_exists('referral_storage_periods', $data)) {
            $normalized['referral_storage_periods'] = (int) $data['referral_storage_periods'];
        }

        if (array_key_exists('referral_lifetime_day', $data)) {
            $normalized['referral_lifetime_day'] = (int) $data['referral_lifetime_day'];
        }

        if (array_key_exists('minimum_bonus_payout', $data)) {
            $normalized['minimum_bonus_payout'] = (int) $data['minimum_bonus_payout'];
        }

        if (array_key_exists('is_enabled_referral_logs', $data)) {
            $normalized['is_enabled_referral_logs'] = (int) $data['is_enabled_referral_logs'];
        }

        if (array_key_exists('type_partner_deductions', $data)) {
            $normalized['type_partner_deductions'] = (int) $data['type_partner_deductions'];
        }

        if (array_key_exists('id_referral_code_currency', $data)) {
            $normalized['id_referral_code_currency'] = (int) $data['id_referral_code_currency'];
        }

        if (array_key_exists('referral_fallback_code_currencies', $data)) {
            $value = $data['referral_fallback_code_currencies'];

            if (is_string($value)) {
                $value = array_filter(array_map('trim', explode(',', $value)));
            }

            $normalized['referral_fallback_code_currencies'] = array_values(
                array_map(static fn ($v) => (string) $v, (array) $value)
            );
        }

        parent::update($normalized);
    }

    /**
     * Приведение к массиву для UI/API.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled_referral_system'            => $this->isEnabled(),
            'referral_storage_periods'           => $this->storagePeriods(),
            'referral_lifetime_day'              => $this->lifetimeDay(),
            'minimum_bonus_payout'               => $this->minimumBonusPayout(),
            'is_enabled_referral_logs'           => $this->isReferralLogsEnabled(),
            'type_partner_deductions'            => $this->typePartnerDeductions(),
            'id_referral_code_currency'          => $this->referralCodeCurrencyId(),
            'referral_fallback_code_currencies'  => $this->referralFallbackCodeCurrencies(),
            'default_referral_program_id' => $this->defaultReferralProgramId(),
        ];
    }
}
