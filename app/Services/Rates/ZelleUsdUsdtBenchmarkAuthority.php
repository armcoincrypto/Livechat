<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\DirectionExchange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Durable writer: ZELLEUSD outgoing course_value = USDTTRC20→dest benchmark base.
 * Fee is NOT baked in — CanonicalDirectionRateCalculator applies floating_fee/fix_fee once.
 */
final class ZelleUsdUsdtBenchmarkAuthority
{
    public const PARSER_SOURCE_NAME = 'ZELLE_USDTTRC20_BENCHMARK';

    public function __construct(
        private readonly ZelleUsdBenchmarkResolver $resolver = new ZelleUsdBenchmarkResolver(),
    ) {
    }

    public static function make(): self
    {
        return new self();
    }

    public function owns(DirectionExchange $direction): bool
    {
        return $this->resolver->isZelleOutgoing($direction);
    }

    /**
     * @return array{ok:bool,written:bool,course:?string,reason:string,benchmark:?array}
     */
    public function apply(DirectionExchange $direction, bool $dryRun = true): array
    {
        // Owner policy: when BestChange is the intentional runtime owner, do not compete.
        if (ZelleOutgoingRateWriteGuard::yieldsToBestChange((int) $direction->id)) {
            return [
                'ok' => true,
                'written' => false,
                'course' => null,
                'reason' => 'bestchange_active_owner',
                'benchmark' => null,
            ];
        }

        $result = $this->resolver->resolve($direction);
        $bench = $result->toArray();

        if (!$result->eligible || $result->benchmarkRate === null) {
            if (!$dryRun && $this->owns($direction)) {
                // Fail closed: do not retain stale prior ZELLE market rate as public base.
                // Also deactivate + quarantine so status=1 + course=0 cannot stay orderable
                // (rates:health treats that as critical invalid_active_rate).
                DB::table('direction_exchange')->where('id', $direction->id)->update([
                    'course_value' => '0',
                    'is_error_rate' => 1,
                    'error_rate_text' => $result->reasonCode,
                    'parser_source_name' => self::PARSER_SOURCE_NAME,
                    'status' => 0,
                    'allow_export' => 2,
                    'updated_at' => now(),
                ]);
            }

            return [
                'ok' => false,
                'written' => !$dryRun,
                'course' => null,
                'reason' => $result->reasonCode,
                'benchmark' => $bench,
            ];
        }

        $course = $result->benchmarkRate;
        if (!$dryRun) {
            // profit=0 is ZELLE family invariant (Прибыль lives in floating_fee).
            // Gate marks this as an approved compiler neutralization, not RUB magic profit.
            CommercialAdjustmentWriteGate::run('zelle:ZelleUsdUsdtBenchmarkAuthority', function () use ($direction, $course): void {
                DB::table('direction_exchange')->where('id', $direction->id)->update([
                    'course_value' => $course,
                    'manual_rate_value' => $course,
                    'is_error_rate' => 0,
                    'error_rate_text' => null,
                    'parser_source_name' => self::PARSER_SOURCE_NAME,
                    'exchange_rate' => $course,
                    // Neutralize legacy Calculator profit/add_course so fees apply only via floating_fee/fix_fee.
                    'profit' => 0,
                    'add_course1' => 0,
                    'add_course2' => 0,
                    'your_add_course1' => 0,
                    'your_add_course2' => 0,
                    'updated_at' => now(),
                ]);
            });
            Log::info('zelle_usdt_benchmark_applied', [
                'direction_id' => $direction->id,
                'benchmark_direction_id' => $result->benchmarkDirectionId,
                'course' => $course,
                'source' => $result->benchmarkSource,
            ]);
        }

        return [
            'ok' => true,
            'written' => !$dryRun,
            'course' => $course,
            'reason' => $result->reasonCode,
            'benchmark' => $bench,
        ];
    }

    /**
     * @return array{scanned:int,ok:int,failed:int,rows:list<array<string,mixed>>}
     */
    public function refreshAll(bool $dryRun = true): array
    {
        $dirs = DirectionExchange::query()
            ->where('id_currency1', ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID)
            ->whereNull('deleted_at')
            ->get();

        $ok = 0;
        $failed = 0;
        $rows = [];
        foreach ($dirs as $dir) {
            $r = $this->apply($dir, $dryRun);
            $rows[] = [
                'direction_id' => (int) $dir->id,
                'ok' => $r['ok'],
                'course' => $r['course'],
                'reason' => $r['reason'],
                'benchmark_direction_id' => $r['benchmark']['benchmark_direction_id'] ?? null,
            ];
            if ($r['ok']) {
                $ok++;
            } else {
                $failed++;
            }
        }

        return [
            'scanned' => $dirs->count(),
            'ok' => $ok,
            'failed' => $failed,
            'rows' => $rows,
        ];
    }
}
