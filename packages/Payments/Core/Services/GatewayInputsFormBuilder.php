<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Services;

use App\Models\GatewayMerchant;
use App\Models\GatewayPayment;
use iEXPackages\Payments\Core\Config\GatewayConfig;
use iEXPackages\Payments\Core\Contracts\GatewayInterface;
use iEXPackages\Payments\Core\Security\Contracts\SecretAccessManagerInterface;
use iEXPackages\Payments\Payments;
use Illuminate\Support\Facades\File;

final class GatewayInputsFormBuilder
{
    public function __construct(
        private readonly GatewayOptionsResolver $optionsResolver,
        private readonly SecretAccessManagerInterface $secrets,
    ) {}

    /**
     * Собирает форму мерчанта (incoming).
     */
    public function buildMerchantForm(GatewayMerchant $merchant): array
    {
        $cfg    = Payments::forConfig($merchant->alias);
        $gateway = Payments::forMerchant($merchant);

        return $this->buildForm(
            cfg: $cfg,
            gateway: $gateway,
            group: 'merchant',
            extOptions: (array) ($merchant->ext_options ?? [])
        );
    }

    /**
     * Собирает форму выплат (outgoing).
     */
    public function buildPayForm(GatewayPayment $payment): array
    {
        $cfg     = Payments::forConfig($payment->alias);
        $gateway = Payments::forPayment($payment);

        return $this->buildForm(
            cfg: $cfg,
            gateway: $gateway,
            group: 'pay',
            extOptions: (array) ($payment->ext_options ?? [])
        );
    }

    // ---------------------------------------------------------------------

    private function buildForm(
        GatewayConfig $cfg,
        GatewayInterface $gateway,
        string $group,
        array $extOptions
    ): array {
        $isConnectionReady = $this->isConnectionReady($cfg, $gateway, $group);

        $connectionFields = $this->buildConnectionFields(
            cfg: $cfg,
            gateway: $gateway,
            group: $group
        );

        $optionsFields = $this->buildOptionsFields(
            cfg: $cfg,
            gateway: $gateway,
            group: $group,
            extOptions: $extOptions
        );

        return [
            'isConnectionReady' => $isConnectionReady,
            'connectionFields'  => $connectionFields,
            'optionsFields'     => $optionsFields,
        ];
    }

    /**
     * Соединение готово, если все inputs.{group}.fields заполнены в runtime-конфиге (Vault).
     * (Resolver options тоже проверяет это сам, но флаг полезен для UI.)
     */
    private function isConnectionReady(GatewayConfig $cfg, GatewayInterface $gateway, string $group): bool
    {
        $keys = collect($cfg->fields($group))
            ->pluck('key')
            ->filter(fn ($k) => is_string($k) && $k !== '')
            ->values()
            ->all();

        if ($keys === []) {
            return true;
        }

        if (!method_exists($gateway, 'hasConfig') || !method_exists($gateway, 'configString')) {
            return false;
        }

        foreach ($keys as $k) {
            if (!$gateway->hasConfig($k)) {
                return false;
            }

            if (trim((string) $gateway->configString($k, '')) === '') {
                return false;
            }
        }

        return true;
    }

