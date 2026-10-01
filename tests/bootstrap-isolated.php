<?php

declare(strict_types=1);

/**
 * Isolated PHPUnit bootstrap: no Laravel app, no production DB.
 * Sufficient for CanonicalDirectionRateCalculator tests that pass base overrides.
 */
require __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
}, true, true);

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'iEXPackages\\')) {
        return;
    }
    $path = dirname(__DIR__) . '/packages/' . str_replace('\\', '/', substr($class, strlen('iEXPackages\\'))) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
}, true, true);

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Tests\\')) {
        return;
    }
    $path = dirname(__DIR__) . '/tests/' . str_replace('\\', '/', substr($class, 6)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
}, true, true);
