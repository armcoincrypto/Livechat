<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DirectionExchange;
use App\Services\Rates\CurrencyPublicRetirement;
use App\Services\Rates\DerivedMarketBaselineAuthority;
use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\PublicDuplicateExclusion;
use App\Services\Rates\RateDirectionEligibility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * ALL active coins × ALL operational CARD* rails coverage.
 *
 * Creates/reactivates only when automatic derived baseline legs are fresh.
 * Never invents prices; never restores retired currencies; never physical-deletes.
 */
final class RatesCoinCardCoverageApplyCommand extends Command
{
    protected $signature = 'rates:coin-card-coverage-apply
        {--dry-run : plan only (default)}
        {--apply : apply DB + derived ownership}
        {--audit= : evidence directory}
        {--haircut=0.999 : derived haircut}';

    protected $description = 'Fill safe missing coin→CARD pairs via DERIVED_MARKET_BASELINE';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $dry = ! $apply;
        $audit = (string) ($this->option('audit') ?: storage_path('app/coin-card-coverage'));
        File::ensureDirectoryExists($audit);
        $haircut = (string) $this->option('haircut');

        PublicDuplicateExclusion::clearCache();
        $imb = new IndependentMarketBaseline();
        $elig = RateDirectionEligibility::make();
        $derived = DerivedMarketBaselineAuthority::fromStorageApp();

        $coins = $this->activeCoins($imb);
        $cards = $this->wantedCards();
        $plan = [];
        $trueExceptions = [];

        foreach ($coins as $coin) {
            foreach ($cards as $card) {
                $cell = $this->classifyCell($coin, $card, $imb, $elig);
                if (in_array($cell['classification'], ['COMPLETE', 'DUPLICATE_ALIAS'], true)
                    && ($cell['quoteable'] ?? false)
                    && ($cell['automatic'] ?? false)
                ) {
                    continue;
                }
                if ($cell['classification'] === 'EXISTS_EXCLUDED' || $cell['classification'] === 'RETIREMENT_EXCLUDED') {
                    continue;
                }

                $probe = $this->safeRateProbe($coin, $card, $imb);
                if (! $probe['ok']) {
                    $trueExceptions[] = $cell + ['exception_reason' => $probe['reason']];
                    continue;
                }

                if (in_array($cell['classification'], ['MISSING', 'EXISTS_DISABLED', 'NO_SAFE_RATE_SOURCE'], true)
                    || ($cell['classification'] === 'COMPLETE' && ! ($cell['automatic'] ?? false))
                ) {
                    $plan[] = $this->buildAction($coin, $card, $cell, $probe, $haircut);
                }
            }
        }

