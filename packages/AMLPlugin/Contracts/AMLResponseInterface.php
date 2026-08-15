<?php

namespace iEXPackages\AMLPlugin\Contracts;

/**
 * Интерфейс AMLResponseInterface
 *
 * Описывает обязательные методы, которые должны реализовывать
 * все Response-классы AML-драйверов для обработки и предоставления данных проверки.
 *
 * @package iEXPackages\AMLPlugin\Contracts
 */
interface AMLResponseInterface
{
    /**
     * Проверяет, находится ли проверка AML в состоянии ожидания результатов.
     *
     * @return bool True, если статус проверки pending.
     */
    public function isPending(): bool;

    /**
     * Проверяет, успешно ли завершилась AML-проверка.
     *
     * @return bool True, если проверка успешна.
     */
    public function isSuccessful(): bool;

    /**
     * Возвращает общий процентный показатель риска транзакции или адреса.
     *
     * @return float Уровень риска (в процентах).
     */
    public function getRiskScore(): float;

    /**
     * Возвращает список сигналов (критериев) риска с их процентными значениями.
     *
     * @return array Ассоциативный массив сигналов риска и их уровней.
     */
    public function getRiskSignals(): array;

    /**
     * Возвращает список сигналов риска, превышающих заданные пороговые значения.
     *
     * @return array Ассоциативный массив сигналов, превысивших допустимый риск.
     */
    public function getExceededRiskSignals(): array;

    /**
     * Подготавливает и возвращает данные проверки в формате, удобном для сохранения в базу данных.
     *
     * @return array Массив данных для сохранения.
     */
    public function getDataToDatabase(): array;

    /**
     * Возвращает сырые исходные данные, полученные от AML-сервиса.
     *
     * @return array Исходный массив данных.
     */
    public function rawData(): array;

    /**
     * Проверяет, превышает ли общий риск транзакции заданный пороговый уровень.
     *
     * @param int|null $threshold Пороговое значение риска. Если null, берётся из конфигурации.
     * @return bool True, если риск превышает порог.
     */
    public function isRiskExceeded(int $threshold = null): bool;
}