    private function buildConnectionFields(GatewayConfig $cfg, GatewayInterface $gateway, string $group): array
    {
        $scope = $this->secretScopeForGroup($group);

        return collect($cfg->fields($group))
            ->map(function (array $field) use ($gateway, $scope) {

                $key   = (string) ($field['key'] ?? '');
                if ($key === '') {
                    return null;
                }

                $type  = (string) ($field['type'] ?? 'input');
                $label = (string) ($field['label'] ?? $key);

                $value = (string) $gateway->configString($key, '');

                $placeholder = '';
                $isHidden = (bool) ($field['is_hidden'] ?? false);

                if ($isHidden && !$this->secrets->canView($scope)) {
                    $placeholder = $this->secrets->placeholder($value);
                    $value = '';
                }

                $options = $this->normalizeOptionsList($field['options'] ?? null);

                return [
                    'key'         => $key,
                    'type'        => $type,
                    'label'       => $label,
                    'placeholder' => $placeholder,
                    'value'       => $value,
                    'options'     => $options, // [{id,value}]
                    'purpose'     => (string) ($field['purpose'] ?? ''),
                    'warning'     => (string) ($field['warning'] ?? ''),
                    'is_hidden'   => $isHidden,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function buildOptionsFields(
        GatewayConfig $cfg,
        GatewayInterface $gateway,
        string $group,
        array $extOptions
    ): array {
        return collect($cfg->optionFields($group))
            ->map(function (array $field) use ($cfg, $gateway, $group, $extOptions) {

                $key = (string) ($field['key'] ?? '');
                if ($key === '') {
                    return null;
                }

                $type  = (string) ($field['type'] ?? 'input');
                $label = (string) ($field['label'] ?? $key);

                $optionsValue = [];



                if ($type === 'select') {
                    $optionsValue = $this->resolveSelectOptions(
                        field: $field,
                        cfg: $cfg,
                        gateway: $gateway,
                        group: $group
                    );
                }

                return [
                    'key'           => $key,
                    'type'          => $type,
                    'label'         => $label,
                    'default_value' => (string) ($field['default'] ?? $field['default_value'] ?? ''),
                    'value'         => (string) ($extOptions[$key] ?? ''),
                    'options'       => $this->normalizeOptionsList($optionsValue), // [{id,value}]
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * unified resolver:
     * 1) options_api_method -> GatewayOptionsResolver (cache+deps)
     * 2) options_file -> json
     * 3) options -> static
     */
    private function resolveSelectOptions(
        array $field,
        GatewayConfig $cfg,
        GatewayInterface $gateway,
        string $group
    ): array {
        // 1) options_api_method (кеш + dependency tracking)
        $method = trim((string) ($field['options_api_method'] ?? ''));

        if ($method !== '') {
            $options = $this->optionsResolver->resolve(
                gateway: $gateway,
                group: $group,
                method: $method,
                params: []
            );

            if ($options !== []) {
                return $options; // id => value
            }
        }

        // 2) options_file (fallback)
        $file = trim((string) ($field['options_file'] ?? ''));
        if ($file !== '') {
            $fromFile = $this->readOptionsFile($file);
            if ($fromFile !== []) {
                return $fromFile;
            }
        }

        // 3) options static (fallback)
        if (!empty($field['options']) && is_array($field['options'])) {
            return $field['options'];
        }

        return [];
    }

    private function secretScopeForGroup(string $group): string
    {
        // scope names: merchant|pay
        return $group === 'pay' ? 'pay' : 'merchant';
    }

    /**
     * options может быть:
     * - null
     * - assoc array id => value
     * - list array [{id,value}...] (редко)
     *
     * Возвращаем всегда list: [{id,value}]
     */
    private function normalizeOptionsList(mixed $options): array
    {

        if ($options === null) {
            return [];
        }

        if (!is_array($options) || $options === []) {
            return [];
        }

        // если это list:
        // A) [{id,value}, ...]
        // B) [0 => 'Нет', 1 => 'Да'] (скалярные значения)
        if (array_is_list($options)) {
            $out = [];

            foreach ($options as $idx => $row) {
                // формат B: скалярные значения
                if (!is_array($row)) {
                    $out[] = [
                        'id' => (string) $idx,
                        'value' => (string) $row,
                    ];
                    continue;
                }

                // формат A: {id,value}
                $id = $row['id'] ?? null;
                $val = $row['value'] ?? null;

                if ($id === null) {
                    continue;
                }

                $out[] = [
                    'id' => (string) $id,
                    'value' => (string) ($val ?? $id),
                ];
            }

            return $out;
        }



        // assoc id => value
        return collect($options)
            ->map(fn ($value, $id) => [
                'id' => (string) $id,
                'value' => (string) $value,
            ])
            ->values()
            ->all();
    }

    /**
     * Поддерживаем 2 формата JSON:
     * A) [{id, full_name/name/title}, ...]
     * B) {id: value, ...}
     *
     * Возвращаем assoc: id => value
     */
    private function readOptionsFile(string $filename): array
    {
        $filePath = rtrim((string) config('vault.gateway_options'), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $filename;

        if (!File::exists($filePath)) {
            return [];
        }

        $raw = json_decode((string) File::get($filePath), true);

        if (!is_array($raw) || $raw === []) {
            return [];
        }

        // list format
        if (array_is_list($raw)) {
            return collect($raw)
                ->filter(fn ($r) => is_array($r) && isset($r['id']))
                ->mapWithKeys(fn ($r) => [
                    (string) $r['id'] => (string) (
                        $r['full_name']
                        ?? $r['name']
                        ?? $r['title']
                        ?? $r['id']
                    ),
                ])
                ->all();
        }

        // assoc format
        return collect($raw)
            ->map(fn ($v) => (string) $v)
            ->all();
    }
}
