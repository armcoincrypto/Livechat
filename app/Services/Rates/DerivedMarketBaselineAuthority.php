<?php

declare(strict_types=1);

namespace App\Services\Rates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Durable derived-market ownership for directions that BestChange cannot price
 * (e.g. GRAM→CARDKZT / public TON→CARDKZT).
 *
 * Authority: DERIVED_MARKET_BASELINE = asset_leg × fiat_leg × haircut.
 */
final class DerivedMarketBaselineAuthority
{
    public function __construct(
        private readonly IndependentMarketBaseline $baseline = new IndependentMarketBaseline(),
        private readonly ?string $configPath = null,
    ) {
    }

    public static function fromStorageApp(): self
    {
        $path = function_exists('base_path')
            ? base_path('resources/rates/derived-market-baseline-directions.json')
            : '/var/www/app_exswapin_usr/data/www/app.exswaping.com/resources/rates/derived-market-baseline-directions.json';

        return new self(configPath: $path);
    }

    /**
     * @return array<string,mixed>
     */
    public function config(): array
    {
        $path = $this->configPath ?? base_path('resources/rates/derived-market-baseline-directions.json');
        if (!is_file($path)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? $json : [];
    }

    public function owns(int $directionId): bool
    {
        $dirs = $this->config()['directions'] ?? [];

        return isset($dirs[(string) $directionId]) || isset($dirs[$directionId]);
    }

    /**
     * @return array{
     *   ok:bool,
     *   rate:?string,
     *   reason:?string,
     *   components:array<string,mixed>,
     *   authority:string
     * }
     */
    public function evaluate(int $directionId): array
    {
        $cfg = $this->config()['directions'][(string) $directionId]
            ?? $this->config()['directions'][$directionId]
            ?? null;
        if (!is_array($cfg)) {
            return [
                'ok' => false,
                'rate' => null,
                'reason' => 'not_owned',
                'components' => [],
                'authority' => 'DERIVED_MARKET_BASELINE',
            ];
        }

        $assetSym = (string) ($cfg['asset_leg']['symbol'] ?? '');
        $fiatSym = (string) ($cfg['fiat_leg']['symbol'] ?? '');
        $haircut = (string) ($cfg['haircut'] ?? '1');
        $cryptoMax = (int) ($cfg['freshness']['crypto_max_age_seconds'] ?? 900);
        $fiatMax = (int) ($cfg['freshness']['fiat_max_age_seconds'] ?? 21600);

        // USDT-pegged senders (USDT*/USDC/DAI/ZELLE/REV*): asset leg is unity.
        if (in_array($assetSym, ['USDT_PEG', 'UNITY', 'USDT'], true)) {
            $asset = [
                'rate' => '1',
                'source' => 'usdt_peg',
                'as_of' => gmdate('Y-m-d H:i:s'),
                'age_seconds' => 0,
                'sample_size' => 1,
                'divergence' => null,
                'selection_reason' => 'usdt_peg',
            ];
        } else {
            $asset = $this->baseline->quote($assetSym);
        }
        $fiat = $this->baseline->quote($fiatSym);
        $components = [
            'asset_leg' => $asset,
            'fiat_leg' => $fiat,
            'haircut' => $haircut,
        ];

        if ($asset === null || !isset($asset['rate']) || (float) $asset['rate'] <= 0) {
            return [
                'ok' => false,
                'rate' => null,
                'reason' => 'asset_leg_missing_or_non_positive',
                'components' => $components,
                'authority' => 'DERIVED_MARKET_BASELINE',
            ];
        }
        if ($fiat === null || !isset($fiat['rate']) || (float) $fiat['rate'] <= 0) {
            return [
                'ok' => false,
                'rate' => null,
                'reason' => 'fiat_leg_missing_or_non_positive',
                'components' => $components,
                'authority' => 'DERIVED_MARKET_BASELINE',
            ];
        }
        if ((int) ($asset['age_seconds'] ?? PHP_INT_MAX) > $cryptoMax) {
            return [
                'ok' => false,
                'rate' => null,
                'reason' => 'asset_leg_stale',
                'components' => $components,
                'authority' => 'DERIVED_MARKET_BASELINE',
            ];
        }
        if ((int) ($fiat['age_seconds'] ?? PHP_INT_MAX) > $fiatMax) {
            return [
                'ok' => false,
                'rate' => null,
                'reason' => 'fiat_leg_stale',
                'components' => $components,
                'authority' => 'DERIVED_MARKET_BASELINE',
            ];
        }

        $rate = bcmul((string) $asset['rate'], (string) $fiat['rate'], 18);
        $rate = bcmul($rate, $haircut, 12);
        if ((float) $rate <= 0) {
            return [
                'ok' => false,
                'rate' => null,
                'reason' => 'derived_non_positive',
                'components' => $components,
                'authority' => 'DERIVED_MARKET_BASELINE',
            ];
        }

        return [
            'ok' => true,
            'rate' => $rate,
            'reason' => null,
            'components' => $components,
            'authority' => 'DERIVED_MARKET_BASELINE',
        ];
    }

    /**
     * Apply ownership to DB. Retains last valid course when legs fail.
     *
     * @return array<string,mixed>
     */
    public function apply(int $directionId, bool $dryRun = true): array
    {
        $eval = $this->evaluate($directionId);
        $cfg = $this->config()['directions'][(string) $directionId] ?? [];
        $now = now()->toDateTimeString();
        $row = DB::table('direction_exchange')->where('id', $directionId)->first();
        if (!$row) {
            return ['ok' => false, 'reason' => 'direction_missing', 'eval' => $eval];
        }

        // Prefer live BestChange when admin enabled it — do not overwrite course/parser.
        $bc = DB::table('bestchange_directions')
            ->where('id_direction_exchange', $directionId)
            ->where('status', 1)
            ->where('is_error_parser', 0)
            ->whereRaw('CAST(rate_value AS DECIMAL(36,18)) > 0')
            ->first();
        if ($bc !== null) {
            return [
                'ok' => true,
                'skipped' => 'bestchange_active',
                'direction_id' => $directionId,
                'dry_run' => $dryRun,
                'eval' => $eval,
            ];
        }

        $action = [
            'direction_id' => $directionId,
            'dry_run' => $dryRun,
            'eval' => $eval,
        ];

        if (!empty($eval['ok']) && is_string($eval['rate'])) {
            $action['write'] = [
                'course_value' => $eval['rate'],
                'manual_rate_value' => $eval['rate'],
                'parser_source_name' => (string) ($cfg['ownership']['parser_source_name'] ?? 'Ручной курс'),
                'is_error_rate' => 0,
                'error_rate_text' => null,
                'status' => 1,
                'allow_export' => (int) ($row->allow_export === 2 ? 0 : $row->allow_export),
            ];
        } else {
            // Retain last positive course for site/admin; only hard-error when
            // there is nothing usable. Temporary fiat/crypto gaps must not flip
            // directions into "Курс не настроен" / catalog exclusion.
            $lastCourse = (string) ($row->course_value ?? '0');
            $lastManual = (string) ($row->manual_rate_value ?? '0');
            $hasRetained = (float) $lastCourse > 0 || (float) $lastManual > 0;
            $action['write'] = [
                'is_error_rate' => $hasRetained ? 0 : 1,
                'error_rate_text' => $hasRetained
                    ? ('derived_baseline_retained:' . ($eval['reason'] ?? 'unavailable'))
                    : ('derived_baseline_' . ($eval['reason'] ?? 'unavailable')),
            ];
        }

        if (!$dryRun) {
            DB::table('direction_exchange')->where('id', $directionId)->update(array_merge($action['write'], [
                'updated_at' => $now,
            ]));
            // Do not force-disable BestChange links. Admin/BC compiler own link
            // status; derived authority only writes course when it successfully
            // evaluates. Disabling BC here made admin "save BestChange" impossible.
            if (!empty($cfg['ownership']['block_bestchange_overwrite'])) {
                // no-op retained for config compatibility
            }
            Log::info('derived_market_baseline_applied', $action);
        }

        return $action;
    }

    /**
     * Refresh all configured owned directions.
     *
     * @return list<array<string,mixed>>
     */
    public function refreshAll(bool $dryRun = true): array
    {
        $out = [];
        foreach (array_keys($this->config()['directions'] ?? []) as $id) {
            $out[] = $this->apply((int) $id, $dryRun);
        }

        return $out;
    }
}
