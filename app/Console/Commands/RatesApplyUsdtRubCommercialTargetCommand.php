<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DirectionExchange;
use App\Services\Rates\CanonicalDirectionRateCalculator;
use App\Services\Rates\CommercialAdjustmentWriteGate;
use App\Services\Rates\DerivedMarketBaselineAuthority;
use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\RateChannel;
use App\Services\Rates\RateDirectionEligibility;
use App\Services\Rates\RateMode;
use App\Services\Rates\RubFamilyPremiumPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * USDT→classic-RUB commercial-target diagnostic / BASE ownership helper.
 *
 * Owner policy (2026-08-11):
 *   BASE     = automatic / DERIVED compiler-owned
 *   Прибыль  = owner/admin-owned (direction_exchange.profit for DERIVED)
 *
 * This command MUST NOT overwrite profit / floating_fee / fix_fee on normal --apply.
 * Historical ~95 RUB/USDT bootstrap that wrote profit is demoted behind an explicit
 * dual-gate legacy flag that cannot be triggered accidentally.
 */
final class RatesApplyUsdtRubCommercialTargetCommand extends Command
{
    protected $signature = 'rates:apply-usdt-rub-commercial-target
        {--target=95 : Reference customer floating RUB per 1 USDT (diagnostic / REVIEW expectation)}
        {--dry-run : plan / diagnostics only (default when --apply omitted)}
        {--apply : refresh DERIVED BASE ownership + eligibility report; never writes owner Прибыль}
        {--from=USDTTRC20,USDTERC20,USDTBEP20,USDTTON,USDTSOL : restrict source letter codes (comma)}
        {--to=SBERRUB,TBRUB,TCSBRUB,SBPRUB,RFBRUB,ACRUB : classic bank destinations}
        {--export=0 : ignored for profit; retained for eligibility reporting only}
        {--legacy-overwrite-owner-profit : DANGEROUS bootstrap only; requires EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP=1}
        {--skip-xml-sync : skip scheme:files after apply (tests / diagnostics)}';

    protected $description = 'USDT→RUB commercial-target diagnostics + DERIVED BASE refresh (Прибыль is owner-owned)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $dry = (bool) $this->option('dry-run') || !$apply;
        $legacyProfit = (bool) $this->option('legacy-overwrite-owner-profit');
        $target = (float) $this->option('target');
        if ($target < 50.0 || $target > 200.0) {
            $this->error('target_out_of_sane_bounds');

            return self::FAILURE;
        }

        if ($legacyProfit && !$this->legacyProfitBootstrapAllowed()) {
            $this->error('legacy_profit_bootstrap_refused: set EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP=1 and pass --legacy-overwrite-owner-profit (owner Прибыль is protected)');
            Log::warning('rub_commercial_target_legacy_profit_refused', [
                'reason' => 'owner_profit_protected',
                'env_set' => false,
            ]);

            return self::FAILURE;
        }

        if ($legacyProfit && $dry) {
            $this->error('legacy_profit_requires_apply');

            return self::FAILURE;
        }

        $fromCodes = array_values(array_filter(array_map(
            static fn (string $s) => strtoupper(trim($s)),
            explode(',', (string) $this->option('from')),
        )));
        $toCodes = array_values(array_filter(array_map(
            static fn (string $s) => strtoupper(trim($s)),
            explode(',', (string) $this->option('to')),
        )));

        $baseline = (new IndependentMarketBaseline())->quote('USDRUB');
        $cbr = isset($baseline['rate']) && is_numeric((string) $baseline['rate'])
            ? (string) $baseline['rate']
            : null;
        if ($cbr === null || bccomp($cbr, '0', 8) <= 0) {
            $this->error('cbr_baseline_unavailable');

            return self::FAILURE;
        }

        $rows = DB::select(
            'SELECT de.id, cb.designation_xml AS fr, cs.designation_xml AS tto,
                    de.status, de.course_value, de.profit, de.floating_fee, de.fix_fee, de.parser_source_name,
                    de.min_price1, de.max_price1, de.allow_export
             FROM direction_exchange de
             JOIN currencies cb ON cb.id = de.id_currency1
             JOIN currencies cs ON cs.id = de.id_currency2
             WHERE de.deleted_at IS NULL
               AND cb.designation_xml IN ('.implode(',', array_fill(0, count($fromCodes), '?')).')
               AND cs.designation_xml IN ('.implode(',', array_fill(0, count($toCodes), '?')).')
             ORDER BY cs.designation_xml, cb.designation_xml, de.id',
            array_merge($fromCodes, $toCodes),
        );

