<?php
declare(strict_types=1);

namespace iEXPackages\Payments\Core\Services;
use iEXPackages\Payments\Core\Engine\GatewayManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class PaymentsConfigCatalog
{
    public function __construct(
        private readonly GatewayManager $gatewayManager,
    ) {}

    /**
     * Список шлюзов для приёма (merchant/incoming) в формате “как раньше”.
     *
     * @return array<string, array{
     *   name:string, version:string, alias:string, is_recommended:int, group:string, sorting:int,
     *   params: array{fields: array, options_fields: array}
     * }>
     */
    public function merchant(): array
    {
        $out = [];

        foreach ($this->loadAllGatewayConfigs() as $alias => $cfg) {
            if (!$this->isGatewayVisible($alias, $cfg)) {
                continue;
            }
            if (!$this->supportsIncoming($cfg)) {
                continue;
            }
            $out[$alias] = $this->legacyStyle($cfg, 'merchant');
        }

        return $this->sortGateways($out);
    }

    /**
     * Список шлюзов для выплат (pay/outgoing) в формате “как раньше”.
     *
     * @return array<string, array{
     *   name:string, version:string, alias:string, is_recommended:int, group:string, sorting:int,
     *   params: array{fields: array, options_fields: array}
     * }>
     */
    public function payout(): array
    {
        $out = [];

        foreach ($this->loadAllGatewayConfigs() as $alias => $cfg) {
            if (!$this->isGatewayVisible($alias, $cfg)) {
                continue;
            }
            if (!$this->supportsOutgoing($cfg)) {
                continue;
            }
            $out[$alias] = $this->legacyStyle($cfg, 'pay');
        }

        return $this->sortGateways($out);
    }

    /**
     * Полный каталог: alias => meta + merchant + pay.
     *
     * @return array<string, array{meta:array, merchant:array, pay:array}>
     */
    public function all(): array
    {
        $out = [];

        foreach ($this->loadAllGatewayConfigs() as $alias => $cfg) {
            if (!$this->isGatewayVisible($alias, $cfg)) {
                continue;
            }
            $out[$alias] = [
                'meta'     => $this->metaBlock($cfg),
                'merchant' => $this->groupBlock($cfg, 'merchant'),
                'pay'      => $this->groupBlock($cfg, 'pay'),
            ];
        }

        // если хочешь — сортировать и all() по тем же правилам:
        // но all() чаще используется как “сырые данные”
        ksort($out, SORT_NATURAL);

        return $out;
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * Загружает все конфиги доступных шлюзов: alias => config array.
     */
    private function loadAllGatewayConfigs(): array
    {
        $configs = [];

        foreach ($this->gatewayManager->all() as $alias => $gatewayClass) {
            if (!is_string($gatewayClass) || $gatewayClass === '' || !class_exists($gatewayClass)) {
                continue;
            }

            $gateway = new $gatewayClass([]);
            $cfg = $gateway->gatewayConfig()->all();

            if (is_array($cfg)) {
                $configs[mb_strtolower((string) $alias, 'UTF-8')] = $cfg;
            }
        }

        return $configs;
    }

    /**
     * Формат “как раньше”:
     * name/version/alias/is_recommended/group/sorting + params(fields/options_fields)
     */
    private function legacyStyle(array $cfg, string $group): array
    {
        $meta = $cfg['meta'] ?? [];
        $inputs = $cfg['inputs'][$group] ?? [];

        $params = [
            'fields'         => is_array($inputs['fields'] ?? null) ? $inputs['fields'] : [],
            'options_fields' => is_array($inputs['options_fields'] ?? null) ? $inputs['options_fields'] : [],
        ];

        if ($group === 'merchant') {
            $callback = $inputs['callback'] ?? [];

            // Нормализуем на всякий случай
            if (!is_array($callback)) {
                $callback = [];
            }

            $params['callback'] = [
                'enabled'             => (bool)($callback['enabled'] ?? false),
                'order_id_field'      => (string)($callback['order_id_field'] ?? ''),
                'route_name'          => (string)($callback['route_name'] ?? ''),
                'ip_whitelist_enabled'=> (bool)($callback['ip_whitelist_enabled'] ?? false),
            ];
        }

        return [
            'name'           => (string)($meta['name'] ?? ''),
            'version'        => (string)($meta['version'] ?? ''),
            'alias'          => (string)($meta['alias'] ?? ''),
            'is_recommended' => (int)(($meta['recommended'] ?? false) ? 1 : 0),
            'category'          => (string)($meta['category'] ?? ''),
            'sorting'        => (int)($meta['sorting'] ?? 0),

            'params' => $params,
        ];
    }

    /**
     * Сортировка:
     * 1) recommended DESC
     * 2) sorting ASC (sorting=0 → в конец группы)
     * 3) name ASC (natural, case-insensitive)
     */
    private function sortGateways(array $items): array
    {
        uasort($items, static function (array $a, array $b): int {
            $recA = (int)($a['is_recommended'] ?? 0);
            $recB = (int)($b['is_recommended'] ?? 0);

            if ($recA !== $recB) {
                return $recB <=> $recA; // DESC
            }

            $sortA = (int)($a['sorting'] ?? 0);
            $sortB = (int)($b['sorting'] ?? 0);

            $sortA = $sortA > 0 ? $sortA : 999999;
            $sortB = $sortB > 0 ? $sortB : 999999;

            if ($sortA !== $sortB) {
                return $sortA <=> $sortB; // ASC
            }

            $nameA = mb_strtolower((string)($a['name'] ?? ''), 'UTF-8');
            $nameB = mb_strtolower((string)($b['name'] ?? ''), 'UTF-8');

            return strnatcmp($nameA, $nameB);
        });

        return $items;
    }

    private function supportsIncoming(array $cfg): bool
    {
        return (bool)($cfg['capabilities']['incoming'] ?? false);
    }

    private function supportsOutgoing(array $cfg): bool
    {
        return (bool)($cfg['capabilities']['outgoing'] ?? false);
    }

    private function metaBlock(array $cfg): array
    {
        $meta = $cfg['meta'] ?? [];

        return [
            'name'        => (string)($meta['name'] ?? ''),
            'alias'       => (string)($meta['alias'] ?? ''),
            'version'     => (string)($meta['version'] ?? ''),
            'category'    => (string)($meta['category'] ?? ''),
            'description' => (string)($meta['description'] ?? ''),
            'group'       => (string)($meta['group'] ?? ''),
            'sorting'     => (int)($meta['sorting'] ?? 0),
            'recommended' => (bool)($meta['recommended'] ?? false),
        ];
    }

    private function groupBlock(array $cfg, string $group): array
    {
        $inputs = $cfg['inputs'][$group] ?? [];

        return [
            'fields'         => is_array($inputs['fields'] ?? null) ? $inputs['fields'] : [],
            'options_fields' => is_array($inputs['options_fields'] ?? null) ? $inputs['options_fields'] : [],
        ];
    }

    public function merchantByAlias(string $alias): ?array
    {
        $cfg = $this->loadAllGatewayConfigs()[$alias] ?? null;
        if (!is_array($cfg) || !$this->isGatewayVisible($alias, $cfg)) {
            return null;
        }
        if (!$this->supportsIncoming($cfg)) {
            return null;
        }
        return $this->legacyStyle($cfg, 'merchant');
    }

    public function payoutByAlias(string $alias): ?array
    {
        $cfg = $this->loadAllGatewayConfigs()[$alias] ?? null;
        if (!is_array($cfg) || !$this->isGatewayVisible($alias, $cfg)) {
            return null;
        }
        if (!$this->supportsOutgoing($cfg)) {
            return null;
        }
        return $this->legacyStyle($cfg, 'pay');
    }

    public function byAlias(string $alias, string $group): ?array
    {
        return match ($group) {
            'merchant' => $this->merchantByAlias($alias),
            'pay'      => $this->payoutByAlias($alias),
            default    => null,
        };
    }

    /**
     * Определяет, должен ли шлюз отображаться в каталоге.
     *
     * Логика:
     * 1) По умолчанию шлюз считается публичным.
     * 2) Если в config.php шлюза указано meta.visibility = 'private' (или meta.is_private = true),
     *    то шлюз показывается ТОЛЬКО если его alias присутствует в config('payments.allowed_gateway_aliases').
     * 3) Если allow-list пустой — публичные шлюзы показываются, приватные скрываются.
     *
     * Это позволяет держать часть шлюзов "индивидуальными" (скрытыми по умолчанию),
     * но включать их выборочно на уровне проекта.
     */
    private function isGatewayVisible(string $alias, array $cfg): bool
    {
        $alias = Str::lower(trim($alias));

        // Признак приватности шлюза (два варианта для совместимости)
        $visibility = (string) Arr::get($cfg, 'meta.visibility', 'public');
        $isPrivate  = (bool) Arr::get($cfg, 'meta.is_private', false);

        $private = $isPrivate || Str::lower($visibility) === 'private';

        // allow-list из config/payments.php
        $allowed = config('payments.allowed_gateway_aliases', []);
        $allowed = is_array($allowed) ? $allowed : [];
        $allowed = array_values(array_filter(array_map(static fn ($v) => Str::lower(trim((string) $v)), $allowed)));

        // Публичные шлюзы показываем всегда
        if (!$private) {
            return true;
        }

        // Приватные — только если явно разрешены
        return in_array($alias, $allowed, true);
    }
}
