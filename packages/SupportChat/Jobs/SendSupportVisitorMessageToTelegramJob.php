<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Jobs;

use App\Models\SupportMessage;
use iEXPackages\SupportChat\Services\SupportChatDeliveryDiagnostics;
use iEXPackages\SupportChat\Services\SupportChatDiagnosticsLog;
use iEXPackages\SupportChat\Services\SupportTelegramOutboundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class SendSupportVisitorMessageToTelegramJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function __construct(
        public readonly int $supportMessageId,
    ) {}

    public function handle(SupportTelegramOutboundService $telegram): void
    {
        $started = microtime(true);
        $message = SupportMessage::query()->find($this->supportMessageId);

        if ($message === null) {
            return;
        }

        if ($message->sender_type !== SupportMessage::SENDER_VISITOR) {
            return;
        }

        if ($message->telegram_outbound_message_id !== null) {
            return;
        }

        if (! $telegram->isEnabled()) {
            return;
        }

        $telegramMessageId = $telegram->sendVisitorMessageNotification($message);

        if ($telegramMessageId === null) {
            throw new \RuntimeException('Support Telegram send returned no message id');
        }

        SupportMessage::query()->whereKey($message->id)->update([
            'telegram_outbound_message_id' => $telegramMessageId,
            'telegram_delivery_failed_at' => null,
            'telegram_delivery_error' => null,
        ]);

        $message->refresh();
        $message->loadMissing('conversation');

        SupportChatDiagnosticsLog::messageSent([
            'conversation_id' => $message->support_conversation_id,
            'public_support_id' => $message->conversation?->public_support_id,
            'support_message_id' => $message->id,
            'telegram_message_id' => $telegramMessageId,
            'telegram_topic_id' => $message->conversation?->telegram_forum_topic_id,
            'elapsed_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        app(SupportChatDeliveryDiagnostics::class)->markMessageTelegramFailed(
            $this->supportMessageId,
            $exception?->getMessage(),
            'job_exhausted_retries'
        );

        $message = SupportMessage::query()->with('conversation')->find($this->supportMessageId);
        SupportChatDiagnosticsLog::telegramSendFailed([
            'conversation_id' => $message?->support_conversation_id,
            'public_support_id' => $message?->conversation?->public_support_id,
            'support_message_id' => $this->supportMessageId,
            'reason' => SupportChatDiagnosticsLog::sanitizeError($exception?->getMessage()),
            'error_code' => 'job_exhausted_retries',
        ]);
    }
}
