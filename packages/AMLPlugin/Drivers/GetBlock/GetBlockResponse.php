<?php

namespace iEXPackages\AMLPlugin\Drivers\GetBlock;

use iEXPackages\AMLPlugin\Contracts\AMLResponseInterface;
use iEXPackages\AMLPlugin\Responses\AMLResponseAbstract;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Class GetBlockResponse
 *
 * Реализация Response-класса для AML-драйвера GetBlock.
 * Обрабатывает ответ сервиса GetBlock и предоставляет
 * удобный доступ к ключевым данным проверки транзакций и адресов.
 *
 * @package iEXPackages\AMLPlugin\Drivers\GetBlock
 */
class GetBlockResponse extends AMLResponseAbstract implements AMLResponseInterface
{
    /**
     * Статус проверки: ожидание результатов.
     */
    protected const STATUS_PENDING = 'PENDING';

    /**
     * Статус проверки: успешная проверка.
     */
    protected const STATUS_SUCCESS = 'SUCCESS';

    /**
     * Проверяет, находится ли проверка в статусе "PENDING" (ожидание).
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return Arr::get($this->data, 'result.check.status') === self::STATUS_PENDING;
    }

    /**
     * Проверяет, успешно ли завершилась проверка AML.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return Arr::get($this->data, 'result.check.status') === self::STATUS_SUCCESS;
    }

    /**
     * Возвращает общий процент риска транзакции/адреса.
     *
     * @return float Уровень риска в процентах.
     */
    public function getRiskScore(): float
    {
        return round((float) Arr::get($this->data, 'result.check.report.riskscore', 0) * 100, 2);
    }

    /**
     * Возвращает массив сигналов риска, исключая нулевые значения.
     *
     * @return array Массив сигналов риска с уровнем риска в процентах.
     */
    public function getRiskSignals(): array
    {
        $signals = Arr::get($this->data, 'result.check.report.signals', []);

        if (!is_array($signals)) {
            return [];
        }

        return collect($signals)
            ->filter(fn($value) => (float)$value !== 0)
            ->map(fn($value) => round($value * 100, 2))
            ->toArray();
    }

    /**
     * Возвращает проверяемый адрес.
     *
     * @return string|null Адрес, прошедший проверку.
     */
    protected function getAddress(): ?string
    {
        return Arr::get($this->data, 'result.check.address');
    }

    /**
     * Подготавливает и возвращает данные проверки в удобном формате для сохранения в базе данных.
     *
     * @return array
     */
    public function getDataToDatabase(): array
    {
        return [
            'hash'          => Arr::get($this->data, 'result.check.hash'),
            'currency'      => Arr::get($this->data, 'result.check.currency'),
            'address'       => $this->getAddress(),
            'status'        => Arr::get($this->data, 'result.check.status'),
            'risk_score'    => $this->getRiskScore(),
            'pdf_link'      => Arr::get($this->data, 'result.check.pdfLink'),
            'share_link'    => Arr::get($this->data, 'result.check.shareLink'),
            'counterparty'  => Arr::get($this->data, 'result.check.counterparty', []),
            'risk_signals'  => $this->getRiskSignals(),
            'meta'          => Arr::get($this->data, 'result.meta', []),
            'checked_at'    => Arr::get($this->data, 'result.check.resultDate'),
            'raw_data'      => $this->rawData(),
        ];
    }

    /**
     * Возвращает исходные сырые данные от AML-сервиса.
     *
     * @return array
     */
    public function rawData(): array
    {
        return Arr::get($this->data, 'result', []);
    }
}
