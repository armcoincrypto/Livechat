<?php

namespace iEXPackages\AMLPlugin\Responses;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Абстрактный класс AMLResponseAbstract
 *
 * Предоставляет базовые методы для обработки ответов от AML-сервисов.
 * Каждый конкретный Response-класс должен реализовать специфические методы для извлечения
 * сигналов риска и общего уровня риска из полученных данных.
 *
 * @package iEXPackages\AMLPlugin\Responses
 */
abstract class AMLResponseAbstract
{
    /**
     * Сырые данные ответа от AML-сервиса.
     *
     * @var array
     */
    protected array $data;

    /**
     * Конфигурация текущего AML-драйвера.
     *
     * @var array
     */
    protected array $config;

    /**
     * Конструктор AMLResponseAbstract.
     *
     * @param array $data   Сырые данные ответа от AML-сервиса
     * @param array $config Конфигурация текущего AML-драйвера
     */
    public function __construct(array $data, array $config = [])
    {
        $this->data = $data;
        $this->config = $config;
    }

    /**
     * Возвращает список сигналов риска и их уровни.
     *
     * @return array Массив, где ключ – идентификатор сигнала риска, значение – уровень риска в процентах.
     */
    abstract public function getRiskSignals(): array;

    /**
     * Возвращает общий уровень риска транзакции (процент).
     *
     * @return float Уровень риска в процентах.
     */
    abstract public function getRiskScore(): float;

    /**
     * Проверяет, превышает ли общий риск транзакции заданный пороговый уровень.
     *
     * @param int|null $threshold Порог риска для проверки. Если не задан, используется конфигурация 'risk_level'.
     * @return bool Возвращает true, если текущий риск превышает порог.
     */
    public function isRiskExceeded(int $threshold = null): bool
    {
        $threshold ??= (int)($this->config['risk_level'] ?? 0);

        return $this->getRiskScore() >= $threshold;
    }

    /**
     * Возвращает список сигналов риска, которые превысили индивидуально заданные пороги.
     *
     * Сравнивает текущие уровни риска сигналов с конфигурацией (например: max_risk_signalName).
     * Возвращает только сигналы, которые превышают установленные лимиты.
     *
     * @return array Массив сигналов риска, превысивших заданные пороговые значения.
     */
    public function getExceededRiskSignals(): array
    {
        $signals = $this->getRiskSignals();

        $riskThresholds = collect($this->config)->filter(
            fn($value, $key) => str_starts_with($key, 'max_risk_')
        );

        return collect($signals)->filter(
            function ($currentRiskValue, $signalKey) use ($riskThresholds) {
                $configKey = 'max_risk_' . $signalKey;

                // Пропускаем, если конфигурация для сигнала отсутствует
                if (!$riskThresholds->has($configKey)) {
                    return false;
                }

                // Возвращаем сигнал, если он превышает пороговое значение из конфигурации
                return (float)$currentRiskValue >= (float)$riskThresholds[$configKey];
            }
        )->toArray();
    }
}
