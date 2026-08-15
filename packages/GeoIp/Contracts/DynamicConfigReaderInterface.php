<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Contracts;

/**
 * Читалка DynamicConfig.
 * Реализацию привяжи к своему модулю DynamicConfig.
 */
interface DynamicConfigReaderInterface
{
    /**
     * Возвращает значение по ключу или null, если ключа нет.
     */
    public function get(string $key): mixed;
}
