<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\DirectionExchange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Durable writer for both Zelle legs:
 * - ZELLEUSD → dest BASE = USDTTRC20→dest benchmark hierarchy
 * - coin → ZELLEUSD BASE = exact inverse of the outgoing peer BASE
 *
 * Fee is NOT baked in — CanonicalDirectionRateCalculator applies floating_fee/fix_fee once.
 */
final class ZelleUsdUsdtBenchmarkAuthority
{
    public const PARSER_SOURCE_NAME = 'ZELLE_USDTTRC20_BENCHMARK';

    /** Canonical commercial pins (admin «Прибыль» +6 ⇔ floating_fee=-6). */
    public const CANONICAL_FLOATING_FEE = '-6';

    public const CANONICAL_FIX_FEE = '-3';

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
        if ($this->resolver->isZelleOutgoing($direction)) {
            return true;
        }

        // Incoming owns only when already labeled BENCHMARK (or being migrated).
        return $this->resolver->isZelleIncoming($direction)
            && (string) ($direction->parser_source_name ?? '') === self::PARSER_SOURCE_NAME;
    }

    /**
     * @return array{ok:bool,written:bool,course:?string,reason:string,benchmark:?array}
     */
    public function apply(DirectionExchange $direction, bool $dryRun = true): array
    {
        // Owner policy: when BestChange is the intentional runtime owner on outgoing, do not compete.
        if ($this->resolver->isZelleOutgoing($direction)
            && ZelleOutgoingRateWriteGuard::yieldsToBestChange((int) $direction->id)
        ) {
            return [
                'ok' => true,
                'written' => false,
                'course' => null,
                'reason' => 'bestchange_active_owner',
                'benchmark' => null,
            ];
        }

        if (!$this->resolver->isZelleLeg($direction)) {
            return [
                'ok' => false,
                'written' => false,
                'course' => null,
                'reason' => ZelleUsdBenchmarkResult::REASON_NOT_ZELLE_LEG,
                'benchmark' => null,
            ];
        }

        $result = $this->resolver->resolve($direction);
        $bench = $result->toArray();

        if (!$result->eligible || $result->benchmarkRate === null) {
            if (!$dryRun && $this->owns($direction)) {
                // Fail closed: do not retain stale prior ZELLE market rate as public base.
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
            CommercialAdjustmentWriteGate::run('zelle:ZelleUsdUsdtBenchmarkAuthority', function () use ($direction, $course): void {
                DB::table('direction_exchange')->where('id', $direction->id)->update([
                    'course_value' => $course,
                    'manual_rate_value' => $course,
                    'is_error_rate' => 0,
                    'error_rate_text' => null,
                    'parser_source_name' => self::PARSER_SOURCE_NAME,
                    'exchange_rate' => $course,
                    'profit' => 0,
                    'add_course1' => 0,
                    'add_course2' => 0,
                    'your_add_course1' => 0,
                    'your_add_course2' => 0,
                    'floating_fee' => self::CANONICAL_FLOATING_FEE,
                    'fix_fee' => self::CANONICAL_FIX_FEE,
                    'is_type_rate' => 1,
                    'updated_at' => now(),
                ]);
            });
            Log::info('zelle_usdt_benchmark_applied', [
                'direction_id' => $direction->id,
                'benchmark_direction_id' => $result->benchmarkDirectionId,
                'course' => $course,
                'source' => $result->benchmarkSource,
                'leg' => $this->resolver->isZelleIncoming($direction) ? 'inbound' : 'outgoing',
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
     * Refresh outgoing first, then inbound inverses (depends on outgoing BASE).
     *
     * @return array{scanned:int,ok:int,failed:int,rows:list<array<string,mixed>>}
     */
    public function refreshAll(bool $dryRun = true): array
    {
        $outgoing = DirectionExchange::query()
            ->where('id_currency1', ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID)
            ->whereNull('deleted_at')
            ->get();

        $inbound = DirectionExchange::query()
            ->where('id_currency2', ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID)
            ->where('id_currency1', '!=', ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID)
            ->whereNull('deleted_at')
            ->get();

        $ok = 0;
        $failed = 0;
        $rows = [];

        foreach ([$outgoing, $inbound] as $batch) {
            foreach ($batch as $dir) {
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
        }

        return [
            'scanned' => $outgoing->count() + $inbound->count(),
            'ok' => $ok,
            'failed' => $failed,
            'rows' => $rows,
        ];
    }
}
