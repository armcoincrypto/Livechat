<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Config;

use Illuminate\Support\Arr;

final class GatewayConfig
{
    public function __construct(
        private readonly array $config,
    ) {}

    public static function fromArray(array $config): self
    {
        return new self($config);
    }

    // --- SCHEMA ---

    public function schema(): int
    {
        return (int) ($this->config['schema'] ?? 1);
    }

    // --- META ---

    public function name(): ?string
    {
        return Arr::get($this->config, 'meta.name');
    }

    public function alias(): ?string
    {
        return Arr::get($this->config, 'meta.alias');
    }

    public function version(): ?string
    {
        return Arr::get($this->config, 'meta.version');
    }

    public function description(): ?string
    {
        return Arr::get($this->config, 'meta.description');
    }

    public function category(): ?string
    {
        return Arr::get($this->config, 'meta.category');
    }

    // --- CAPABILITIES ---

    public function capabilities(): array
    {
        return Arr::get($this->config, 'capabilities', []);
    }

    public function autoOperationsEnabled(): bool
    {
        return (bool) ($this->config['capabilities']['features']['auto_operations'] ?? false);
    }

    public function supportsOperation(string $operation): bool
    {
        return (bool) Arr::get($this->config, 'operations.' . $operation, false);
    }

    public function supportsCallback(string $type): bool
    {
        return (bool) Arr::get($this->config, 'capabilities.callbacks.' . $type, false);
    }

    // GatewayConfig.php
    public function supportsPolling(): bool
    {
        return (bool)($this->config['capabilities']['features']['supports_polling'] ?? false);
    }

    public function supportsCheckout(): bool
    {
        return (bool) ($this->config['capabilities']['features']['supports_checkout'] ?? false);
    }

    public function supportsPollingIncoming(): bool
    {
        // если ты сделал polling.incoming/outgoing
        $v = $this->config['capabilities']['features']['polling']['incoming'] ?? null;
        if ($v !== null) return (bool)$v;

        // fallback на старый общий флаг
        return (bool)($this->config['capabilities']['features']['supports_polling'] ?? false);
    }

    // --- OPERATIONS ---

    public function operationConfig(string $operation): array
    {
        return Arr::get($this->config, 'operations.' . $operation, []);
    }

    public function requestClass(string $operation): ?string
    {
        return Arr::get($this->config, 'operations.' . $operation . '.request_class');
    }

    public function responseClass(string $operation): ?string
    {
        return Arr::get($this->config, 'operations.' . $operation . '.response_class');
    }

    /**
     * Все операции из config.php.
     */
    public function operations(): array
    {
        return Arr::get($this->config, 'operations', []);
    }

    /**
     * Тип операции: incoming|outgoing|service|null
     */
    public function operationType(string $operation): ?string
    {
        $type = Arr::get($this->config, 'operations.' . $operation . '.type');

        return is_string($type) && $type !== '' ? $type : null;
    }

    public function operationLabel(string $operation): ?string
    {
        $label = Arr::get($this->config, 'operations.' . $operation . '.label');
        return is_string($label) && $label !== '' ? $label : null;
    }

    // --- DEFAULTS ---

    public function defaults(): array
    {
        return Arr::get($this->config, 'defaults', []);
    }

    // --- INPUTS ---

    /**
     * Вернёт конфиг группы inputs (например, 'merchant' или 'pay').
     */
    public function inputsGroup(string $group): array
    {
        return Arr::get($this->config, 'inputs.' . $group, []);
    }

    /**
     * Просто fields для указанной группы (без options_fields).
     */
    public function fields(string $group): array
    {
        return Arr::get($this->config, 'inputs.' . $group . '.fields', []);
    }

    /**
     * options_fields для указанной группы.
     */
    public function optionFields(string $group): array
    {
        return Arr::get($this->config, 'inputs.' . $group . '.options_fields', []);
    }

    // --- CALLBACKS ---
    public function callbackConfig(): array
    {
        $merchant = $this->inputsGroup('merchant');
        $cb = $merchant['callback'] ?? [];

        return is_array($cb) ? $cb : [];
    }

    public function callbacksEnabled(): bool
    {
        return (bool) ($this->callbackConfig()['enabled'] ?? false);
    }

    public function callbackOrderIdField(): ?string
    {
        $v = $this->callbackConfig()['order_id_field'] ?? null;
        return is_string($v) && $v !== '' ? $v : null;
    }

    public function callbackRouteName(): ?string
    {
        $v = $this->callbackConfig()['route_name'] ?? null;
        return is_string($v) && $v !== '' ? $v : null;
    }

    public function callbackIpWhitelistEnabled(): bool
    {
        return (bool) ($this->callbackConfig()['ip_whitelist_enabled'] ?? false);
    }

    /**
     * Flow-конфиг операции (обычно purchase).
     *
     * @return array{mode?:string, store_external_id?:bool, supports_polling?:bool}
     */
    public function operationFlow(string $operation): array
    {
        $flow = Arr::get($this->config, "operations.{$operation}.flow", []);
        return is_array($flow) ? $flow : [];
    }

    /**
     * Режим выдачи результата после операции (purchase).
     * requisites|form|redirect
     */
    public function operationFlowMode(string $operation, string $default = 'requisites'): string
    {
        $mode = Arr::get($this->config, "operations.{$operation}.flow.mode", $default);
        $mode = is_string($mode) ? trim($mode) : $default;

        return in_array($mode, ['default', 'form', 'redirect'], true) ? $mode : $default;
    }

    /**
     * Нужно ли сохранять externalId после операции (purchase).
     */
    public function operationFlowStoreExternalId(string $operation, bool $default = true): bool
    {
        return (bool) Arr::get($this->config, "operations.{$operation}.flow.store_external_id", $default);
    }

    /**
     * Нужно ли включать polling после операции (purchase).
     * Это НЕ "шлюз умеет polling", а "мы хотим polling в этом flow".
     */
    public function operationFlowWantsPolling(string $operation, bool $default = false): bool
    {
        return (bool) Arr::get($this->config, "operations.{$operation}.flow.supports_polling", $default);
    }

    /**
     * Все ключи (key) из fields + options_fields для указанной группы.
     *
     * Пример:
     *   $this->inputKeys('merchant'); // ['api_key', 'wallet_unique_id', ...]
     */
    public function inputKeys(string $group): array
    {
        $fields = $this->fields($group);

        return collect([$fields])
            ->flatten(1)
            ->pluck('key')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function all(): array
    {
        return $this->config;
    }
}
