<?php
declare(strict_types=1);

namespace iEXPackages\Proxy\Services;

use iEXPackages\Proxy\Models\Proxy;
use InvalidArgumentException;

/**
 * ProxyUrlBuilder
 *
 * Строит proxy URL в формате, который понимает Laravel Http:
 *  - http://user:pass@host:port
 *  - socks5://host:port
 *
 * Важно:
 * - Builder НЕ решает, можно ли использовать прокси (ProxyRepository::isUsable()).
 * - Builder НЕ логирует.
 * - Builder возвращает ТОЛЬКО валидный URL или кидает exception.
 */
final class ProxyUrlBuilder
{
    private const ALLOWED_TYPES = ['http', 'https', 'socks4', 'socks5'];

    public function build(Proxy $proxy): string
    {
        $type = strtolower(trim((string) $proxy->type));
        if ($type === '' || !in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('Invalid proxy type');
        }

        $host = trim((string) $proxy->host);
        if ($host === '') {
            throw new InvalidArgumentException('Proxy host is empty');
        }

        $port = (int) $proxy->port;
        if ($port <= 0) {
            throw new InvalidArgumentException('Invalid proxy port');
        }

        // IPv6 support
        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            $host = '[' . $host . ']';
        }

        $login = trim((string) $proxy->login);
        $password = trim((string) $proxy->password);

        $auth = '';
        if ($login !== '') {
            $auth = rawurlencode($login);

            if ($password !== '') {
                $auth .= ':' . rawurlencode($password);
            }

            $auth .= '@';
        }

        return "{$type}://{$auth}{$host}:{$port}";
    }
}
