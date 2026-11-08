<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DirectionExchange;
use App\Services\Rates\CommercialAdjustmentWriteGate;
use App\Services\Rates\DirectionCreationDefaults;
use App\Services\Rates\ZelleUsdBenchmarkResolver;
use App\Services\Rates\ZelleUsdUsdtBenchmarkAuthority;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Unify crypto↔ZELLEUSD onto ZELLE_USDTTRC20_BENCHMARK.
 *
 * - Ensure every active ZELLE→crypto has a matching crypto→ZELLE (create if missing)
 * - Claim inbound ownership as BENCHMARK (inverse of outgoing BASE)
 * - Pin floating_fee/fix_fee like outgoing
 * - Enable XML for ZELLE→USDCERC20 when hard-disabled
 * - Refresh all Zelle legs via authority
 *
 * Default is dry-run. Pass --apply to mutate.
 */
final class RatesZelleCryptoUnifyCommand extends Command
{
    protected $signature = 'rates:zelle-crypto-unify
        {--apply : Write DB changes}
        {--json : JSON summary}
        {--enable-usdc-xml : Set ZELLEUSD→USDCERC20 allow_export=0}';

    protected $description = 'Unify crypto↔Zelle onto BENCHMARK (dry-run default)';

    /** @var list<string> */
    private const CRYPTO_XML = [
        'BTC', 'LTC', 'ETH', 'BNBBEP20', 'DASH', 'XRP', 'XMR', 'ZEC', 'DOGE', 'TRX',
        'XLM', 'BCH', 'ETC', 'ADA', 'NEO', 'XTZ', 'DOT', 'SOL', 'SHIBERC20',
        'USDTTRC20', 'USDTERC20', 'USDTBEP20', 'USDTSOL', 'USDTTON', 'USDTPOLYGON',
        'USDCERC20', 'GRAM',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $enableUsdcXml = (bool) $this->option('enable-usdc-xml');
        $zid = ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID;
        $auth = ZelleUsdUsdtBenchmarkAuthority::make();

        $idByXml = DB::table('currencies')
            ->whereIn('designation_xml', self::CRYPTO_XML)
            ->pluck('id', 'designation_xml')
            ->all();

        $plan = [
            'mode' => $apply ? 'APPLY' : 'DRY_RUN',
            'create_inbound' => [],
            'claim_inbound' => [],
            'enable_xml' => [],
            'refresh' => null,
            'errors' => [],
        ];

        $templateIn = DB::table('direction_exchange as d')
            ->join('currencies as c1', 'c1.id', '=', 'd.id_currency1')
            ->where('d.id_currency2', $zid)
            ->where('d.status', 1)
            ->whereNull('d.deleted_at')
            ->where('c1.designation_xml', 'USDTTRC20')
            ->select('d.*')
            ->first();

        if ($templateIn === null) {
            $plan['errors'][] = 'missing_template_USDTTRC20_to_ZELLE';
            $this->emit($plan);

            return 1;
        }

        foreach (self::CRYPTO_XML as $xml) {
            $cid = (int) ($idByXml[$xml] ?? 0);
            if ($cid <= 0) {
                $plan['errors'][] = "missing_currency:$xml";
                continue;
            }

            $out = DB::table('direction_exchange')
                ->whereNull('deleted_at')
                ->where('id_currency1', $zid)
                ->where('id_currency2', $cid)
                ->where('status', 1)
                ->first();
            if ($out === null) {
                $plan['errors'][] = "missing_outgoing:ZELLEUSD->$xml";
                continue;
            }

            // Prefer active (status=1). Never prefer status=2 over status=1
            // (orderByDesc(status) wrongly ranks disabled above active).
            $in = DB::table('direction_exchange')
                ->whereNull('deleted_at')
                ->where('id_currency1', $cid)
                ->where('id_currency2', $zid)
                ->orderByRaw('CASE WHEN status = 1 THEN 0 ELSE 1 END')
                ->orderByDesc('id')
                ->first();

            if ($in === null) {
                $plan['create_inbound'][] = [
                    'xml' => $xml,
                    'coin_id' => $cid,
                    'template_id' => (int) $templateIn->id,
                ];
                if ($apply) {
                    $newId = $this->createInboundFromTemplate((array) $templateIn, $cid, $xml);
                    $plan['create_inbound'][array_key_last($plan['create_inbound'])]['created_id'] = $newId;
                    $in = DB::table('direction_exchange')->find($newId);
                }
            }

            if ($in === null) {
                continue;
            }

            $needsClaim = (int) $in->status !== 1
                || (string) ($in->parser_source_name ?? '') !== ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME
                || (string) ($in->floating_fee ?? '') !== ZelleUsdUsdtBenchmarkAuthority::CANONICAL_FLOATING_FEE
                || (string) ($in->fix_fee ?? '') !== ZelleUsdUsdtBenchmarkAuthority::CANONICAL_FIX_FEE;

            if ($needsClaim) {
                $plan['claim_inbound'][] = [
                    'id' => (int) $in->id,
                    'xml' => $xml.'->ZELLEUSD',
                    'from_status' => (int) $in->status,
                    'from_parser' => (string) $in->parser_source_name,
                ];
                if ($apply) {
                    $this->claimInbound((int) $in->id);
                }
            }
        }

        // ZELLEUSD→USDCERC20 XML hard-disable lift
        $usdcOut = DB::table('direction_exchange as d')
            ->join('currencies as c2', 'c2.id', '=', 'd.id_currency2')
            ->where('d.id_currency1', $zid)
            ->whereNull('d.deleted_at')
            ->where('d.status', 1)
            ->where('c2.designation_xml', 'USDCERC20')
            ->select('d.id', 'd.allow_export')
            ->first();
        if ($usdcOut && (int) $usdcOut->allow_export === 2 && $enableUsdcXml) {
            $plan['enable_xml'][] = ['id' => (int) $usdcOut->id, 'pair' => 'ZELLEUSD->USDCERC20'];
            if ($apply) {
                DB::table('direction_exchange')->where('id', (int) $usdcOut->id)->update([
                    'allow_export' => 0,
                    'updated_at' => now(),
                ]);
            }
        } elseif ($usdcOut && (int) $usdcOut->allow_export === 2) {
            $plan['enable_xml'][] = [
                'id' => (int) $usdcOut->id,
                'pair' => 'ZELLEUSD->USDCERC20',
                'skipped' => 'pass --enable-usdc-xml to clear allow_export=2',
            ];
        }

        // Refresh all Zelle legs (outgoing then inbound)
        if ($apply) {
            $plan['refresh'] = $auth->refreshAll(dryRun: false);
        } else {
            $plan['refresh'] = $auth->refreshAll(dryRun: true);
        }

        $this->emit($plan);
        $failed = (int) ($plan['refresh']['failed'] ?? 0);

        return ($failed > 0 && $apply) ? 1 : 0;
    }

