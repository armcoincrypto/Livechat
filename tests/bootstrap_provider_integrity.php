<?php
declare(strict_types=1);
$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    $map = [
        'App\\' => $root . '/app/',
        'Tests\\' => $root . '/tests/',
    ];
    foreach ($map as $prefix => $base) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $rel = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        $path = $base . $rel;
        if (is_file($path)) {
            require $path;
        }
    }
});
// Stub config() for integrity config reads
if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed {
        static $cfg;
        if ($cfg === null) {
            $cfg = include dirname(__DIR__) . '/config/rates_provider_integrity.php';
        }
        if ($key === null) {
            return $cfg;
        }
        if (str_starts_with($key, 'rates_provider_integrity.')) {
            $key = substr($key, strlen('rates_provider_integrity.'));
        } elseif ($key === 'rates_provider_integrity') {
            return $cfg;
        } else {
            return $default;
        }
        $cur = $cfg;
        foreach (explode('.', $key) as $part) {
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                return $default;
            }
            $cur = $cur[$part];
        }
        return $cur;
    }
}