        $planPath = $audit.'/apply-plan.json';
        File::put($planPath, json_encode([
            'mode' => $dry ? 'dry-run' : 'apply',
            'coins' => count($coins),
            'cards' => count($cards),
            'plan_count' => count($plan),
            'true_exceptions' => count($trueExceptions),
            'plan' => $plan,
            'exceptions' => $trueExceptions,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('plan='.count($plan).' true_exceptions='.count($trueExceptions).' file='.$planPath);

        if ($dry) {
            return self::SUCCESS;
        }

        $rollback = [];
        $created = [];
        $modified = [];
        $derivedCfg = $derived->config();
        if (! isset($derivedCfg['directions']) || ! is_array($derivedCfg['directions'])) {
            $derivedCfg['directions'] = [];
        }

        $failed = [];
        DB::beginTransaction();
        try {
            foreach ($plan as $action) {
                $result = $this->applyAction($action, $derivedCfg, $rollback, $created, $modified, $elig, $imb);
                if ($result['ok'] !== true) {
                    $failed[] = $result;
                    $this->warn('pair_failed '.$action['coin_code'].'→'.$action['card_code'].' '.($result['reason'] ?? ''));
                }
            }

            $derivedCfg['count'] = count($derivedCfg['directions']);
            $derivedCfg['approved_at'] = gmdate('Y-m-d\TH:i:s\Z');
            $derivedCfg['authority'] = 'DERIVED_MARKET_BASELINE';
            $cfgPath = base_path('resources/rates/derived-market-baseline-directions.json');
            File::put($cfgPath, json_encode($derivedCfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

            // Re-apply rates with updated ownership file.
            $derived2 = DerivedMarketBaselineAuthority::fromStorageApp();
            foreach (array_merge($created, $modified) as $id) {
                $derived2->apply((int) $id, false);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            File::put($audit.'/apply-error.txt', $e->getMessage()."\n".$e->getTraceAsString());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        File::put($audit.'/rollback.sql', implode("\n", $rollback)."\n");
        File::put($audit.'/apply-result.json', json_encode([
            'created' => $created,
            'modified' => $modified,
            'failed' => $failed,
            'created_count' => count($created),
            'modified_count' => count($modified),
            'failed_count' => count($failed),
            'deleted_count' => 0,
        ], JSON_PRETTY_PRINT));

        $this->info('created='.count($created).' modified='.count($modified).' failed='.count($failed));

        return self::SUCCESS;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function activeCoins(IndependentMarketBaseline $imb): array
    {
        $rows = DB::select("
            SELECT c.id, c.designation_xml, c.tech_name, c.status
            FROM currencies c
            WHERE c.deleted_at IS NULL
              AND c.status = 0
              AND EXISTS (SELECT 1 FROM currency_filter x WHERE x.currency_id=c.id AND x.filter_currency_id=3)
            ORDER BY c.designation_xml, c.id
        ");
        $out = [];
        foreach ($rows as $r) {
            $code = (string) $r->designation_xml;
            if (CurrencyPublicRetirement::isDesignationRetired($code)) {
                continue;
            }
            if (preg_match('/^(CARD|CASH|WIRE|ZELLE)/', $code)) {
                continue;
            }
            $asset = IndependentMarketBaseline::assetFromCode($code);
            $out[] = [
                'id' => (int) $r->id,
                'code' => $code,
                'name' => (string) $r->tech_name,
                'asset' => $asset,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function wantedCards(): array
    {
        $rows = DB::select("
            SELECT c.id, c.designation_xml, c.tech_name, c.status, c.visible_receiving,
              (SELECT COUNT(*) FROM direction_exchange d WHERE d.id_currency2=c.id AND d.deleted_at IS NULL AND d.status=1) AS as_dest_enabled
            FROM currencies c
            WHERE c.deleted_at IS NULL AND c.designation_xml LIKE 'CARD%'
            ORDER BY c.designation_xml, c.id
        ");
        $by = [];
        foreach ($rows as $r) {
            $by[(string) $r->designation_xml][] = $r;
        }
        $out = [];
        foreach ($by as $code => $variants) {
            if (CurrencyPublicRetirement::isDesignationRetired($code)) {
                continue;
            }
            usort($variants, function ($a, $b) {
                $sa = [(int) $a->status === 0 ? 0 : 1, -((int) $a->visible_receiving), -((int) $a->as_dest_enabled), (int) $a->id];
                $sb = [(int) $b->status === 0 ? 0 : 1, -((int) $b->visible_receiving), -((int) $b->as_dest_enabled), (int) $b->id];

                return $sa <=> $sb;
            });
            $c = $variants[0];
            $operational = ((int) $c->status === 0) && ((int) $c->as_dest_enabled > 0 || (int) $c->visible_receiving === 1);
            if (! $operational) {
                continue;
            }
            $out[] = [
                'id' => (int) $c->id,
                'code' => $code,
                'name' => (string) $c->tech_name,
                'fiat' => preg_replace('/^CARD/', '', $code) ?: '',
                'variant_ids' => array_map(static fn ($v) => (int) $v->id, $variants),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string,mixed>  $coin
     * @param  array<string,mixed>  $card
     * @return array<string,mixed>
     */
    private function classifyCell(array $coin, array $card, IndependentMarketBaseline $imb, RateDirectionEligibility $elig): array
    {
        $dirs = DB::table('direction_exchange')
            ->where('id_currency1', $coin['id'])
            ->whereIn('id_currency2', $card['variant_ids'])
            ->get();
        if ($dirs->isEmpty()) {
            return [
                'coin' => $coin['code'],
                'card' => $card['code'],
                'classification' => 'MISSING',
                'direction_id' => null,
            ];
        }
        $sorted = $dirs->sortByDesc(function ($d) use ($card) {
            $s = 0;
            if ($d->deleted_at === null) {
                $s += 1000;
            }
            if ((int) $d->status === 1) {
                $s += 100;
            }
            if ((int) $d->id_currency2 === (int) $card['id']) {
                $s += 10;
            }

            return $s;
        })->values();
        $d = $sorted[0];
        $enabledSameDest = $dirs->filter(static fn ($x) => $x->deleted_at === null && (int) $x->status === 1 && (int) $x->id_currency2 === (int) $d->id_currency2)->count();
        $model = DirectionExchange::with(['currency1', 'currency2'])->find($d->id);
        $raw = $model ? $elig->evaluateDirection($model) : [];
        $src = (string) ($d->parser_source_name ?? '');
        $isManual = (bool) preg_match('/ручн|manual/iu', $src);
        $courseOk = is_numeric((string) $d->course_value) && (float) $d->course_value > 0;
        $automatic = $courseOk && ! $isManual;
        // Derived-owned "Ручной курс" label is still automatic authority once re-labeled;
        // treat positive course with derived config ownership as automatic for planning.
        if ($courseOk && $isManual && DerivedMarketBaselineAuthority::fromStorageApp()->owns((int) $d->id)) {
            $automatic = true;
        }
        $excluded = PublicDuplicateExclusion::isExcluded((int) $d->id);
        $classification = 'COMPLETE';
        if ($d->deleted_at !== null || (int) $d->status !== 1) {
            $classification = 'EXISTS_DISABLED';
        } elseif ($excluded) {
            $classification = 'EXISTS_EXCLUDED';
        } elseif ($enabledSameDest > 1) {
            $classification = 'DUPLICATE_ALIAS';
        } elseif (! $automatic) {
            $classification = 'NO_SAFE_RATE_SOURCE';
        }

        return [
            'coin' => $coin['code'],
            'card' => $card['code'],
            'classification' => $classification,
            'direction_id' => (int) $d->id,
            'status' => (int) $d->status,
            'deleted_at' => $d->deleted_at,
            'quoteable' => ! empty($raw['quote_allowed']),
            'orderable' => ! empty($raw['order_allowed']),
            'automatic' => $automatic,
            'rate_source' => $src,
            'row' => $d,
        ];
    }

    /**
     * @param  array<string,mixed>  $coin
     * @param  array<string,mixed>  $card
     * @return array{ok:bool,reason:?string,asset_sym:?string,fiat_sym:?string,rate:?string,components:array}
     */
    private function safeRateProbe(array $coin, array $card, IndependentMarketBaseline $imb): array
    {
        $asset = $coin['asset'];
        $fiat = $card['fiat'];
        if (! is_string($asset) || $asset === '' || $fiat === '') {
            return ['ok' => false, 'reason' => 'asset_or_fiat_unmapped', 'asset_sym' => null, 'fiat_sym' => null, 'rate' => null, 'components' => []];
        }
        if ($asset === 'PEPE') {
            $aq = $imb->quote('PEPEUSDT');
            if ($aq === null) {
                return ['ok' => false, 'reason' => 'pepe_market_unavailable', 'asset_sym' => 'PEPEUSDT', 'fiat_sym' => 'USD'.$fiat, 'rate' => null, 'components' => []];
            }
        }
        $fiatSym = 'USD'.$fiat;
        $fq = $imb->quote($fiatSym);
        if ($fq === null || (float) $fq['rate'] <= 0) {
            return ['ok' => false, 'reason' => 'fiat_leg_missing', 'asset_sym' => null, 'fiat_sym' => $fiatSym, 'rate' => null, 'components' => ['fiat' => $fq]];
        }
        if (in_array($asset, ['USDT', 'USDC', 'TUSD'], true)) {
            $rate = bcmul('1', (string) $fq['rate'], 12);
            $rate = bcmul($rate, '0.999', 12);

            return [
                'ok' => true,
                'reason' => null,
                'asset_sym' => 'USDT_PEG',
                'fiat_sym' => $fiatSym,
                'rate' => $rate,
                'components' => ['fiat' => $fq],
            ];
        }
        $aq = $imb->quote($asset.'USDT');
        if ($aq === null || (float) $aq['rate'] <= 0) {
            return ['ok' => false, 'reason' => 'asset_leg_missing', 'asset_sym' => $asset.'USDT', 'fiat_sym' => $fiatSym, 'rate' => null, 'components' => ['asset' => $aq, 'fiat' => $fq]];
        }
        $rate = bcmul((string) $aq['rate'], (string) $fq['rate'], 18);
        $rate = bcmul($rate, '0.999', 12);

        return [
            'ok' => (float) $rate > 0,
            'reason' => (float) $rate > 0 ? null : 'non_positive',
            'asset_sym' => $asset.'USDT',
            'fiat_sym' => $fiatSym,
            'rate' => $rate,
            'components' => ['asset' => $aq, 'fiat' => $fq],
        ];
    }

    /**
     * @param  array<string,mixed>  $coin
     * @param  array<string,mixed>  $card
     * @param  array<string,mixed>  $cell
     * @param  array<string,mixed>  $probe
     * @return array<string,mixed>
     */
    private function buildAction(array $coin, array $card, array $cell, array $probe, string $haircut): array
    {
        $template = $this->templateForCard($card);
        $limits = $this->limitsForCoinCard($coin, $card, $template);

        return [
            'coin_id' => $coin['id'],
            'coin_code' => $coin['code'],
            'card_id' => $card['id'],
            'card_code' => $card['code'],
            'existing_direction_id' => $cell['direction_id'] ?? null,
            'existing_status' => $cell['status'] ?? null,
            'existing_deleted_at' => $cell['deleted_at'] ?? null,
            'action' => ($cell['direction_id'] ?? null) ? 'reactivate' : 'create',
            'template_direction_id' => $template['id'] ?? null,
            'rate' => $probe['rate'],
            'asset_sym' => $probe['asset_sym'],
            'fiat_sym' => $probe['fiat_sym'],
            'haircut' => $haircut,
            'limits' => $limits,
            'tech_name' => $coin['name'].' → '.$card['name'],
        ];
    }

    /**
     * @param  array<string,mixed>  $card
     * @return array<string,mixed>|null
     */
    private function templateForCard(array $card): ?array
    {
        $row = DB::selectOne("
            SELECT d.*
            FROM direction_exchange d
            JOIN currencies c1 ON c1.id = d.id_currency1
            WHERE d.id_currency2 = ?
              AND d.deleted_at IS NULL AND d.status = 1
              AND c1.designation_xml = 'USDTTRC20'
            LIMIT 1
        ", [$card['id']]);
        if (! $row) {
            $row = DB::selectOne("
                SELECT d.*
                FROM direction_exchange d
                WHERE d.id_currency2 = ? AND d.deleted_at IS NULL AND d.status = 1
                ORDER BY d.id ASC
                LIMIT 1
            ", [$card['id']]);
        }

        return $row ? (array) $row : null;
    }

    /**
     * @param  array<string,mixed>  $coin
     * @param  array<string,mixed>  $card
     * @param  array<string,mixed>|null  $template
     * @return array<string,mixed>
     */
    private function limitsForCoinCard(array $coin, array $card, ?array $template): array
    {
        $defaults = \App\Services\Rates\DirectionCreationDefaults::fromStorageApp();
        $give = $defaults->giveLimitsForXml((string) ($coin['designation_xml'] ?? $coin['code'] ?? ''));

        $peer = DB::selectOne("
            SELECT d.min_price1, d.max_price1, d.min_price2, d.max_price2, d.type_reserve, d.direction_reserve, d.is_type_rate, d.profit, d.floating_fee
            FROM direction_exchange d
            JOIN currencies c2 ON c2.id = d.id_currency2
            WHERE d.id_currency1 = ?
              AND d.deleted_at IS NULL AND d.status = 1
              AND CAST(d.min_price1 AS DECIMAL(36,18)) > 0
              AND CAST(d.max_price1 AS DECIMAL(36,18)) >= CAST(d.min_price1 AS DECIMAL(36,18))
            ORDER BY CASE
              WHEN c2.designation_xml LIKE 'USDT%' THEN 0
              WHEN c2.designation_xml LIKE 'CARD%' THEN 1
              ELSE 2 END, d.id
            LIMIT 1
        ", [$coin['id']]);

        // Owner give-limits policy wins for currency1 (e.g. GRAM 400–500).
        $min1 = $give['min'];
        $max1 = $give['max'];
        if ((float) $max1 < (float) $min1) {
            $max1 = $min1;
        }

        return [
            'min_price1' => (string) $min1,
            'max_price1' => (string) $max1,
            'min_price2' => (string) ($template['min_price2'] ?? '0'),
            'max_price2' => (string) ($template['max_price2'] ?? '0'),
            'type_reserve' => (int) ($peer->type_reserve ?? $template['type_reserve'] ?? 0),
            'direction_reserve' => (string) ($peer->direction_reserve ?? $template['direction_reserve'] ?? '0'),
            'is_type_rate' => 1,
            'profit' => $defaults->defaultProfitPercent(),
            'floating_fee' => '0',
            'is_manual_min_price1' => 1,
            'is_manual_max_price1' => 1,
        ];
    }

    /**
     * @param  array<string,mixed>  $action
     * @param  array<string,mixed>  $derivedCfg
     * @param  list<string>  $rollback
     * @param  list<int>  $created
     * @param  list<int>  $modified
     * @return array<string,mixed>
     */
    private function applyAction(
        array $action,
        array &$derivedCfg,
        array &$rollback,
        array &$created,
        array &$modified,
        RateDirectionEligibility $elig,
        IndependentMarketBaseline $imb,
    ): array {
        $limits = $action['limits'];
        if ((float) $limits['min_price1'] <= 0 || (float) $limits['max_price1'] < (float) $limits['min_price1']) {
            return ['ok' => false, 'reason' => 'invalid_limits', 'action' => $action];
        }

        $dirId = $action['existing_direction_id'] ?? null;
        if ($dirId) {
            $before = DB::table('direction_exchange')->where('id', $dirId)->first();
            if (! $before) {
                return ['ok' => false, 'reason' => 'missing_existing', 'action' => $action];
            }
            $rollback[] = sprintf(
                'UPDATE direction_exchange SET status=%d, deleted_at=%s, course_value=%s, manual_rate_value=%s, parser_source_name=%s, allow_export=%d, is_error_rate=%d, min_price1=%s, max_price1=%s WHERE id=%d;',
                (int) $before->status,
                $before->deleted_at === null ? 'NULL' : "'".$before->deleted_at."'",
                $this->sqlQuote((string) $before->course_value),
                $this->sqlQuote((string) $before->manual_rate_value),
                $this->sqlQuote((string) $before->parser_source_name),
                (int) $before->allow_export,
                (int) $before->is_error_rate,
                $this->sqlQuote((string) $before->min_price1),
                $this->sqlQuote((string) $before->max_price1),
                (int) $dirId
            );
            DB::table('direction_exchange')->where('id', $dirId)->update([
                'status' => 1,
                'deleted_at' => null,
                'course_value' => $action['rate'],
                'manual_rate_value' => $action['rate'],
                'parser_source_name' => 'DERIVED_MARKET_BASELINE',
                'is_error_rate' => 0,
                'error_rate_text' => null,
                'allow_export' => ((int) $before->allow_export === 2) ? 0 : (int) $before->allow_export,
                'min_price1' => $limits['min_price1'],
                'max_price1' => $limits['max_price1'],
                'min_price2' => $limits['min_price2'],
                'max_price2' => $limits['max_price2'],
                'profit' => $limits['profit'],
                'floating_fee' => $limits['floating_fee'],
                'is_type_rate' => $limits['is_type_rate'],
                'is_manual_min_price1' => (int) ($limits['is_manual_min_price1'] ?? 1),
                'is_manual_max_price1' => (int) ($limits['is_manual_max_price1'] ?? 1),
                'updated_at' => now(),
            ]);
            $modified[] = (int) $dirId;
        } else {
            $template = $action['template_direction_id']
                ? DB::table('direction_exchange')->where('id', $action['template_direction_id'])->first()
                : null;
            if (! $template) {
                return ['ok' => false, 'reason' => 'no_template', 'action' => $action];
            }
            $data = (array) $template;
            unset($data['id']);
            $data['id_currency1'] = $action['coin_id'];
            $data['id_currency2'] = $action['card_id'];
            $data['status'] = 1;
            $data['deleted_at'] = null;
            $data['course_value'] = $action['rate'];
            $data['manual_rate_value'] = $action['rate'];
            $data['parser_source_name'] = 'DERIVED_MARKET_BASELINE';
            $data['is_error_rate'] = 0;
            $data['error_rate_text'] = null;
            $data['allow_export'] = 0; // website first; XML only when BC-ready later
            $data['min_price1'] = $limits['min_price1'];
            $data['max_price1'] = $limits['max_price1'];
            $data['min_price2'] = $limits['min_price2'];
            $data['max_price2'] = $limits['max_price2'];
            $data['type_reserve'] = $limits['type_reserve'];
            $data['direction_reserve'] = $limits['direction_reserve'];
            $data['is_type_rate'] = $limits['is_type_rate'];
            $data['profit'] = $limits['profit'];
            $data['floating_fee'] = $limits['floating_fee'];
            $data['is_manual_min_price1'] = (int) ($limits['is_manual_min_price1'] ?? 1);
            $data['is_manual_max_price1'] = (int) ($limits['is_manual_max_price1'] ?? 1);
            $data['tech_name'] = $action['tech_name'];
            $data['seo_title'] = $action['tech_name'];
            $data['seo_description'] = $action['tech_name'];
            $data['seo_keywords'] = $action['coin_code'].','.$action['card_code'];
            $data['created_at'] = now();
            $data['updated_at'] = now();
            // Avoid unique/export linkage collisions from template; keep NOT NULL ints at 0.
            foreach (['id_bestchange_rates'] as $k) {
                if (array_key_exists($k, $data)) {
                    $data[$k] = null;
                }
            }
            foreach ([
                'id_crypto_parser', 'rl_id_parser_exchange', 'id_parser_formula_rate',
                'id_file_parser_rate', 'id_partner_parser_rate', 'bc_id_your_exchange',
                'cr_id_new_rate', 'id_competitor', 'id_merchant', 'id_group_commission',
            ] as $k) {
                if (array_key_exists($k, $data)) {
                    $data[$k] = 0;
                }
            }
            if (array_key_exists('enable_file_parser_rate', $data)) {
                $data['enable_file_parser_rate'] = 0;
            }
            if (array_key_exists('bc_enable_your_course', $data)) {
                $data['bc_enable_your_course'] = 0;
            }
            $dirId = (int) DB::table('direction_exchange')->insertGetId($data);
            $rollback[] = 'UPDATE direction_exchange SET status=0, deleted_at=NOW(), allow_export=2 WHERE id='.$dirId.';';
            $created[] = $dirId;
        }

        $derivedCfg['directions'][(string) $dirId] = [
            'from' => $action['coin_code'],
            'to' => $action['card_code'],
            'public_aliases' => [],
            'asset_leg' => [
                'symbol' => $action['asset_sym'],
                'orientation' => $action['asset_sym'] === 'USDT_PEG' ? 'unity' : 'asset_per_usdt',
            ],
            'fiat_leg' => [
                'symbol' => $action['fiat_sym'],
                'orientation' => 'fiat_per_usd',
            ],
            'formula' => 'course = asset_leg * fiat_leg * haircut',
            'haircut' => $action['haircut'],
            'haircut_note' => 'all-coins-all-cards coverage; profit field not double-applied',
            'freshness' => [
                'crypto_max_age_seconds' => 900,
                'fiat_max_age_seconds' => 21600,
            ],
            'ownership' => [
                'parser_source_name' => 'DERIVED_MARKET_BASELINE',
                'block_bestchange_overwrite' => false,
                'keep_bestchange_link_status' => 0,
            ],
            'on_stale_or_missing' => 'mark_unavailable_retain_last_valid',
        ];

        $model = DirectionExchange::with(['currency1', 'currency2'])->find($dirId);
        $raw = $model ? $elig->evaluateDirection($model) : [];
        if (empty($raw['quote_allowed']) || empty($raw['order_allowed'])) {
            // Keep created/reactivated but not considered public-success — leave rate, disable if quote fails hard.
            if (empty($raw['quote_allowed'])) {
                DB::table('direction_exchange')->where('id', $dirId)->update([
                    'status' => 0,
                    'updated_at' => now(),
                ]);

                return ['ok' => false, 'reason' => 'quote_or_order_failed', 'direction_id' => $dirId, 'raw' => $raw];
            }
        }

        return ['ok' => true, 'direction_id' => $dirId, 'raw' => $raw];
    }

    private function sqlQuote(string $v): string
    {
        return "'".str_replace("'", "''", $v)."'";
    }
}
