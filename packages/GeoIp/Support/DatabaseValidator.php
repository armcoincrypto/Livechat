<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Support;

use GeoIp2\Database\Reader;
use iEXPackages\GeoIp\Exceptions\UpdateFailedException;

/**
 * Валидация mmdb через metadata().
 */
final class DatabaseValidator
{
    public function validate(string $path): void
    {
        try {
            $r = new Reader($path);
            $r->metadata();
        } catch (\Throwable $e) {
            throw new UpdateFailedException("Невалидная mmdb база: {$path}. ".$e->getMessage(), previous: $e);
        }
    }
}
