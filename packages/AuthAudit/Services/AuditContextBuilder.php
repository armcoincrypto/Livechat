<?php
declare(strict_types=1);

namespace iEXPackages\AuthAudit\Services;

use Illuminate\Http\Request;

final class AuditContextBuilder
{
    /**
     * Собирает единый контекст запроса для audit.meta.
     *
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public function build(Request $r, array $deviceInfo, array $extra = []): array
    {
        $base = [
            'request' => [
                'path' => $r->path(),
                'method' => $r->method(),
                'route' => optional($r->route())->getName(),
                'full_url' => $r->fullUrl(),
                'referer' => $r->header('referer'),
                'request_id' => $r->header('x-request-id'),
                'accept_language' => $r->header('accept-language'),
                'locale' => app()->getLocale(),
            ],
            'device' => [
                'browser' => $deviceInfo['browser'] ?? null,
                'browser_ver' => $deviceInfo['browser_ver'] ?? null,
                'os' => $deviceInfo['os'] ?? null,
                'os_ver' => $deviceInfo['os_ver'] ?? null,
                'device_type' => $deviceInfo['device_type'] ?? null,
            ],
        ];

        return array_replace_recursive($base, $extra);
    }
}
