<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Services;

use iEXPackages\Payments\Core\Config\GatewayConfig;
use iEXPackages\Payments\Core\Contracts\GatewayInterface;
use RuntimeException;
use Throwable;

final class GatewayOptionsResolver
{
    public function __construct(
        private readonly GatewayOptionsCache $cache,
    ) {}

    /**
     * Получить options для select-поля (id => value) с кешем и dependency tracking.
     *
     * @param GatewayInterface $gateway  runtime gateway (Payments::forMerchant/forPayment)
     * @param string $group              merchant|pay
     * @param string $method             options_api_method (например getCurrencies)
     * @param array $params              дополнительные параметры для options (редко)
     * @param int|null $ttlSeconds       TTL (если нужно override)
     *
     * @return array<string,string> id => value
     */
    public function resolve(
        GatewayInterface $gateway,
        string $group,
        string $method,
        array $params = [],
        ?int $ttlSeconds = null
    ): array {
        $group  = trim($group);
        $method = trim($method);

        if ($group === '' || $method === '') {
            return [];
        }

        // 1) Проверка: вообще есть ли options operation
        $cfg = $gateway->gatewayConfig();
        $op = $cfg->operationConfig('options');

        if ($op === []) {
            // UI может иметь static options/options_file, но options_api_method работать не будет
            return [];
        }

        // 2) Проверка соединения: все connection keys должны быть заполнены
        if (!$this->isConnectionReady($gateway, $cfg, $group)) {
            return [];
        }

        // 3) Dependency hash: меняется автоматически при изменении Vault-конфига
        $alias = $cfg->alias() ?? 'gateway';
        $configHash = $this->makeConfigHash($gateway, $cfg, $group);

        // 4) Params hash: если options зависят от params
        $paramsHash = $this->makeParamsHash($params);

        $key = $this->cache->key($alias, $group, $method, $configHash, $paramsHash);

        // 5) Cache hit
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        // 6) Cache miss -> запрос к шлюзу через operation "options"
        try {
            $resp = $gateway->run('options', [
                'group'  => $group,
                'method' => $method,
                'params' => $params,
            ]);

            // Ожидаем, что OptionsResponse::getData() вернёт array<string,string>
            $data = method_exists($resp, 'getData') ? (array) $resp->getData() : [];

            // Нормализация: оставляем только string=>string
            $normalized = $this->normalizeOptions($data);

            // Сохраняем
            $this->cache->put($key, $normalized, $ttlSeconds);

            return $normalized;

        } catch (Throwable) {
            // В UX лучше вернуть пусто, чем падать формой настроек
            return [];
        }
    }

    /**
     * Проверка, заполнены ли connection keys для группы.
     *
     * Берём keys из inputs.{group}.fields.
     * Если хочешь учитывать required=true — добавим, но сейчас это безопасный вариант:
     * проверяем все ключи.
     */
    private function isConnectionReady(GatewayInterface $gateway, GatewayConfig $cfg, string $group): bool
    {
        $keys = $this->connectionKeys($cfg, $group);

        if ($keys === []) {
            // если нет connection keys — считаем соединение "готово"
            return true;
        }

        // gateway должен уметь hasConfig/configString
        if (!method_exists($gateway, 'hasConfig') || !method_exists($gateway, 'configString')) {
            return false;
        }

        foreach ($keys as $k) {
            $k = (string) $k;
            if ($k === '') {
                continue;
            }

            if (!$gateway->hasConfig($k)) {
                return false;
            }

            $v = (string) $gateway->configString($k, '');
            if (trim($v) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Зависимость кеша от runtime-конфига:
     * если меняется хоть один connection key — меняется hash → кеш автоматически "новый".
     */
    private function makeConfigHash(GatewayInterface $gateway, GatewayConfig $cfg, string $group): string
    {
        $keys = $this->connectionKeys($cfg, $group);

        $pairs = [];
        foreach ($keys as $k) {
            $k = (string) $k;
            if ($k === '') {
                continue;
            }

            // configString может отсутствовать на gateway — тогда пусто
            $pairs[$k] = method_exists($gateway, 'configString')
                ? (string) $gateway->configString($k, '')
                : '';
        }

        // сортируем для стабильности
        ksort($pairs);

        return sha1(json_encode($pairs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    /**
     * Хэш параметров options.
     */
    private function makeParamsHash(array $params): string
    {
        if ($params === []) {
            return 'noparams';
        }

        // stable sort
        $normalized = $this->stableSortRecursive($params);

        return sha1(json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    private function stableSortRecursive(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = $this->stableSortRecursive($v);
            }
        }

        // sort associative keys for deterministic hash
        if (!array_is_list($data)) {
            ksort($data);
        }

        return $data;
    }

    /**
     * Получить список ключей соединения из inputs.{group}.fields.
     *
     * @return string[]
     */
    private function connectionKeys(GatewayConfig $cfg, string $group): array
    {
        $fields = $cfg->fields($group);

        if (!is_array($fields) || $fields === []) {
            return [];
        }

        $keys = [];
        foreach ($fields as $f) {
            if (!is_array($f)) {
                continue;
            }

            $key = $f['key'] ?? null;
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys, SORT_NATURAL | SORT_FLAG_CASE);

        return $keys;
    }

    /**
     * Нормализует options в string=>string, отбрасывая мусор.
     *
     * @param array $data
     * @return array<string,string>
     */
    private function normalizeOptions(array $data): array
    {
        $out = [];

        foreach ($data as $id => $value) {
            if (!is_string($id) && !is_int($id)) {
                continue;
            }

            $id = (string) $id;

            if (is_array($value) || is_object($value)) {
                continue;
            }

            $out[$id] = (string) $value;
        }

        // стабильная сортировка (приятно для UI)
        ksort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }
}
