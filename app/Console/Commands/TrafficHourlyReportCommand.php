<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TrafficHourlyReportService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class TrafficHourlyReportCommand extends Command
{
    protected $signature = 'traffic:hourly-report
                            {--dry-run : Print the report text without sending}
                            {--send : Send the report to the configured Telegram chat}
                            {--hour= : Report hour start (Y-m-d\\TH:00:00) in app timezone; default previous completed hour}
                            {--hours= : Aggregate previous N completed local hours (default 1, scheduler uses config)}';

    protected $description = 'Build and optionally send a privacy-safe site activity estimate to Telegram (TRAFFIC-P2a).';

    public function handle(TrafficHourlyReportService $reportService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $send = (bool) $this->option('send');

        if ($dryRun && $send) {
            $this->error('Use either --dry-run or --send, not both.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $send) {
            $dryRun = true;
        }

        [$periodStart, $periodEnd] = $this->resolvePeriod($reportService);

        $data = $reportService->buildReportData($periodStart, $periodEnd);
        $text = $reportService->formatTelegramText($data);

        $this->line($text);

        if ($dryRun) {
            $this->info('Dry-run only — report not sent.');

            return self::SUCCESS;
        }

        if ($reportService->sendTelegramReport($data)) {
            $this->info('Report sent to '.config('support_chat.telegram.hourly_report.chat_id'));

            return self::SUCCESS;
        }

        $this->error('Failed to send report. Check logs and SUPPORT_TELEGRAM_* / SUPPORT_TELEGRAM_HOURLY_REPORT_CHAT_ID config.');

        return self::FAILURE;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolvePeriod(TrafficHourlyReportService $reportService): array
    {
        $hour = trim((string) $this->option('hour'));
        if ($hour !== '') {
            $tz = (string) config('app.timezone', 'UTC');
            $start = CarbonImmutable::parse($hour, $tz)->startOfHour();
            $hoursOption = trim((string) $this->option('hours'));
            $hoursCount = $hoursOption !== '' ? max(1, (int) $hoursOption) : 1;
            $end = $start->addHours($hoursCount);

            return [$start, $end];
        }

        $hoursOption = trim((string) $this->option('hours'));
        if ($hoursOption !== '') {
            return $reportService->periodForHours(max(1, (int) $hoursOption));
        }

        return $reportService->defaultPeriod();
    }
}
