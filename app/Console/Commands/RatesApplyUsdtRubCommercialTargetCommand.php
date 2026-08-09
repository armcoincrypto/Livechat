<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DirectionExchange;
use App\Services\Rates\CanonicalDirectionRateCalculator;
use App\Services\Rates\DerivedMarketBaselineAuthority;
use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\RateChannel;
use App\Services\Rates\RateDirectionEligibility;
use App\Services\Rates\RateMode;
use App\Services\Rates\RubFamilyPremiumPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Owner-approved USDT→classic-RUB commercial target (~95 RUB / USDT).
 *
 * Moves selected BestChange-owned rails onto DERIVED_MARKET_BASELINE (CBR×haircut)
 * with admin «Прибыль» set so customer floating ≈ target, then enables status=1
 * only when eligibility passes.
 */
final class RatesApplyUsdtRubCommercialTargetCommand extends Command
{
    protected $signature = 'rates:apply-usdt-rub-commercial-target
        {--target=95 : Absolute customer floating target RUB per 1 USDT}
        {--dry-run : plan only}
        {--apply : write derived ownership, profit, and enable statuses}
        {--from=USDTTRC20,USDTERC20,USDTBEP20,USDTTON,USDTSOL : restrict source letter codes (comma)}
        {--to=SBERRUB,TBRUB,TCSBRUB,SBPRUB,RFBRUB,ACRUB : classic bank destinations}
        {--export=0 : allow_export after restore (0=off, 1=on if eligibility permits)}';

    protected $description = 'Apply intentional USDT→RUB commercial target via DERIVED baseline + profit';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $dry = (bool) $this->option('dry-run') || !$apply;
        $target = (float) $this->option('target');
        if ($target < 50.0 || $target > 200.0) {
            $this->error('target_out_of_sane_bounds');

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
                    de.status, de.course_value, de.profit, de.floating_fee, de.parser_source_name,
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
            'haircut_note' => 'USDT→RUB commercial target ownership; profit applies once via CanonicalDirectionRateCalculator',
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
            // profit such that final = base × (1 - profit/100) ≈ target
            // ⇒ profit = (1 - target/base) * 100
            $profit = bcmul(bcsub('1', bcdiv((string) $target, $haircutBase, 12), 12), '100', 6);
            $plan[] = [
                'id' => $id,
                'from' => (string) $row->fr,
                'to' => (string) $row->tto,
                'before' => [
                    'status' => (int) $row->status,
                    'course' => (string) $row->course_value,
                    'profit' => (string) $row->profit,
                    'parser' => (string) $row->parser_source_name,
                    'allow_export' => (int) $row->allow_export,
                ],
                'cbr' => $cbr,
                'derived_base_expected' => $haircutBase,
                'profit' => $profit,
                'target' => (string) $target,
            ];
            $registry['directions'][(string) $id] = array_merge($template, [
                'from' => (string) $row->fr,
                'to' => (string) $row->tto,
            ]);
        }

        $registry['count'] = count($registry['directions']);
        $registry['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
        $registry['update_note'] = 'USDT classic RUB commercial target ~'.$target.' via DERIVED + profit; owner chat 2026-08-09';

        $snapDir = storage_path('app/rates/usdt-rub-commercial-target');
        if (!is_dir($snapDir)) {
            File::makeDirectory($snapDir, 0755, true);
        }
        $stamp = gmdate('Ymd\THis\Z');
        $snapFile = $snapDir.'/plan-'.$stamp.'.json';
        file_put_contents($snapFile, json_encode([
            'mode' => $dry ? 'dry-run' : 'apply',
            'target' => $target,
            'cbr' => $cbr,
            'baseline_source' => $baseline['source'] ?? null,
            'plan' => $plan,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($dry) {
            $this->line(json_encode([
                'mode' => 'dry-run',
                'snapshot' => $snapFile,
                'count' => count($plan),
                'sample' => array_slice($plan, 0, 5),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        file_put_contents(
            $registryPath,
            json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
        );

        $auth = DerivedMarketBaselineAuthority::fromStorageApp();
        $refresh = $auth->refreshAll(dryRun: false);

        $calc = CanonicalDirectionRateCalculator::make();
        $elig = RateDirectionEligibility::make();
        $wantExport = (int) $this->option('export') === 1;
        $results = [];
        foreach ($plan as $item) {
            $id = (int) $item['id'];
            $dir = DirectionExchange::query()->find($id);
            if (!$dir) {
                $results[] = ['id' => $id, 'ok' => false, 'reason' => 'missing'];
                continue;
            }
            $dir->profit = $item['profit'];
            $dir->floating_fee = 0;
            $dir->parser_source_name = 'DERIVED_MARKET_BASELINE';
            $dir->is_error_rate = 0;
            $dir->error_rate_text = null;
            $dir->status = 1;
            $dir->allow_export = 0;
            $dir->save();

            $dir = $dir->fresh(['currency1', 'currency2']);
            $floating = $calc->calculate($dir, RateMode::Floating, RateChannel::Website);
            $status = $elig->evaluateDirection($dir);

            // allow_export schema: 0=unrestricted, 1=time-windowed, 2=blocked.
            // Leave 0 when eligibility permits; never flip to 1 without a valid window
            // (empty windows fail closed in shouldExportRate and drop XML rows).
            if ($wantExport && !empty($status['eligible_for_export'])) {
                $dir->allow_export = 0;
                $dir->save();
                $status = $elig->evaluateDirection($dir->fresh(['currency1', 'currency2']));
            } elseif ($wantExport && empty($status['eligible_for_export'])) {
                $dir->allow_export = 2;
                $dir->save();
                $status['reasons'][] = 'export_requested_but_eligibility_denied';
            } elseif (!$wantExport) {
                $dir->allow_export = 2;
                $dir->save();
            }

            $results[] = [
                'id' => $id,
                'from' => $item['from'],
                'to' => $item['to'],
                'ok' => (bool) ($status['eligible_for_order'] ?? false),
                'course' => (string) $dir->course_value,
                'profit' => (string) $dir->profit,
                'customer_floating' => $floating->finalRate,
                'quote_allowed' => (bool) ($status['eligible_for_quote'] ?? false),
                'order_allowed' => (bool) ($status['eligible_for_order'] ?? false),
                'export_allowed' => (bool) ($status['eligible_for_export'] ?? false),
                'allow_export' => (int) $dir->allow_export,
                'classification' => $status['classification'] ?? null,
                'reasons' => $status['reasons'] ?? [],
            ];
        }

        $this->line(json_encode([
            'mode' => 'apply',
            'snapshot' => $snapFile,
            'cbr' => $cbr,
            'target' => $target,
            'refresh_owned' => count($refresh),
            'results' => $results,
            'policy' => RubFamilyPremiumPolicy::fromStorageApp()->summary(),
            'allow_export_schema' => [
                '0' => 'unrestricted_export_when_other_gates_pass',
                '1' => 'time_window_only_requires_allow_export_from_to',
                '2' => 'blocked_never_export',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $failed = count(array_filter($results, static fn ($r) => empty($r['ok'])));
        if ($failed > 0) {
            $this->warn("eligibility_failures={$failed}");
        }

        // Keep BestChange XML membership synchronized with post-apply status/allow_export.
        // Without this, PUBLIC_ORDERABLE rows can remain absent from currencies.xml until
        // the next unrelated scheme:files tick.
        try {
            $this->call('scheme:files');
            $this->info('post_apply_xml_sync=scheme:files');
        } catch (\Throwable $e) {
            $this->warn('post_apply_xml_sync_failed='.$e->getMessage());
        }

        return self::SUCCESS;
    }
}
