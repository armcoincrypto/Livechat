<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DirectionExchange;
use App\Services\Rates\CanonicalDirectionRateCalculator;
use App\Services\Rates\RateMode;
use App\Services\Rates\ZelleUsdBenchmarkResolver;
use App\Services\Rates\ZelleUsdUsdtBenchmarkAuthority;
use Illuminate\Console\Command;

final class RatesZelleUsdUsdtPreviewCommand extends Command
{
    protected $signature = 'rates:zelleusd-usdt-preview
        {--direction= : Single ZELLEUSD outgoing direction id}
        {--all : Preview all ZELLEUSD outgoing directions}
        {--json : JSON output}
        {--fail-on-invalid : Exit 1 if any selected direction lacks a valid benchmark}
        {--apply : Write course_value from benchmark (compiler path; use with care)}
        {--dry-run : With --apply, do not write}';

    protected $description = 'Preview ZELLEUSD→dest rates from USDTTRC20→dest benchmarks + admin floating/fix fees';

    public function handle(): int
    {
        $resolver = ZelleUsdBenchmarkResolver::make();
        $calc = CanonicalDirectionRateCalculator::make();
        $authority = ZelleUsdUsdtBenchmarkAuthority::make();

        $q = DirectionExchange::query()
            ->where('id_currency1', ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID)
            ->whereNull('deleted_at')
            ->with(['currency1', 'currency2']);

        if ($id = $this->option('direction')) {
            $q->where('id', (int) $id);
        } elseif (!$this->option('all')) {
            $this->error('Pass --all or --direction=');

            return 1;
        }

        $rows = [];
        $invalid = 0;
        foreach ($q->orderBy('id')->get() as $dir) {
            $bench = $resolver->resolve($dir);
            $base = $bench->eligible ? (string) $bench->benchmarkRate : '0';
            $floating = $calc->calculate($dir, RateMode::Floating, baseRateOverride: $base);
            $fixed = $calc->calculate($dir, RateMode::Fixed, baseRateOverride: $base);
            $row = [
                'zelle_direction_id' => (int) $dir->id,
                'destination' => (string) ($dir->currency2?->designation_xml ?? ''),
                'benchmark_direction_id' => $bench->benchmarkDirectionId,
                'benchmark_rate' => $bench->benchmarkRate,
                'benchmark_timestamp' => $bench->benchmarkTimestamp,
                'benchmark_source' => $bench->benchmarkSource,
                'floating_fee' => $dir->floating_fee,
                'fix_fee' => $dir->fix_fee,
                'predicted_floating' => $floating->finalRate,
                'predicted_fixed' => $fixed->finalRate,
                'eligible' => $bench->eligible,
                'reason' => $bench->reasonCode,
                'match_method' => $bench->matchMethod,
            ];
            if ($this->option('apply')) {
                $applied = $authority->apply($dir, (bool) $this->option('dry-run'));
                $row['apply'] = $applied;
            }
            $rows[] = $row;
            if (!$bench->eligible) {
                $invalid++;
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'count' => count($rows),
                'invalid' => $invalid,
                'rows' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(
                ['id', 'dest', 'bench_id', 'bench_rate', 'ff', 'xf', 'float', 'fixed', 'ok', 'reason'],
                array_map(static fn ($r) => [
                    $r['zelle_direction_id'],
                    $r['destination'],
                    $r['benchmark_direction_id'],
                    $r['benchmark_rate'],
                    $r['floating_fee'],
                    $r['fix_fee'],
                    $r['predicted_floating'],
                    $r['predicted_fixed'],
                    $r['eligible'] ? 'Y' : 'N',
                    $r['reason'],
                ], $rows)
            );
            $this->info('count='.count($rows).' invalid='.$invalid);
        }

        if ($this->option('fail-on-invalid') && $invalid > 0) {
            return 2;
        }

        return 0;
    }
}
