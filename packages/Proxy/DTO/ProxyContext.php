<?php
declare(strict_types=1);

namespace iEXPackages\Proxy\DTO;

/**
 * ProxyContext
 *
 * Контекст использования прокси.
 * Нужен для:
 *  - корректного логирования (откуда пришёл запрос)
 *  - одинакового API для всех модулей (BestChange/Parsers/Gateways/…)
 *  - будущего расширения (пулы/ротация/правила выбора)
 *
 * Сейчас (по твоей задаче) контекст влияет на alias в логах.
 */
final class ProxyContext
{
    /**
     * @param string      $sourceType Тип источника: bestchange|parser_group|gateway|custom
     * @param string|null $sourceKey  Идентификатор внутри типа: alias группы/alias мерчанта и т.п.
     * @param string      $purpose    Назначение: api|parser|payout|any (для семантики, на будущее)
     */
    public function __construct(
        public readonly string $sourceType,
        public readonly ?string $sourceKey = null,
        public readonly string $purpose = 'any',
    ) {}

    /**
     * BestChange использует один global proxy_id из DynamicConfig.
     */
    public static function bestChange(): self
    {
        return new self('bestchange', null, 'api');
    }

    /**
     * Группа парсеров (50+ источников) — по alias группы.
     */
    public static function parserGroup(string $alias): self
    {
        return new self('parser_group', $alias, 'parser');
    }

    /**
     * Платёжный шлюз/мерчант — по alias.
     */
    public static function gateway(string $alias): self
    {
        return new self('gateway', $alias, 'payout');
    }

    /**
     * Универсальный контекст под любые будущие модули.
     */
    public static function custom(string $type, ?string $key = null, string $purpose = 'any'): self
    {
        return new self($type, $key, $purpose);
    }

    /**
     * Человекочитаемый алиас для логов: "bestchange" или "parser_group:binance".
     */
    public function alias(): string
    {
        if ($this->sourceKey === null || $this->sourceKey === '') {
            return $this->sourceType;
        }

        return $this->sourceType . ':' . $this->sourceKey;
    }
}
