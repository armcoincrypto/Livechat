<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DirectionExchange;
use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\RateConfiguredExpectation;
use App\Services\Rates\RateSanityGuard;
use App\Services\Rates\RubFamilyPremiumPolicy;
use Illuminate\Console\Command;

/**
 * Report RUB family premium policy status and per-direction economic posture.
 */
final class RatesRubFamilyPolicyStatusCommand extends Command
{
    protected $signature = 'rates:rub-family-policy-status
        {--format=json : json|table}
        {--family= : Optional family key filter (e.g. SBPRUB)}
        {--limit=0 : Limit directions (0 = all matching)}';

    protected $description = 'Report RUB family premium policy approval state and direction eligibility implications.';

    public function handle(
        IndependentMarketBaseline $baseline,
        RateConfiguredExpectation $expectation,
        RateSanityGuard $guard,
    ): int {
        $policy = RubFamilyPremiumPolicy::fromStorageApp();
        $familyFilter = strtoupper(trim((string) $this->option('family')));
        $limit = max(0, (int) $this->option('limit'));

        $query = DirectionExchange::query()
            ->with(['currency1:id,designation_xml', 'currency2:id,designation_xml'])
            ->whereNull('deleted_at')
            ->orderBy('id');

        $rows = [];
        foreach ($query->cursor() as $direction) {
            $from = strtoupper((string) ($direction->currency1?->designation_xml ?? ''));
            $to = strtoupper((string) ($direction->currency2?->designation_xml ?? ''));
            if (!str_contains($to, 'RUB')) {
                continue;
            }
            if (IndependentMarketBaseline::assetFromCode($from) === null) {
                continue;
            }
            $family = $policy->familyForDestination($to);
            $familyKey = (string) ($family['family_key'] ?? 'UNMAPPED');
            if ($familyFilter !== '' && $familyKey !== $familyFilter && $to !== $familyFilter) {
                continue;
            }

            $ind = $this->baselineFor($baseline, $from, $to);
            $course = $guard->normalize((string) ($direction->course_value ?? ''));
            $profit = (string) ($direction->profit ?? '0');
            $analysis = null;
            if ($ind !== null && $course !== null) {
                $analysis = $expectation->analyze(
                    baseline: $ind['rate'],
                    actual: $course,
                    profitPercent: $profit,
                );
            }
            $raw = $analysis['raw_market_deviation'] ?? null;
            $eval = $policy->evaluateCoinRub(
                $to,
                $raw === null ? null : (float) $raw,
                (float) $profit,
            );

            $reasons = $eval['reasons'];
            if ((int) $direction->status !== 1) {
                $reasons[] = 'direction_not_active';
            }
            if ((int) $direction->allow_export === 2) {
                $reasons[] = 'export_hard_disabled';
            }

            $exportOk = (bool) $eval['export_allowed']
                && in_array($eval['classification'], ['PASS', 'PASS_EXPLAINED_SPREAD'], true)
                && $ind !== null
                && $course !== null
                && (int) $direction->status === 1
                && (int) $direction->allow_export !== 2;
            $orderOk = (bool) $eval['order_allowed']
                && $ind !== null
                && $course !== null
                && (int) $direction->status === 1
                && !in_array($eval['classification'], ['QUARANTINE_REQUIRED', 'NO_POLICY'], true);

            $rows[] = [
                'direction_id' => (int) $direction->id,
                'from' => $from,
                'to' => $to,
                'family' => $familyKey,
                'policy_approved' => $policy->isApproved(),
                'family_decision' => $family['decision'] ?? ($family['proposed_decision'] ?? null),
                'baseline' => $ind['rate'] ?? null,
                'baseline_source' => $ind['source'] ?? null,
                'actual_rate' => $course,
                'configured_profit_percent' => $profit,
                'expected_premium_percent' => $eval['expected_premium_percent'],
                'explained_family_premium_percent' => $eval['expected_premium_percent'],
                'raw_market_deviation' => $raw,
                'unexplained_deviation' => $eval['unexplained_vs_expected_percent'],
                'classification' => $eval['classification'],
                'order_eligibility' => $orderOk,
                'export_eligibility' => $exportOk,
                'blocking_reasons' => $reasons,
                'direction_status' => (int) $direction->status,
                'allow_export' => (int) $direction->allow_export,
            ];
            if ($limit > 0 && count($rows) >= $limit) {
                break;
            }
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'policy' => $policy->summary(),
            'direction_count' => count($rows),
            'directions' => $rows,
        ];

        if ((string) $this->option('format') === 'table') {
            $this->table(
                ['id', 'pair', 'family', 'export', 'reasons'],
                collect($rows)->map(fn ($r) => [
                    $r['direction_id'],
                    $r['from'] . '→' . $r['to'],
                    $r['family'],
                    $r['export_eligibility'] ? 'yes' : 'no',
                    implode(',', $r['blocking_reasons']),
                ])->all()
            );
        } else {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }

    /**
     * @return array{rate:string,source:string}|null
     */
    private function baselineFor(IndependentMarketBaseline $baseline, string $from, string $to): ?array
    {
        $asset = IndependentMarketBaseline::assetFromCode($from);
        if ($asset === null) {
            return null;
        }
        if ($asset === 'USDT' || $asset === 'USDC') {
            $q = $baseline->quote('USDRUB');

            return $q ? ['rate' => $q['rate'], 'source' => $q['source']] : null;
        }
        $q = $baseline->cryptoRub($asset);

        return $q ? ['rate' => $q['rate'], 'source' => $q['source']] : null;
    }
}
