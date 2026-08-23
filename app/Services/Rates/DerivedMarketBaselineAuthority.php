<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\DirectionExchange;
use iEXPackages\Calculator\CalculatorFacade;
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
        } elseif (($cfg['asset_leg']['source'] ?? '') === 'direction_course') {
            // Live peer direction course (e.g. BestChange GRAM→USDTTRC20 as TON USD peg).
            $asset = $this->quoteDirectionCourse($cfg['asset_leg'], $cryptoMax);
            if ($asset === null && $assetSym !== '') {
                $asset = $this->baseline->quote($assetSym);
            }
        } else {
            $asset = $this->baseline->quote($assetSym);
        }
        // USD destinations (ADVCUSD/CASHUSD/…): fiat leg is unity via USDT_PEG.
        $fiatOri = (string) ($cfg['fiat_leg']['orientation'] ?? '');
        if (in_array($fiatSym, ['USDT_PEG', 'UNITY', 'USDT', 'USDUSD'], true)) {
            $fiat = [
                'rate' => '1',
                'source' => 'usd_peg',
                'as_of' => gmdate('Y-m-d H:i:s'),
                'age_seconds' => 0,
                'sample_size' => 1,
                'divergence' => null,
                'selection_reason' => 'usd_peg',
            ];
        } elseif (($cfg['fiat_leg']['source'] ?? '') === 'direction_course') {
            $fiat = $this->quoteDirectionCourse($cfg['fiat_leg'], $fiatMax);
            if ($fiat !== null && $fiatOri === 'inverse_usdt') {
                $fiat['rate'] = bcdiv('1', (string) $fiat['rate'], 18);
                $fiat['selection_reason'] = 'direction_course_inverse';
            } elseif ($fiat === null && $fiatOri === 'inverse_usdt' && $fiatSym !== '') {
                $q = $this->baseline->quote($fiatSym);
                if ($q !== null && isset($q['rate']) && (float) $q['rate'] > 0) {
                    $fiat = $q;
                    $fiat['rate'] = bcdiv('1', (string) $q['rate'], 18);
                    $fiat['selection_reason'] = 'inverse_usdt';
                }
            }
        } elseif ($fiatOri === 'inverse_usdt') {
            $q = $this->baseline->quote($fiatSym);
            if ($q !== null && isset($q['rate']) && (float) $q['rate'] > 0) {
                $fiat = $q;
                $fiat['rate'] = bcdiv('1', (string) $q['rate'], 18);
                $fiat['selection_reason'] = 'inverse_usdt';
            } else {
                $fiat = null;
            }
        } else {
            $fiat = $this->baseline->quote($fiatSym);
        }
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

        // ZELLEUSD outgoing is exclusively owned by ZelleUsdUsdtBenchmarkAuthority.
        // Derived may still exist as a READ upstream for other pairs, but must never
        // write course_value / parser_source_name on ZELLE rows.
        $zelleDeny = ZelleOutgoingRateWriteGuard::denyGenericWrite(
            $directionId,
            'DerivedMarketBaselineAuthority'
        );
        if ($zelleDeny['blocked']) {
            return [
                'ok' => true,
                'skipped' => $zelleDeny['reason'],
                'direction_id' => $directionId,
                'dry_run' => $dryRun,
                'eval' => $eval,
            ];
        }

        // Prefer healthy BestChange; stale/outlier must not suppress DERIVED fallback.
        $blockBc = !empty($cfg['ownership']['block_bestchange_overwrite']);
        if (!$blockBc && BestChangeMarketBaseHealth::isHealthy($directionId)) {
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

        // Keep add_course* neutralized. Do NOT zero profit — admin «Прибыль»
        // is the commercial % control for DERIVED ( Canonical applies as -profit ).
        $neutralize = [
            'add_course1' => 0,
            'add_course2' => 0,
            'your_add_course1' => 0,
            'your_add_course2' => 0,
        ];

        if (!empty($eval['ok']) && is_string($eval['rate'])) {
            // Rate ownership only. Do NOT force status=1 — admin enable/disable
            // must stick across */10 derived baseline refresh ticks.
            $action['write'] = array_merge([
                'course_value' => $eval['rate'],
                'manual_rate_value' => $eval['rate'],
                'parser_source_name' => (string) ($cfg['ownership']['parser_source_name'] ?? 'DERIVED_MARKET_BASELINE'),
                'is_error_rate' => 0,
                'error_rate_text' => null,
                // Preserve owner export policy: 0=unrestricted, 1=time-windowed, 2=blocked.
                // Never auto-clear quarantine (2→0); that desyncs XML from intentional blocks.
                'allow_export' => (int) ($row->allow_export ?? 0),
                'exchange_rate' => $this->formatExchangeRateLabel($directionId, $eval['rate']),
            ], $neutralize);
        } else {
            // Retain last positive course for site/admin; only hard-error when
            // there is nothing usable. Temporary fiat/crypto gaps must not flip
            // directions into "Курс не настроен" / catalog exclusion.
            // Always re-assert ownership label so legacy «Ручной курс» cannot stick
            // on derived-owned rows when a refresh retains the last valid BASE.
            $lastCourse = (string) ($row->course_value ?? '0');
            $lastManual = (string) ($row->manual_rate_value ?? '0');
            $retainedRate = (float) $lastCourse > 0 ? $lastCourse : $lastManual;
            $hasRetained = (float) $retainedRate > 0;
            $action['write'] = array_merge([
                'parser_source_name' => (string) ($cfg['ownership']['parser_source_name'] ?? 'DERIVED_MARKET_BASELINE'),
                'is_error_rate' => $hasRetained ? 0 : 1,
                'error_rate_text' => $hasRetained
                    ? ('derived_baseline_retained:' . ($eval['reason'] ?? 'unavailable'))
                    : ('derived_baseline_' . ($eval['reason'] ?? 'unavailable')),
            ], $neutralize);
            if ($hasRetained) {
                $action['write']['exchange_rate'] = $this->formatExchangeRateLabel(
                    $directionId,
                    (string) $retainedRate
                );
            }
        }

        $oldBase = (string) ($row->course_value ?? '');
        $newBase = isset($action['write']['course_value'])
            ? (string) $action['write']['course_value']
            : $oldBase;
        $from = (string) ($cfg['from'] ?? ($cfg['in'] ?? ''));
        $to = (string) ($cfg['to'] ?? ($cfg['out'] ?? ''));
        $action['pair'] = trim($from . '->' . $to, '->');
        $action['old_base'] = $oldBase;
        $action['new_base'] = $newBase;
        $action['legs'] = $eval['components'] ?? null;
        $deltaPct = null;
        if (is_numeric($oldBase) && (float) $oldBase > 0 && is_numeric($newBase)) {
            $deltaPct = ((float) $newBase - (float) $oldBase) / (float) $oldBase * 100.0;
        }
        $action['delta_pct'] = $deltaPct;

        // Protect last-good BASE from a broken leg (CBR/crypto spike or stale zero).
        $capPct = 25.0;
        if (
            $deltaPct !== null
            && abs($deltaPct) > $capPct
            && isset($action['write']['course_value'])
        ) {
            Log::warning('derived_baseline.delta_exceeds_safety_cap', [
                'direction_id' => $directionId,
                'pair' => $action['pair'],
                'old_base' => $oldBase,
                'new_base' => $newBase,
                'delta_pct' => $deltaPct,
                'cap_pct' => $capPct,
                'dry_run' => $dryRun,
            ]);
            unset($action['write']['course_value'], $action['write']['manual_rate_value']);
            $action['write']['error_rate_text'] = 'derived_baseline_delta_cap:' . round($deltaPct, 4);
            $action['skipped'] = 'delta_exceeds_safety_cap';
            $action['skipped_delta_cap'] = true;
            $action['new_base'] = $oldBase;
        }

        if (!$dryRun) {
            $oldBase = (string) ($action['old_base'] ?? $oldBase);
            $newBase = isset($action['write']['course_value'])
                ? (string) $action['write']['course_value']
                : $oldBase;

            DB::table('direction_exchange')->where('id', $directionId)->update(array_merge($action['write'], [
                'updated_at' => $now,
            ]));
            // When ownership blocks BC overwrite, disable active BC links so the
            // BestChange compiler cannot re-poison course_value on the next tick.
            if ($blockBc) {
                DB::table('bestchange_directions')
                    ->where('id_direction_exchange', $directionId)
                    ->where('status', 1)
                    ->update(['status' => 0, 'updated_at' => $now]);
            }
            if ($oldBase !== $newBase || ($action['write']['parser_source_name'] ?? null) !== null) {
                RateWriteAuditLogger::record([
                    'event' => 'base_change',
                    'direction_id' => $directionId,
                    'from' => $cfg['from'] ?? null,
                    'to' => $cfg['to'] ?? null,
                    'old_base' => $oldBase,
                    'new_base' => $newBase,
                    'writer' => 'DerivedMarketBaselineAuthority',
                    'source' => (string) ($cfg['ownership']['parser_source_name'] ?? 'DERIVED_MARKET_BASELINE'),
                    'reason' => $blockBc ? 'derived_refresh_block_bc' : 'derived_refresh',
                ]);
            }
            Log::info('derived_market_baseline_applied', $action);
        }

        return $action;
    }

    /**
     * Admin list «Курс» string = public commercial rate (BASE ± Прибыль),
     * matching BestChange XML — not raw BASE alone.
     */
    private function formatExchangeRateLabel(int $directionId, string $rate): string
    {
        if (!is_numeric($rate) || bccomp($rate, '0', 18) !== 1) {
            return $rate;
        }

        try {
            $dir = DirectionExchange::query()
                ->with([
                    'currency1:id,number_format,id_code_currency',
                    'currency1.code_currency:id,name',
                    'currency2:id,number_format,id_code_currency',
                    'currency2.code_currency:id,name',
                ])
                ->find($directionId);
            if ($dir === null) {
                return $rate;
            }

            $dir->course_value = $rate;
            $dir->manual_rate_value = $rate;
            $dir->parser_source_name = 'DERIVED_MARKET_BASELINE';

            $commercial = CanonicalDirectionRateCalculator::make()
                ->calculateForExport($dir, $rate);
            $displayRate = ($commercial->eligible && bccomp($commercial->finalRate, '0', 18) === 1)
                ? $commercial->finalRate
                : $rate;

            $dir->course_value = $displayRate;
            $dir->profit = 0; // already applied into displayRate
            $dir->profit_s = 0;

            return (string) CalculatorFacade::setDirectionExchange($dir)
                ->withoutOptions()
                ->calculate()
                ->getFullRate();
        } catch (\Throwable $e) {
            Log::warning('derived_exchange_rate_label_failed', [
                'direction_id' => $directionId,
                'message' => $e->getMessage(),
            ]);

            return $rate;
        }
    }

    /**
     * Read a positive live course from another public direction as a market leg.
     *
     * Used for optional peer-direction legs. GRAM/TON crypto crosses must NOT
     * peer our own BestChange GRAM→USDT self-rate (historically ~2× under-priced);
     * prefer IndependentMarketBaseline TONUSDT instead.
     *
     * @param  array<string,mixed>  $leg
     * @return array{rate:string,source:string,as_of:string,age_seconds:int,sample_size:int,divergence:null,selection_reason:string}|null
     */
    private function quoteDirectionCourse(array $leg, int $maxAgeSeconds): ?array
    {
        $peerId = (int) ($leg['direction_id'] ?? 0);
        if ($peerId <= 0) {
            return null;
        }

        // Peer courses (esp. BestChange) refresh slower than Binance ticks.
        $maxAgeSeconds = max($maxAgeSeconds, (int) ($leg['max_age_seconds'] ?? 21600));

        $peer = DB::table('direction_exchange')
            ->where('id', $peerId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->first(['id', 'course_value', 'updated_at', 'parser_source_name']);

        if ($peer === null) {
            return null;
        }

        $rate = (string) ($peer->course_value ?? '0');
        if (bccomp($rate, '0', 18) !== 1) {
            return null;
        }

        $updatedAt = (string) ($peer->updated_at ?? '');
        $age = 0;
        if ($updatedAt !== '') {
            try {
                $age = max(0, time() - strtotime($updatedAt));
            } catch (\Throwable) {
                $age = PHP_INT_MAX;
            }
        }
        if ($age > $maxAgeSeconds) {
            return null;
        }

        return [
            'rate' => $rate,
            'source' => 'direction_course:' . $peerId . ':' . (string) ($peer->parser_source_name ?? ''),
            'as_of' => $updatedAt !== '' ? $updatedAt : gmdate('Y-m-d H:i:s'),
            'age_seconds' => $age,
            'sample_size' => 1,
            'divergence' => null,
            'selection_reason' => 'direction_course',
        ];
    }

    /**
     * Refresh all configured owned directions.
     *
     * @return list<array<string,mixed>>
     */
    public function refreshAll(bool $dryRun = true): array
    {
        ProtectedMarketBaselineWriteGuard::clearCache();

        $out = [];
        foreach (array_keys($this->config()['directions'] ?? []) as $id) {
            $out[] = $this->apply((int) $id, $dryRun);
        }

        return $out;
    }
}
