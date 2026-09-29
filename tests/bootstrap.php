<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Prefer App\\ / iEXPackages\\ from this tree so phpunit still sees local classes
// when vendor/ is reused from another worktree (PSR-4 otherwise points there).
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

// Tests\\ namespace for this worktree (PHPUnit PHAR has no composer Tests map).
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Tests\\')) {
        return;
    }
    $path = dirname(__DIR__) . '/tests/' . str_replace('\\', '/', substr($class, 6)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
}, true, true);

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
