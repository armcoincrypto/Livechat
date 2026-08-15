<?php

declare(strict_types=1);

namespace App\Settings;


use iEXPackages\DynamicConfig\DynamicConfigModel;

/**
 * BinInspectorConfig
 *
 * Настройки плагина BinInspector (ранее "card_info").
 *
 * Хранение:
 *  - scope_type = 'plugins'
 *  - scope_id   = null
 *  - ключи:
 *      bin_inspector.driver
 *      bin_inspector.is_api
 *      bin_inspector.save_data
 *      bin_inspector.api_key
 *      bin_inspector.ids_currencies
 */
final class BinInspectorConfig extends DynamicConfigModel
{
    /**
     * Настройки плагина живут в scope_type = plugins.
     */
    protected function scopeType(): string
    {
        return 'plugins';
    }

    protected function scopeId(): ?int
    {
        return null;
    }

    protected function prefix(): string
    {
        return 'bin_inspector';
    }

    protected function fields(): array
    {
        return [
            'driver',
            'is_api',
            'save_data',
            'api_key',
            'ids_currencies',
        ];
    }

    // --- Геттеры ---

    public function driver(): ?string
    {
        $value = $this->getString('driver', 'bincodes');

        return $value !== '' ? $value : null;
    }

    public function isApiEnabled(): bool
    {
        return $this->getBool('is_api', false);
    }

    public function shouldSaveData(): bool
    {
        return $this->getBool('save_data', true);
    }

    public function apiKey(): ?string
    {
        $value = $this->getString('api_key', '');

        return $value !== '' ? $value : null;
    }

    public function idsCurrencies(): ?string
    {
        $value = $this->getString('ids_currencies', '');

        return $value !== '' ? $value : null;
    }

    /**
     * Удобный метод — получить список ID валют в виде массива.
     *
     * @return int[]
     */
    public function idsCurrenciesArray(): array
    {
        $raw = $this->idsCurrencies();

        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return array_map(
            static fn ($v) => (int) $v,
            array_filter(array_map('trim', explode(',', $raw)))
        );
    }

    /**
     * Массовое обновление настроек BinInspector.
     *
     * @param array{
     *     driver?: string|null,
     *     is_api?: bool|int,
     *     save_data?: bool|int,
     *     api_key?: string|null,
     *     ids_currencies?: string|null
     * } $data
     */
    public function update(array $data): void
    {
        $normalized = [];

        if (array_key_exists('driver', $data)) {
            $normalized['driver'] = $data['driver'] ?: null;
        }

        if (array_key_exists('is_api', $data)) {
            $normalized['is_api'] = (bool) $data['is_api'];
        }

        if (array_key_exists('save_data', $data)) {
            $normalized['save_data'] = (bool) $data['save_data'];
        }

        if (array_key_exists('api_key', $data)) {
            $normalized['api_key'] = $data['api_key'] ?: null;
        }

        if (array_key_exists('ids_currencies', $data)) {
            $normalized['ids_currencies'] = $data['ids_currencies'] ?: null;
        }

        parent::update($normalized);
    }
}
