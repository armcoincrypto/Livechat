<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SupportChatHourlyReportService
{
    private const TELEGRAM_TEXT_MAX = 4096;

    public function __construct(
        private readonly SupportChatHourlyAnalyticsService $analytics,
    ) {}

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function defaultPeriod(): array
    {
        return $this->analytics->defaultPeriod();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildReportData(?CarbonImmutable $periodStart = null, ?CarbonImmutable $periodEnd = null): array
    {
        if ($periodStart === null || $periodEnd === null) {
            [$periodStart, $periodEnd] = $this->defaultPeriod();
        }

        return $this->analytics->aggregate($periodStart, $periodEnd);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function formatTelegramText(array $data): string
    {
        $lines = [
            '📊 Exswaping hourly support chat activity',
            '',
            '👥 Unique chat visitors: '.(int) ($data['unique_visitors'] ?? 0),
            '💬 New conversations: '.(int) ($data['new_conversations'] ?? 0),
            '📨 Visitor messages: '.(int) ($data['visitor_messages'] ?? 0),
            '',
            '🌍 Countries:',
        ];

        $countries = $data['countries'] ?? [];
        if ($countries === []) {
            $lines[] = '— none —';
        } else {
            foreach ($countries as $row) {
                $lines[] = ($row['label'] ?? 'Unknown').' — '.(int) ($row['count'] ?? 0);
            }
        }

        $lines[] = '';
        $lines[] = '🗣 Languages:';

        $languages = $data['languages'] ?? [];
        if ($languages === []) {
            $lines[] = '— none —';
        } else {
            foreach ($languages as $row) {
                $lines[] = ($row['code'] ?? '—').' — '.(int) ($row['count'] ?? 0);
            }
        }

        $lines[] = '';
        $lines[] = '🔥 Top pages:';

        $pages = $data['top_pages'] ?? [];
        if ($pages === []) {
            $lines[] = '— none —';
        } else {
            $rank = 1;
            foreach ($pages as $row) {
                $lines[] = $rank.'. '.($row['path'] ?? '—').' — '.(int) ($row['count'] ?? 0);
                $rank++;
            }
        }

        $lines[] = '';
        $lines[] = 'Period: '.($data['period_label'] ?? '—').' ('.($data['timezone'] ?? 'UTC').')';
        $lines[] = 'Scope: Support Chat only (not full-site visitor analytics)';

        $text = implode("\n", $lines);

        if (mb_strlen($text, 'UTF-8') > self::TELEGRAM_TEXT_MAX) {
            $text = mb_substr($text, 0, self::TELEGRAM_TEXT_MAX - 1, 'UTF-8').'…';
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendTelegramReport(array $data): bool
    {
        if (! filter_var(config('support_chat.telegram.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            Log::warning('support-chat hourly-report: telegram disabled');

            return false;
        }

        $token = trim((string) config('support_chat.telegram.bot_token', ''));
        $chatId = trim((string) config('support_chat.telegram.hourly_report.chat_id', ''));

        if ($token === '' || $chatId === '') {
            Log::warning('support-chat hourly-report: missing bot token or chat id');

            return false;
        }

        $text = $this->formatTelegramText($data);

        try {
            $response = Http::timeout(25)
                ->acceptJson()
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ]);
        } catch (Throwable $e) {
            Log::warning('support-chat hourly-report: transport_error', [
                'exception' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('support-chat hourly-report: http_error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        $payload = $response->json();
        if (! is_array($payload) || empty($payload['ok'])) {
            Log::warning('support-chat hourly-report: api_rejected', [
                'response' => $payload,
            ]);

            return false;
        }

        return true;
    }
}
