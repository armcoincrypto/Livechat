<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use Carbon\CarbonImmutable;
use iEXPackages\SupportChat\Services\SupportChatHourlyReportService;
use Illuminate\Console\Command;

final class SupportChatHourlyReportCommand extends Command
{
    protected $signature = 'support-chat:hourly-report
                            {--dry-run : Print the report text without sending}
                            {--send : Send the report to the configured Telegram chat}
                            {--at= : Report hour start (Y-m-d H:00) in app timezone; default previous completed hour}';

    protected $description = 'Build and optionally send a privacy-safe hourly Support Chat activity report to Telegram.';

    public function handle(SupportChatHourlyReportService $reportService): int
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
    private function resolvePeriod(SupportChatHourlyReportService $reportService): array
    {
        $at = trim((string) $this->option('at'));
        if ($at === '') {
            return $reportService->defaultPeriod();
        }

        $tz = (string) config('app.timezone', 'UTC');
        $start = CarbonImmutable::parse($at, $tz)->startOfHour();
        $end = $start->addHour();

        return [$start, $end];
    }
}
