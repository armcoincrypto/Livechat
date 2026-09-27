<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Services\Rates\RateAuthorityDriftMonitor;
use App\Services\TelegramOperator\TelegramOperatorBotClient;
use Illuminate\Support\Facades\Log;

/**
 * Routes technical/ops alerts to OPS_TELEGRAM_CHAT_ID only.
 * Fail-closed: missing chat id or disabled flag ⇒ no Telegram send.
 * Never delivers to order-operator DMs or support/traffic groups.
 */
final class TelegramOpsAlertRouter
{
    public function __construct(
        private readonly TelegramOperatorBotClient $bot,
    ) {
    }

    public function opsChatConfigured(): bool
    {
        return $this->normalizeChatId(config('telegram_ops.chat_id')) !== null;
    }

    public function rateTelegramAlertsEnabled(): bool
    {
        return (bool) config('telegram_ops.rate_alerts_enabled', false)
            && $this->opsChatConfigured();
    }

    /**
     * @param  list<array<string, mixed>>  $alertEvents
     * @return array{attempted: int, sent: int, skipped: int, blocked_reason: ?string}
     */
    public function deliverRateAlertEvents(array $alertEvents): array
    {
        $stats = ['attempted' => 0, 'sent' => 0, 'skipped' => 0, 'blocked_reason' => null];

        if (! (bool) config('telegram_ops.rate_alerts_enabled', false)) {
            $stats['blocked_reason'] = 'RATE_TELEGRAM_ALERTS_DISABLED';
            $stats['skipped'] = count($alertEvents);

            return $stats;
        }

        $chatId = $this->normalizeChatId(config('telegram_ops.chat_id'));
        if ($chatId === null) {
            $stats['blocked_reason'] = 'OPS_TELEGRAM_CHAT_ID_MISSING';
            $stats['skipped'] = count($alertEvents);
            Log::info('telegram_ops_alert_blocked', [
                'reason' => 'OPS_TELEGRAM_CHAT_ID_MISSING',
                'owner_input_required' => 'OPS_TELEGRAM_CHAT_ID',
            ]);

            return $stats;
        }

        if ($this->isForbiddenOrderOrTrafficChat($chatId)) {
            $stats['blocked_reason'] = 'REFUSED_ORDER_OR_TRAFFIC_CHAT';
            $stats['skipped'] = count($alertEvents);
            Log::error('telegram_ops_alert_refused_cross_chat', [
                'chat_id_suffix' => substr($chatId, -4),
            ]);

            return $stats;
        }

        foreach ($alertEvents as $ev) {
            if (! $this->shouldDeliver($ev)) {
                $stats['skipped']++;

                continue;
            }
            $stats['attempted']++;
            $text = (new RateAuthorityDriftMonitor())->formatOperatorAlert($ev);
            $ok = $this->bot->sendMessageResult($chatId, $text, null);
            if ($ok['ok'] ?? false) {
                $stats['sent']++;
            } else {
                Log::warning('telegram_ops_alert_send_failed', [
                    'type' => $ev['type'] ?? null,
                    'severity' => $ev['severity'] ?? null,
                    'root_cause' => $ev['root_cause'] ?? null,
                    'chat_id_suffix' => substr($chatId, -4),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function shouldDeliver(array $event): bool
    {
        $type = (string) ($event['type'] ?? '');
        if ($type === 'RECOVERED') {
            return true;
        }
        if ($type !== 'OPEN') {
            return false;
        }

        $sev = strtoupper((string) ($event['severity'] ?? ''));
        $allowed = array_map('strtoupper', (array) config('telegram_ops.telegram_severities', ['CRITICAL', 'WARNING']));
        if (! in_array($sev, $allowed, true)) {
            return false;
        }

        // WATCH / non-impact thin-book parser noise stays JSONL-only.
        if ((bool) config('telegram_ops.suppress_watch_telegram', true) && $sev === 'WATCH') {
            return false;
        }

        $root = (string) ($event['root_cause'] ?? '');
        if (in_array($root, [
            'BESTCHANGE_POSITION_INSUFFICIENT_DEPTH',
            'BESTCHANGE_BOOK_THIN',
            'BESTCHANGE_PARSER_ERROR',
        ], true) && empty($event['public_impact']) && empty($event['order_impact'])) {
            // Non-customer-impact parser/watch-class: no Telegram spam.
            return $sev === 'CRITICAL';
        }

        return true;
    }

    private function normalizeChatId(mixed $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '' || strcasecmp($s, 'null') === 0) {
            return null;
        }

        return $s;
    }

    /**
     * Hard refuse using support traffic group or known operator DM map as ops destination.
     */
    private function isForbiddenOrderOrTrafficChat(string $chatId): bool
    {
        $support = trim((string) config('support_chat.telegram.group_id', ''));
        $hourly = trim((string) (config('support_chat.telegram.hourly_report_chat_id') ?? ''));
        if ($support !== '' && $chatId === $support) {
            return true;
        }
        if ($hourly !== '' && $chatId === $hourly) {
            return true;
        }

        try {
            $auth = app(\App\Services\TelegramOperator\TelegramOperatorAuthService::class);
            foreach ($auth->authorizedActionTelegramUserIds() as $uid) {
                if ((string) $uid === $chatId) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // fail open on auth lookup errors — still blocked by missing dedicated ops intent
        }

        return false;
    }
}
