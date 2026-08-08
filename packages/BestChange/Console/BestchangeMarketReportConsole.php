<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Console;

use App\Models\BestChangeDirection;
use App\Models\BestChangeExchangerStat;
use App\Models\BestChangeMarketReport;
use Illuminate\Console\Command;

/**
 * bestchange:market-report
 *
 * Формирует дневной отчёт:
 * - топ rejected причин из explain_payload
 * - топ exchanger по selected_count/rejected_count
 */
final class BestchangeMarketReportConsole extends Command
{
    protected $signature = 'bestchange:market-report {--day=}';
    protected $description = 'Формирует отчёт по рынку BestChange на основе explain_payload и статистики exchanger.';

    public function handle(): int
    {
        $day = $this->option('day')
            ? \Carbon\Carbon::parse((string)$this->option('day'))->toDateString()
            : now()->toDateString();

        // 1) агрегируем rejected из explain_payload
        $rejectedTotals = [];

        $directions = BestChangeDirection::query()
            ->whereNotNull('explain_payload')
            ->get(['id','explain_payload']);

        foreach ($directions as $d) {
            $payload = $d->explain_payload;
            if (!is_array($payload)) continue;

            $rejected = $payload['rejected'] ?? null;
            if (!is_array($rejected)) continue;

            foreach ($rejected as $k => $v) {
                $key = (string)$k;
                $rejectedTotals[$key] = ($rejectedTotals[$key] ?? 0) + (int)$v;
            }
        }

        arsort($rejectedTotals);
        $rejectedTop = array_slice($rejectedTotals, 0, 30, true);

        // 2) топ exchanger по selected/rejected/error
        $topSelected = BestChangeExchangerStat::query()
            ->orderByDesc('selected_count')
            ->limit(30)
            ->get()
            ->toArray();

        $topRejected = BestChangeExchangerStat::query()
            ->orderByDesc('rejected_count')
            ->limit(30)
            ->get()
            ->toArray();

        $payload = [
            'day' => $day,
            'rejected_top' => $rejectedTop,
            'exchangers_top_selected' => $topSelected,
            'exchangers_top_rejected' => $topRejected,
        ];

        BestChangeMarketReport::query()->updateOrCreate(
            ['day' => $day],
            ['payload' => $payload]
        );

        $this->info("BestChange market report saved for {$day}");
        return self::SUCCESS;
    }
}
