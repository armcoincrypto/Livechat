<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class AllowedIdAddressesMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$this->isFirewallEnabled()) {
            return $next($request);
        }

        $allowedIps = $this->getAllowedIps();

        if ($this->isAllowedIpsEmpty($allowedIps) || $this->ipIsAllowed($request->ip(), $allowedIps)) {
            return $next($request);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Your IP address is not allowed.',
        ], 403);
    }

    /**
     * Проверяет, включен ли firewall.
     */
    protected function isFirewallEnabled(): bool
    {
        return (int) iEXSetting('is_firewall_enabled') === 1;
    }

    /**
     * Получает список разрешённых IP-адресов из настроек.
     */
    protected function getAllowedIps(): array
    {
        $rawIps = iEXSetting('admin_allowed_ip');

        if (empty(trim($rawIps))) {
            return [];
        }

        $ips = preg_split('/[\s,]+/', trim($rawIps));

        return array_filter($ips, fn($ip) => $this->validateIpOrSubnet($ip));
    }

    /**
     * Проверяет, является ли строка валидным IP или подсетью (IPv4 или IPv6).
     */
    protected function validateIpOrSubnet(string $ip): bool
    {
        $baseIp = explode('/', $ip)[0];

        return filter_var($baseIp, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Проверяет пустоту списка разрешенных IP-адресов.
     */
    protected function isAllowedIpsEmpty(array $allowedIps): bool
    {
        return empty($allowedIps);
    }

    /**
     * Проверяет, разрешён ли текущий IP-адрес.
     */
    protected function ipIsAllowed(string $ip, array $allowedIps): bool
    {
        return IpUtils::checkIp($ip, $allowedIps);
    }
}
