<?php
declare(strict_types=1);

namespace iEXPackages\Proxy\Services;

use iEXPackages\Proxy\Models\Proxy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * ProxyRepository
 *
 * Единая точка получения прокси из БД.
 *
 * Почему это важно:
 * - все сервисы перестают напрямую дергать Proxy::find()
 * - можно централизованно управлять кешем и правилами "валидности"
 */
final class ProxyRepository
{
    /**
     * Найти прокси по ID (с кешем).
     *
     * @param int $proxyId
     * @return Proxy|null
     */
    public function findById(int $proxyId): ?Proxy
    {
        if ($proxyId <= 0) {
            return null;
        }

        return Proxy::query()->find($proxyId);
    }

    /**
     * Проверка "можно ли вообще использовать прокси".
     * Здесь не трогаем сеть — только базовая валидация.
     */
    public function isUsable(?Proxy $proxy): bool
    {
        if (!$proxy) {
            return false;
        }

        // 1) Ручное отключение админом (manual disabled)
        if ($proxy->status !== true) {
            return false;
        }

        // 2) Авто-отключение по cooldown (auto disabled)
        // Если поле ещё не добавлено — просто пропусти этот блок
        if (
            $proxy->auto_disabled_until !== null
            && $proxy->auto_disabled_until instanceof Carbon
            && $proxy->auto_disabled_until->isFuture()
        ) {
            return false;
        }

        if (trim((string) $proxy->host) === '') {
            return false;
        }

        if ((int) $proxy->port <= 0) {
            return false;
        }

        $type = strtolower(trim((string) $proxy->type));

        return in_array($type, ['http', 'https', 'socks4', 'socks5'], true);
    }
}
