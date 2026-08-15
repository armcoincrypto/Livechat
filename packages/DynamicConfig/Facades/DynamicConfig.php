<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Facades;

use iEXPackages\DynamicConfig\DynamicConfigManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null, ?\iEXPackages\DynamicConfig\ValueObjects\Scope $scope = null, ?string $locale = null)
 * @method static array all(?\iEXPackages\DynamicConfig\ValueObjects\Scope $scope = null, bool $merged = true)
 * @method static void  set(string $key, mixed $value, ?\iEXPackages\DynamicConfig\ValueObjects\Scope $scope = null)
 * @method static void  update(array $data, ?\iEXPackages\DynamicConfig\ValueObjects\Scope $scope = null)
 * @method static void  delete(string|array $keys, ?\iEXPackages\DynamicConfig\ValueObjects\Scope $scope = null)
 * @method static void  clearScope(\iEXPackages\DynamicConfig\ValueObjects\Scope $scope)
 *
 * Typed API:
 * @method static bool  bool(string $key, bool $default = false, ?\iEXPackages\DynamicConfig\ValueObjects\Scope $scope = null)
 * @method static int   int(string $key, int $default = 0, ?\iEXPackages\DynamicConfig\ValueObjects\Scope $scope = null)
 * @method static float float(string $key, float $default = 0.0, ?\iEXPackages\DynamicConfig\ValueObjects\Scope $scope = null)
 * @method static string string(string $key, string $default = '', ?\iEXPackages\DynamicConfig\ValueObjects\Scope $scope = null, ?string $locale = null)
 * @method static array array(string $key, array $default = [], ?\iEXPackages\DynamicConfig\ValueObjects\Scope $scope = null)
 *
 * Feature flags:
 * @method static bool featureEnabled(string $featureKey, ?\iEXPackages\DynamicConfig\ValueObjects\Scope $scope = null, ?string $subjectId = null)
 *
 *
 * @method static \iEXPackages\DynamicConfig\ScopedDynamicConfig forScope(string $type, ?int $id = null)
 */
final class DynamicConfig extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DynamicConfigManager::class;
    }
}
