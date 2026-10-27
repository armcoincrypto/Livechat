<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Decide whether the heavy admin-frontend route file must be registered
 * for the current request. Customer client-api traffic must not pay the
 * admin route-registration tax on every hit.
 */
final class AdminRouteGate
{
    /**
     * @param  string  $path  URL path without query string (leading slash optional)
     * @param  string  $adminFolder  config iexexchanger.admin_folder (e.g. iexadmin)
     */
    public static function shouldLoadAdminFrontend(
        bool $runningInConsole,
        string $path,
        string $adminFolder,
    ): bool {
        if ($runningInConsole) {
            return true;
        }

        $folder = trim($adminFolder, "/ \t\n\r\0\x0B");
        if ($folder === '') {
            // Misconfiguration: keep admin routes available rather than lock operators out.
            return true;
        }

        $normalized = '/'.ltrim($path, '/');
        if ($normalized === '/') {
            return false;
        }

        $prefix = '/'.$folder;

        return $normalized === $prefix
            || str_starts_with($normalized, $prefix.'/');
    }
}