        $registryPath = base_path('resources/rates/derived-market-baseline-directions.json');
        $registry = json_decode((string) file_get_contents($registryPath), true);
        if (!is_array($registry) || !isset($registry['directions']) || !is_array($registry['directions'])) {
            $this->error('derived_registry_invalid');

            return self::FAILURE;
        }

        $template = [
            'public_aliases' => [],
            'asset_leg' => ['symbol' => 'USDT_PEG', 'orientation' => 'unity'],
            'fiat_leg' => ['symbol' => 'USDRUB', 'orientation' => 'fiat_per_usd'],
            'formula' => 'course = asset_leg * fiat_leg * haircut',
            'haircut' => '0.999',
            'haircut_note' => 'USDT→RUB DERIVED BASE ownership; owner Прибыль is NOT written by this command',
            'freshness' => [
                'crypto_max_age_seconds' => 900,
                'fiat_max_age_seconds' => 21600,
            ],
            'ownership' => [
                'parser_source_name' => 'DERIVED_MARKET_BASELINE',
                'block_bestchange_overwrite' => true,
                'keep_bestchange_link_status' => 0,
            ],
            'on_stale_or_missing' => 'mark_unavailable_retain_last_valid',
        ];

        $plan = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $haircutBase = bcmul($cbr, '0.999', 12);
            // Reference profit only (diagnostic): what would hit target if owner chose it.
            // MUST NOT be persisted on normal --apply.
            $referenceProfit = bcmul(bcsub('1', bcdiv((string) $target, $haircutBase, 12), 12), '100', 6);
            $plan[] = [
                'id' => $id,
                'from' => (string) $row->fr,
                'to' => (string) $row->tto,
                'before' => [
                    'status' => (int) $row->status,
                    'course' => (string) $row->course_value,
                    'profit' => (string) $row->profit,
                    'floating_fee' => (string) $row->floating_fee,
                    'fix_fee' => (string) ($row->fix_fee ?? '0'),
                    'parser' => (string) $row->parser_source_name,
                    'allow_export' => (int) $row->allow_export,
                ],
                'cbr' => $cbr,
                'derived_base_expected' => $haircutBase,
                'reference_profit_for_target' => $referenceProfit,
                'target' => (string) $target,
                'owner_profit_protected' => true,
            ];
            $registry['directions'][(string) $id] = array_merge($template, [
                'from' => (string) $row->fr,
                'to' => (string) $row->tto,
            ]);
        }

        $registry['count'] = count($registry['directions']);
        $registry['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
        $registry['update_note'] = 'USDT classic RUB DERIVED BASE; owner owns Прибыль; commercial-target does not write profit';

        $snapDir = storage_path('app/rates/usdt-rub-commercial-target');
        if (!is_dir($snapDir)) {
            File::makeDirectory($snapDir, 0755, true);
        }
        $stamp = gmdate('Ymd\THis\Z');
        $snapFile = $snapDir.'/plan-'.$stamp.'.json';
        @file_put_contents($snapFile, json_encode([
            'mode' => $dry ? 'dry-run' : ($legacyProfit ? 'legacy-profit-bootstrap' : 'apply-base-only'),
            'target' => $target,
            'cbr' => $cbr,
            'baseline_source' => $baseline['source'] ?? null,
            'owner_profit_protected' => !$legacyProfit,
            'plan' => $plan,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($dry) {
            $this->line(json_encode([
                'mode' => 'dry-run',
                'snapshot' => $snapFile,
                'count' => count($plan),
                'owner_profit_protected' => true,
                'note' => 'Прибыль is owner-controlled; --apply refreshes BASE only and never writes profit/floating_fee/fix_fee',
                'sample' => array_slice($plan, 0, 5),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        // Persist DERIVED registry so BASE refresh owns these rails (not BestChange).
        file_put_contents(
            $registryPath,
            json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
        );

        $auth = DerivedMarketBaselineAuthority::fromStorageApp();
        $refresh = $auth->refreshAll(dryRun: false);

        $calc = CanonicalDirectionRateCalculator::make();
        $elig = RateDirectionEligibility::make();
        $results = [];

        Log::info('rub_commercial_target_apply_owner_profit_protected', [
            'count' => count($plan),
            'legacy_profit' => $legacyProfit,
            'message' => 'Прибыль is owner-controlled; normal apply does not mutate profit/floating_fee/fix_fee',
        ]);
        $this->info('owner_profit_protected=1 (Прибыль will not be overwritten by normal --apply)');

        foreach ($plan as $item) {
            $id = (int) $item['id'];
            $dir = DirectionExchange::query()->find($id);
            if (!$dir) {
                $results[] = ['id' => $id, 'ok' => false, 'reason' => 'missing'];
                continue;
            }

            $profitBefore = (string) $dir->profit;
            $floatingBefore = (string) $dir->floating_fee;
            $fixBefore = (string) $dir->fix_fee;

            // Ownership label only — never commercial adjustment fields.
            $dir->parser_source_name = 'DERIVED_MARKET_BASELINE';
            $dir->is_error_rate = 0;
            $dir->error_rate_text = null;
            $dir->save();

            if ($legacyProfit) {
                CommercialAdjustmentWriteGate::run('legacy:RatesApplyUsdtRubCommercialTargetCommand', function () use ($dir, $item): void {
                    $dir->profit = $item['reference_profit_for_target'];
                    $dir->floating_fee = 0;
                    $dir->save();
                });
                Log::warning('rub_commercial_target_legacy_profit_written', [
                    'direction_id' => $id,
                    'profit' => (string) $dir->profit,
                    'event' => 'LEGACY_RUB_PROFIT_BOOTSTRAP',
                ]);
            }

            $dir = $dir->fresh(['currency1', 'currency2']);
            $floating = $calc->calculate($dir, RateMode::Floating, RateChannel::Website);
            $status = $elig->evaluateDirection($dir);

            $profitAfter = (string) $dir->profit;
            $floatingAfter = (string) $dir->floating_fee;
            $fixAfter = (string) $dir->fix_fee;

            $results[] = [
                'id' => $id,
                'from' => $item['from'],
                'to' => $item['to'],
                'ok' => (bool) ($status['eligible_for_order'] ?? false),
                'course' => (string) $dir->course_value,
                'profit_before' => $profitBefore,
                'profit_after' => $profitAfter,
                'profit_drift' => $legacyProfit ? 'legacy' : (bccomp($profitBefore, $profitAfter, 8) === 0 ? '0' : 'NONZERO'),
                'floating_fee_before' => $floatingBefore,
                'floating_fee_after' => $floatingAfter,
                'fix_fee_before' => $fixBefore,
                'fix_fee_after' => $fixAfter,
                'reference_profit_for_target' => $item['reference_profit_for_target'],
                'customer_floating' => $floating->finalRate,
                'quote_allowed' => (bool) ($status['eligible_for_quote'] ?? false),
                'order_allowed' => (bool) ($status['eligible_for_order'] ?? false),
                'export_allowed' => (bool) ($status['eligible_for_export'] ?? false),
                'allow_export' => (int) $dir->allow_export,
                'classification' => $status['classification'] ?? null,
                'reasons' => $status['reasons'] ?? [],
                'owner_profit_protected' => !$legacyProfit,
            ];
        }

        $this->line(json_encode([
            'mode' => $legacyProfit ? 'legacy-profit-bootstrap' : 'apply-base-only',
            'snapshot' => $snapFile,
            'cbr' => $cbr,
            'target' => $target,
            'refresh_owned' => count($refresh),
            'owner_profit_protected' => !$legacyProfit,
            'results' => $results,
            'policy' => RubFamilyPremiumPolicy::fromStorageApp()->summary(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $failed = count(array_filter($results, static fn ($r) => empty($r['ok'])));
        if ($failed > 0) {
            $this->warn("eligibility_failures={$failed}");
        }

        $drift = count(array_filter(
            $results,
            static fn ($r) => !$legacyProfit && ($r['profit_drift'] ?? '0') !== '0'
        ));
        if ($drift > 0) {
            $this->error("owner_profit_drift_detected={$drift}");

            return self::FAILURE;
        }

        try {
            if (!(bool) $this->option('skip-xml-sync')) {
                $this->call('scheme:files');
                $this->info('post_apply_xml_sync=scheme:files');
            } else {
                $this->info('post_apply_xml_sync=skipped');
            }
        } catch (\Throwable $e) {
            $this->warn('post_apply_xml_sync_failed='.$e->getMessage());
        }

        return self::SUCCESS;
    }

    private function legacyProfitBootstrapAllowed(): bool
    {
        return (string) env('EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP', '') === '1';
    }
}
