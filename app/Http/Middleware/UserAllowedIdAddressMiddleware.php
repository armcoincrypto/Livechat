<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class UserAllowedIdAddressMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Нет пользователя или нет ограничений — пускаем
        if (!$user) {
            return $next($request);
        }

        $raw = (string) ($user->allowed_ip_addresses ?? '');
        $raw = trim($raw);

        if ($raw === '') {
            return $next($request);
        }

        $allowed = $this->parseAllowedIps($raw);

        // Если после парсинга ничего валидного не осталось — безопаснее НЕ блокировать
        if ($allowed === []) {
            return $next($request);
        }

        $clientIp = (string) $request->ip();

        if ($clientIp !== '' && $this->isIpAllowed($clientIp, $allowed)) {
            return $next($request);
        }

        return response()->json([
            'status' => 0,
            'message' => __('auth.ip_not_allowed'),
            'error_code' => 'IP_NOT_ALLOWED',
            'is_authenticated' => 1,
        ], 403);
    }

    /**
     * @return array<int, string> список IP/CIDR
     */
    private function parseAllowedIps(string $ips): array
    {
        // поддержка: пробелы, запятые, ;, переносы строк
        $parts = preg_split('/[\s,;]+/u', trim($ips)) ?: [];

        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            // убираем возможные кавычки/мусор
            $part = trim($part, " \t\n\r\0\x0B\"'");

            // валидируем базовый IP (IPv4/IPv6) даже если есть CIDR
            $baseIp = explode('/', $part, 2)[0];

            if (!filter_var($baseIp, FILTER_VALIDATE_IP)) {
                continue;
            }

            $out[] = $part;
        }

        // уникализируем, сохраняем порядок
        $out = array_values(array_unique($out));

        return $out;
    }

    /**
     * @param array<int, string> $allowed
     */
    private function isIpAllowed(string $ip, array $allowed): bool
    {
        // IpUtils умеет и одиночные IP, и CIDR, и диапазоны/маски в формате Symfony
        return IpUtils::checkIp($ip, $allowed);
    }
}
