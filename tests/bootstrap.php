<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Enable final-class mocking for intentional final production settings/services
// (BestChangeConfig, BestChangeCatalogRepository, …). Does not alter production
// source; strips `final` only while PHPUnit loads class files via file stream.
require_once __DIR__ . '/Support/DG/BypassFinals.php';
require_once __DIR__ . '/Support/DG/BypassFinals/NativeWrapper.php';
require_once __DIR__ . '/Support/DG/BypassFinals/MutatingWrapper.php';
\DG\BypassFinals::enable();

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

// Isolate unit tests from live APP_ENV so telegram_operator.test_force_dm_ids works.
// Do NOT force array cache here — LanguageCatalog boots against the real store; tests
// must not Cache::flush() redis. Prefer unique fake task ids instead.
// Force a deterministic non-production APP_KEY so middleware/response helpers that
// resolve the encrypter never hit MissingAppKeyException under suite load.
foreach ([
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:dGVzdGtleXRlc3RrZXl0ZXN0a2V5dGVzdGtleXRlc3Q=',
    'APP_LOCALE' => 'ru',
    'APP_FALLBACK_LOCALE' => 'en',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Re-assert after Dotenv may have loaded production .env values.
$app['env'] = 'testing';
\Illuminate\Support\Facades\Config::set('app.env', 'testing');
\Illuminate\Support\Facades\Config::set('app.key', 'base64:dGVzdGtleXRlc3RrZXl0ZXN0a2V5dGVzdGtleXRlc3Q=');
\Illuminate\Support\Facades\Config::set('app.locale', 'ru');
\Illuminate\Support\Facades\Config::set('app.fallback_locale', 'en');
putenv('APP_KEY=base64:dGVzdGtleXRlc3RrZXl0ZXN0a2V5dGVzdGtleXRlc3Q=');
$_ENV['APP_KEY'] = 'base64:dGVzdGtleXRlc3RrZXl0ZXN0a2V5dGVzdGtleXRlc3Q=';
$_SERVER['APP_KEY'] = 'base64:dGVzdGtleXRlc3RrZXl0ZXN0a2V5dGVzdGtleXRlc3Q=';
// Reset Encrypter singleton if already resolved against an empty key.
if ($app->bound('encrypter')) {
    $app->forgetInstance('encrypter');
    $app->forgetInstance(\Illuminate\Contracts\Encryption\Encrypter::class);
}

// Snapshot real facade roots so Event::fake()/Notification::fake() in one test
// cannot poison later Unit tests in the same process.
$GLOBALS['__exs_phpunit_events'] = $app->bound('events') ? $app->make('events') : null;
$GLOBALS['__exs_phpunit_mailer'] = $app->bound('mail.manager') ? $app->make('mail.manager') : null;