    /**
     * @param  array<string,mixed>  $template
     */
    private function createInboundFromTemplate(array $template, int $coinId, string $xml): int
    {
        $defaults = DirectionCreationDefaults::fromStorageApp()->attributesForNewDirection($coinId);
        $data = $template;
        unset($data['id']);
        $data['id_currency1'] = $coinId;
        $data['id_currency2'] = ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID;
        $data['status'] = 1;
        $data['deleted_at'] = null;
        $data['parser_source_name'] = ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME;
        $data['profit'] = 0;
        $data['floating_fee'] = ZelleUsdUsdtBenchmarkAuthority::CANONICAL_FLOATING_FEE;
        $data['fix_fee'] = ZelleUsdUsdtBenchmarkAuthority::CANONICAL_FIX_FEE;
        $data['is_type_rate'] = 1;
        $data['is_error_rate'] = 0;
        $data['error_rate_text'] = null;
        $data['allow_export'] = 0;
        $data['course_value'] = '0';
        $data['manual_rate_value'] = '0';
        $data['min_price1'] = $defaults['min_price1'] ?? $data['min_price1'] ?? '1';
        $data['max_price1'] = $defaults['max_price1'] ?? $data['max_price1'] ?? '100';
        $data['is_manual_min_price1'] = 1;
        $data['is_manual_max_price1'] = 1;
        $data['tech_name'] = $xml.' → Zelle USD';
        $data['seo_title'] = $data['tech_name'];
        $data['seo_description'] = $data['tech_name'];
        $data['created_at'] = now();
        $data['updated_at'] = now();
        foreach ([
            'id_crypto_parser', 'rl_id_parser_exchange', 'id_parser_formula_rate',
            'id_file_parser_rate', 'id_partner_parser_rate', 'bc_id_your_exchange',
            'cr_id_new_rate', 'id_competitor',
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

        return (int) CommercialAdjustmentWriteGate::run(
            'zelle:RatesZelleCryptoUnifyCommand:create',
            static fn () => DB::table('direction_exchange')->insertGetId($data),
        );
    }

    private function claimInbound(int $directionId): void
    {
        CommercialAdjustmentWriteGate::run('zelle:RatesZelleCryptoUnifyCommand:claim', function () use ($directionId): void {
            DB::table('direction_exchange')->where('id', $directionId)->update([
                'status' => 1,
                'deleted_at' => null,
                'parser_source_name' => ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME,
                'profit' => 0,
                'floating_fee' => ZelleUsdUsdtBenchmarkAuthority::CANONICAL_FLOATING_FEE,
                'fix_fee' => ZelleUsdUsdtBenchmarkAuthority::CANONICAL_FIX_FEE,
                'is_type_rate' => 1,
                'is_error_rate' => 0,
                'error_rate_text' => null,
                'add_course1' => 0,
                'add_course2' => 0,
                'your_add_course1' => 0,
                'your_add_course2' => 0,
                'updated_at' => now(),
            ]);
            // Stop BestChange compiler competing on this leg.
            DB::table('bestchange_directions')
                ->where('id_direction_exchange', $directionId)
                ->where('status', 1)
                ->update(['status' => 0, 'updated_at' => now()]);
        });
    }

    /**
     * @param  array<string,mixed>  $plan
     */
    private function emit(array $plan): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return;
        }
        $this->info('mode='.$plan['mode']);
        $this->line('create_inbound='.count($plan['create_inbound']));
        $this->line('claim_inbound='.count($plan['claim_inbound']));
        $this->line('enable_xml='.json_encode($plan['enable_xml']));
        $this->line('refresh_ok='.($plan['refresh']['ok'] ?? '?'));
        $this->line('refresh_failed='.($plan['refresh']['failed'] ?? '?'));
        if ($plan['errors'] !== []) {
            $this->warn('errors='.implode(',', $plan['errors']));
        }
    }
}
