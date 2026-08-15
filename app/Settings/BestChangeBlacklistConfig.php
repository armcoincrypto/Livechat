<?php

declare(strict_types=1);

namespace App\Settings;

use iEXPackages\DynamicConfig\DynamicConfigModel;

final class BestChangeBlacklistConfig extends DynamicConfigModel
{
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
        return 'bestchange_blacklist';
    }

    /**
     * @return string[]
     */
    protected function fields(): array
    {
        return [
            'is_status',
            'categories',
            'method',
            'api_id',
            'api_key',
            'columns',
            'type',
        ];
    }

    // ----------------- GETTERS -----------------

    public function isEnabled(): bool
    {
        return $this->getBool('is_status', false);
    }

    /**
     * Категории чёрного списка (типы фильтрации).
     *
     * Примеры:
     *  - ip
     *  - email
     *  - wallet_from
     *
     * @return string[]
     */
    public function categories(): array
    {
        $value = $this->getArray('categories', []);

        return array_values(
            array_map(
                static fn ($v) => trim((string) $v),
                (array) $value
            )
        );
    }

    public function method(): int
    {
        return $this->getInt('method', 0);
    }

    public function apiId(): string
    {
        return $this->getString('api_id', '');
    }

    public function apiKey(): string
    {
        return $this->getString('api_key', '');
    }

    public function columns(): string
    {
        return $this->getString('columns', '');
    }

    public function type(): string
    {
        return $this->getString('type', '');
    }

    // ----------------- UPDATE -----------------

    /**
     * Обновление настроек.
     *
     * ВАЖНО: здесь мы нормализуем вход, чтобы в базе всегда было одинаково:
     *  - categories: int[]
     *  - is_status: bool
     *  - method: int
     *  - остальные: string
     *
     * @param array{
     *   is_status?: bool|int,
     *   categories?: int[]|string[]|null,
     *   method?: int|string,
     *   api_id?: string|null,
     *   api_key?: string|null,
     *   columns?: string|null,
     *   type?: string|null
     * } $data
     */
    public function update(array $data): void
    {
        $normalized = [];

        if (array_key_exists('is_status', $data)) {
            $normalized['is_status'] = (bool) $data['is_status'];
        }

        if (array_key_exists('categories', $data)) {
            $categories = (array) ($data['categories'] ?? []);

            $normalized['categories'] = array_values(
                array_map(
                    static fn ($v) => trim((string) $v),
                    $categories
                )
            );
        }

        // method или method_type
        if (array_key_exists('method', $data)) {
            $normalized['method'] = (int) $data['method'];
        } elseif (array_key_exists('method_type', $data)) {
            $normalized['method'] = (int) $data['method_type'];
        }

        if (array_key_exists('api_id', $data)) {
            $normalized['api_id'] = (string) ($data['api_id'] ?? '');
        }


        if (array_key_exists('api_key', $data)) {
            $normalized['api_key'] = (string) ($data['api_key'] ?? '');
        }

        if (array_key_exists('columns', $data)) {
            $normalized['columns'] = (string) ($data['columns'] ?? '');
        }

        if (array_key_exists('type', $data)) {
            $normalized['type'] = (string) ($data['type'] ?? '');
        }

        if ($normalized === []) {
            return;
        }

        parent::update($normalized);
    }

    /**
     * Для UI/API.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'is_status'   => $this->isEnabled(),
            'categories'  => $this->categories(),
            'method'      => $this->method(),
            'api_id'      => $this->apiId(),
            'api_key'     => $this->apiKey(),
            'columns'     => $this->columns(),
            'type'        => $this->type(),
        ];
    }
}
