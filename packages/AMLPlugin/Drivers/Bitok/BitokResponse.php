<?php

namespace iEXPackages\AMLPlugin\Drivers\Bitok;

use iEXPackages\AMLPlugin\Contracts\AMLResponseInterface;
use iEXPackages\AMLPlugin\Responses\AMLResponseAbstract;
use Illuminate\Support\Arr;


/**
 * Класс BitokResponse
 *
 * Предоставляет удобный доступ к данным ответа от AML-сервиса Bitok.
 *
 * @package iEXPackages\AMLPlugin\Drivers\Bitok
 */
class BitokResponse extends AMLResponseAbstract implements AMLResponseInterface
{
    /**
     * Статус проверки – в процессе.
     */
    protected const STATUS_CHECKING = 'checking';

    /**
     * Статус проверки – завершена.
     */
    protected const STATUS_CHECKED = 'checked';

    /**
     * Проверяет, находится ли AML-проверка в состоянии ожидания результатов.
     *
     * @return bool True, если проверка ещё идёт.
     */
    public function isPending(): bool
    {
        return Arr::get($this->data, 'check_state.counterparty') === self::STATUS_CHECKING;
    }

    /**
     * Проверяет, успешно ли завершилась AML-проверка.
     *
     * @return bool True, если проверка завершена успешно.
     */
    public function isSuccessful(): bool
    {
        return Arr::get($this->data, 'check_state.counterparty') === self::STATUS_CHECKED;
    }

    /**
     * Возвращает общий процент риска по проверке транзакции или адреса.
     *
     * @return float Риск в процентах (от 0 до 100).
     */
    public function getRiskScore(): float
    {
        return (float)iex_number_format((float)Arr::get($this->data, 'risk_score', 0) * 100);
    }

    /**
     * Возвращает сигналы риска, если они есть в ответе (в текущей реализации пустой массив).
     *
     * @return array Ассоциативный массив сигналов риска.
     */
    public function getRiskSignals(): array
    {
        return [];
    }

    /**
     * Возвращает адрес, прошедший AML-проверку.
     *
     * @return string|null Адрес или null, если не указан.
     */
    protected function getAddress(): ?string
    {
        return Arr::get($this->data, 'input_address');
    }

    /**
     * Формирует массив данных, готовых для сохранения в базе данных.
     *
     * @return array Ассоциативный массив данных.
     */
    public function getDataToDatabase(): array
    {
        return [
            'transaction'  => Arr::get($this->data, 'tx_hash'),
            'with_address' => $this->getAddress(),
            'risk_score'   => $this->getRiskScore(),
            'raw_data'     => $this->rawData(),
        ];
    }

    /**
     * Возвращает сырые исходные данные, полученные от AML-сервиса Bitok.
     *
     * @return array Исходный массив данных ответа API.
     */
    public function rawData(): array
    {
        return $this->data;
    }
}
