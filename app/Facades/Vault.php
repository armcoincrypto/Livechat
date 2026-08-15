<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void encryptToFile(string $filename, array $data, ?string $subFolder = null)
 * @method static array decryptFromFile(string $filename, ?string $subFolder = null, bool $strictNonce = true)
 * @method static bool deleteFile(string $filename, ?string $subFolder = null)
 * @method static void updateFile(string $filename, array $newData, ?string $subFolder = null, array $preserveKeys = [])
 */
class Vault extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\VaultService::class;
    }
}
