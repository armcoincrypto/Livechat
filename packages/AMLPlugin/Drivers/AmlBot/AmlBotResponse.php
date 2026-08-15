<?php

namespace iEXPackages\AMLPlugin\Drivers\AmlBot;

use iEXPackages\AMLPlugin\Contracts\AMLResponseInterface;
use iEXPackages\AMLPlugin\Responses\AMLResponseAbstract;
use Illuminate\Support\Arr;

/**
 * Класс AmlBotResponse
 *
 * Реализует обработку и получение данных из ответа AML-сервиса AmlBot.
 *
 * @package iEXPackages\AMLPlugin\Drivers\AmlBot
 */
class AmlBotResponse extends AMLResponseAbstract implements AMLResponseInterface
{
    /**
     * Константа успешного статуса ответа от сервиса AmlBot.
     */
    protected const STATUS_SUCCESS = 'SUCCESS';

    /**
     * Проверяет, находится ли проверка в состоянии ожидания.
     *
     * @return bool True, если проверка ещё не завершена.
     */
    public function isPending(): bool
    {
        return Arr::get($this->data, 'data.status') !== self::STATUS_SUCCESS;
    }

    /**
     * Проверяет, успешно ли завершилась AML-проверка.
     *
     * @return bool True, если проверка завершена успешно.
     */
    public function isSuccessful(): bool
    {
        return Arr::get($this->data, 'data.status') === self::STATUS_SUCCESS;
    }

    /**
     * Возвращает общий процент риска адреса или транзакции.
     *
     * @return float Риск в процентах.
     */
    public function getRiskScore(): float
    {
        $riskScore = Arr::get($this->data, 'data.riskscore', 0);

        return (float)iex_number_format((float)$riskScore * 100);
    }

    /**
     * Возвращает сигналы риска с ненулевыми значениями.
     *
     * @return array Ассоциативный массив сигналов риска и их значений в процентах.
     */
    public function getRiskSignals(): array
    {
        $signals = Arr::get($this->data, 'data.signals', []);

        if (!is_array($signals)) {
            return [];
        }

        return collect($signals)
            ->filter(fn($value) => !empty($value) && (float)$value !== 0)
            ->map(fn($value) => round((float)$value * 100, 2))
            ->toArray();
    }

    /**
     * Возвращает адрес, который был проверен.
     *
     * @return string|null Проверенный адрес или null, если адрес не указан.
     */
    protected function getAddress(): ?string
    {
        return Arr::get($this->data, 'address');
    }

    /**
     * Формирует массив данных для сохранения в базу данных.
     *
     * @return array Готовый для сохранения массив данных.
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
     * Возвращает сырые исходные данные от AmlBot API.
     *
     * @return array Исходный ответ API.
     */
    public function rawData(): array
    {
        return $this->data;
    }
}
