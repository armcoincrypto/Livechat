<?php

declare(strict_types=1);

/**
 * Composer autoload "files" entry. The original packages/Settings tree was missing on disk;
 * these helpers mirror the stack’s real settings API (DynamicConfig / iEXSetting).
 */
// Ensure the real iEXSetting helper is loaded early (before service providers boot).
// This keeps behavior consistent without touching runtime rate logic.
if (!function_exists('iEXSetting')) {
    $dynamicHelpers = __DIR__ . '/../DynamicConfig/helpers.php';
    if (is_file($dynamicHelpers)) {
        require_once $dynamicHelpers;
    }
}

if (!function_exists('settings')) {
    function settings(mixed $key = null, mixed $default = null, mixed ...$rest): mixed
    {
        if (!function_exists('iEXSetting')) {
            if ($key === null) {
                return [];
            }
            if (is_array($key)) {
                return false;
            }

            return $default;
        }

        return iEXSetting($key, $default, ...$rest);
    }
}

if (!function_exists('setting')) {
    function setting(mixed $key = null, mixed $default = null, mixed ...$rest): mixed
    {
        return settings($key, $default, ...$rest);
    }
}
