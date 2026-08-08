<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Services;

use iEXPackages\BestChange\DTO\PairKey;

/**
 * PairKeyFactory
 *
 * Фабрика для создания ключей пар BestChange.
 *
 * Назначение:
 * - централизовать логику формирования ключей валютных пар;
 * - исключить дублирование логики сборки ключей вида "from-to" и "from-to-city";
 * - обеспечить единый формат ключей для:
 *   - HTTP-запросов к BestChange API (/rates/{pairList});
 *   - сопоставления направлений с ответами API;
 *   - логирования и диагностики ошибок парсинга.
 *
 * Почему фабрика, а не прямой new PairKey():
 * - упрощает будущие изменения формата ключа;
 * - позволяет валидировать входные данные в одном месте;
 * - повышает читаемость и предсказуемость кода в сервисах.
 *
 * Класс не содержит бизнес-логики и не зависит от БД или конфигурации.
 * Он выполняет исключительно структурную задачу.
 */
final class PairKeyFactory
{
    /**
     * Создать ключ валютной пары для BestChange.
     *
     * Формат ключа:
     * - без города: "fromCurrencyId-toCurrencyId"
     * - с городом:  "fromCurrencyId-toCurrencyId-cityId"
     *
     * Примеры:
     * - fromDirection(42, 93)        → "42-93"
     * - fromDirection(93, 91, 1)     → "93-91-1"
     *
     * Инварианты:
     * - fromCurrencyId и toCurrencyId должны быть > 0
     * - cityId может быть 0 (означает безналичное направление)
     *
     * Валидация значений (например, > 0) выполняется на уровне сервисов,
     * которые используют фабрику (RatesUpdateService).
     *
     * @param int $fromCurrencyId ID валюты "отдаю"
     * @param int $toCurrencyId   ID валюты "получаю"
     * @param int $cityId         ID города (0 — без города)
     *
     * @return PairKey DTO-объект, инкапсулирующий ключ пары
     */
    public function fromDirection(int $fromCurrencyId, int $toCurrencyId, int $cityId = 0): PairKey
    {
        return new PairKey($fromCurrencyId, $toCurrencyId, $cityId);
    }
}
