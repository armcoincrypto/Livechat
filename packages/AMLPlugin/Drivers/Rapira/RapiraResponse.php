<?php

namespace iEXPackages\AMLPlugin\Drivers\Rapira;

use iEXPackages\AMLPlugin\Contracts\AMLResponseInterface;
use iEXPackages\AMLPlugin\Responses\AMLResponseAbstract;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Класс RapiraResponse
 *
 * Обрабатывает и предоставляет доступ к данным ответа от AML-сервиса Rapira.
 *
 * @package iEXPackages\AMLPlugin\Drivers\Rapira
 */
class RapiraResponse extends AMLResponseAbstract implements AMLResponseInterface
{
    /**
     * Статус успешного ответа от сервиса Rapira.
     */
    protected const STATUS_SUCCESS = 'SUCCESS';

    /**
     * Проверяет, находится ли проверка в статусе ожидания.
     *
     * @return bool True, если проверка в ожидании.
     */
    public function isPending(): bool
    {
        return Arr::get($this->data, 'status') !== self::STATUS_SUCCESS;
    }

    /**
     * Проверяет, успешно ли завершилась проверка.
     *
     * @return bool True, если проверка успешна.
     */
    public function isSuccessful(): bool
    {
        return Arr::get($this->data, 'status') === self::STATUS_SUCCESS;
    }

    /**
     * Возвращает общий процентный уровень риска адреса или транзакции.
     *
     * @return float Уровень риска (от 0 до 100%).
     */
    public function getRiskScore(): float
    {
        return (float)iex_number_format((float)Arr::get($this->data, 'riskscore', 0) * 100);
    }

    /**
     * Возвращает список сигналов риска с их процентными значениями.
     *
     * @return array Ассоциативный массив сигналов риска с уровнями в процентах.
     */
    public function getRiskSignals(): array
    {
        $signals = Arr::get($this->data, 'signals', []);

        if (!is_array($signals)) {
            return [];
        }

        return collect($signals)
            ->filter(fn($value) => !empty($value) && (float)$value !== 0)
            ->map(fn($value) => round((float)$value * 100, 2))
            ->toArray();
    }

    /**
     * Возвращает проверяемый адрес.
     *
     * @return string|null Адрес, прошедший проверку.
     */
    protected function getAddress(): ?string
    {
        return Arr::get($this->data, 'address');
    }

    /**
     * Подготавливает данные для сохранения в базе данных.
     *
     * @return array Массив данных, готовых к сохранению.
     */
    public function getDataToDatabase(): array
    {
        return [
            'address'      => $this->getAddress(),
            'risk_score'   => $this->getRiskScore(),
            'risk_signals' => $this->getRiskSignals(),
            'asset'        => Arr::get($this->data, 'asset'),
            'chain'        => Arr::get($this->data, 'chain'),
            'direction'    => Arr::get($this->data, 'direction'),
            'status'       => Arr::get($this->data, 'status'),
            'uuid'         => Arr::get($this->data, 'uuid'),
            'created_at'   => Arr::get($this->data, 'createTime'),
            'raw_data'     => $this->rawData(),
        ];
    }

    /**
     * Возвращает сырые исходные данные ответа.
     *
     * @return array Полный массив данных ответа от Rapira API.
     */
    public function rawData(): array
    {
        return $this->data;
    }
}
