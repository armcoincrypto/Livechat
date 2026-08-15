<?php

namespace iEXPackages\AMLPlugin\Drivers\Shard;

use iEXPackages\AMLPlugin\Contracts\AMLResponseInterface;
use iEXPackages\AMLPlugin\Responses\AMLResponseAbstract;
use Illuminate\Support\Arr;

/**
 * Класс ShardResponse
 *
 * Обрабатывает и предоставляет доступ к данным ответа от AML-сервиса Shard.
 *
 * @package iEXPackages\AMLPlugin\Drivers\Shard
 */
class ShardResponse extends AMLResponseAbstract implements AMLResponseInterface
{
    /**
     * Проверяет, находится ли проверка в статусе ожидания.
     *
     * @return bool True, если проверка в ожидании.
     */
    public function isPending(): bool
    {
        return Arr::get($this->data, 'risk_score') == 0;
    }

    /**
     * Проверяет, успешно ли завершилась проверка.
     *
     * @return bool True, если проверка успешна.
     */
    public function isSuccessful(): bool
    {
        return true;
    }

    /**
     * Возвращает общий процентный уровень риска адреса или транзакции.
     *
     * @return float Уровень риска (от 0 до 100%).
     */
    public function getRiskScore(): float
    {
        return (float)Arr::get($this->data, 'risk_score', 0);
    }


    /**
     * Возвращает проверяемый адрес.
     *
     * @return string|null Адрес, прошедший проверку.
     */
    protected function getAddress(): ?string
    {
        return '';
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
            'raw_data'     => $this->rawData(),
        ];
    }

    /**
     * Возвращает сырые исходные данные ответа.
     *
     * @return array Полный массив данных ответа от Shard API.
     */
    public function rawData(): array
    {
        return $this->data;
    }

    public function getRiskSignals(): array
    {
        return [];
    }
}
