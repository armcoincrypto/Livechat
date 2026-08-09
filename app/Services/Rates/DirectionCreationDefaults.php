<?php

declare(strict_types=1);

namespace App\Services\Rates;

use Illuminate\Support\Facades\DB;

/**
 * Defaults for newly created exchange directions.
 *
 * - Прибыль default 1.5%
 * - Give-side min/max from owner-tuned per-currency table (e.g. GRAM/TON 400–500)
 */
final class DirectionCreationDefaults
{
    public function __construct(
        private readonly ?string $configPath = null,
    ) {
    }

    public static function fromStorageApp(): self
    {
        $path = function_exists('base_path')
            ? base_path('resources/rates/direction-creation-defaults.json')
            : '/var/www/app_exswapin_usr/data/www/app.exswaping.com/resources/rates/direction-creation-defaults.json';

        return new self(configPath: $path);
    }

    /**
     * @return array<string,mixed>
     */
    public function config(): array
    {
        $path = $this->configPath ?? base_path('resources/rates/direction-creation-defaults.json');
        if (!is_file($path)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? $json : [];
    }

    public function defaultProfitPercent(): string
    {
        $p = (string) ($this->config()['default_profit_percent'] ?? '1.5');

        return is_numeric($p) ? $p : '1.5';
    }

    /**
     * @return array{min:string,max:string}
     */
    public function giveLimitsForXml(string $designationXml): array
    {
        $xml = strtoupper(trim($designationXml));
        $map = $this->config()['give_limits_by_xml'] ?? [];
        if (is_array($map) && isset($map[$xml]) && is_array($map[$xml])) {
            $min = (string) ($map[$xml]['min'] ?? '1');
            $max = (string) ($map[$xml]['max'] ?? '100');
            if (is_numeric($min) && is_numeric($max) && (float) $max >= (float) $min && (float) $min > 0) {
                return ['min' => $min, 'max' => $max];
            }
        }

        $fb = $this->config()['fallback_give_limits'] ?? [];

        return [
            'min' => (string) ($fb['min'] ?? '1'),
            'max' => (string) ($fb['max'] ?? '100'),
        ];
    }

    /**
     * @return array{min:string,max:string}|null
     */
    public function giveLimitsForCurrencyId(int $currencyId): ?array
    {
        if ($currencyId <= 0) {
            return null;
        }
        $xml = DB::table('currencies')->where('id', $currencyId)->value('designation_xml');
        if (!is_string($xml) || $xml === '') {
            return null;
        }

        return $this->giveLimitsForXml($xml);
    }

    /**
     * Fields to apply on create / catalog automation.
     *
     * @return array<string,mixed>
     */
    public function attributesForNewDirection(int $currency1Id): array
    {
        $limits = $this->giveLimitsForCurrencyId($currency1Id) ?? ['min' => '1', 'max' => '100'];

        return [
            'profit' => $this->defaultProfitPercent(),
            'profit_s' => 0,
            'floating_fee' => '0',
            'is_type_rate' => 1,
            'min_price1' => $limits['min'],
            'max_price1' => $limits['max'],
            'is_manual_min_price1' => 1,
            'is_manual_max_price1' => 1,
        ];
    }
}
