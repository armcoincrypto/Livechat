<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAttachment;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SupportChatHealthService
{
    public function __construct(
        private readonly SupportChatSchemaReadinessService $schemaReadiness,
        private readonly SupportTelegramDeliveryStatusService $telegramDeliveryStatus,
    ) {}

    public function snapshot(): array
    {
        $since24h = Carbon::now()->subDay();
        $schema = $this->schemaReadiness->assess();
        $diagnosticsAvailable = $schema['diagnostics_available'];

        $telegramEnabled = filter_var(config('support_chat.telegram.enabled', false), FILTER_VALIDATE_BOOLEAN);
        $forumTopics = $telegramEnabled && filter_var(config('support_chat.telegram.use_forum_topics', false), FILTER_VALIDATE_BOOLEAN);

        $waitingOp = [SupportConversation::STATUS_WAITING_OPERATOR, SupportConversation::STATUS_OPEN];

        $withoutTopic = 0;
        if ($forumTopics && Schema::hasColumn('support_conversations', 'telegram_forum_topic_id')) {
            $withoutTopic = (int) SupportConversation::query()
                ->whereIn('status', $waitingOp)
                ->whereNull('telegram_forum_topic_id')
                ->count();
        }

        $oldestUnanswered = SupportConversation::query()
            ->whereIn('status', $waitingOp)
            ->orderByRaw('COALESCE(last_visitor_message_at, last_message_at, created_at) ASC')
            ->value('last_visitor_message_at');

        $lastOperatorReply = SupportMessage::query()
            ->where('sender_type', SupportMessage::SENDER_OPERATOR)
            ->max('created_at');

        $base = [
            'generated_at' => Carbon::now()->toIso8601String(),
            'status' => $diagnosticsAvailable ? 'ok' : 'degraded',
            'support_chat_schema_ready' => $schema['support_chat_schema_ready'],
            'diagnostics_available' => $schema['diagnostics_available'],
            'missing_columns' => $schema['missing_columns'],
            'missing_indexes' => $schema['missing_indexes'],
            'support_enabled' => filter_var(config('support_chat.enabled', false), FILTER_VALIDATE_BOOLEAN),
            'telegram_enabled' => $telegramEnabled,
            'forum_topics_enabled' => $forumTopics,
            'attachment_support_enabled' => filter_var(config('support_chat.attachments.enabled', false), FILTER_VALIDATE_BOOLEAN),
            'inbound_attachments_enabled' => filter_var(config('support_chat.telegram.inbound_attachments_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'message_states_enabled' => filter_var(config('support_chat.message_states_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'queue_connection' => (string) config('queue.default', 'sync'),
            'recent_failed_jobs_count' => $diagnosticsAvailable
                ? $this->countFailedSupportJobsSince($since24h)
                : null,
            'last_webhook_received_at' => SupportChatMetrics::lastWebhookReceivedAt(),
            'last_operator_reply_at' => $lastOperatorReply ? Carbon::parse($lastOperatorReply)->toIso8601String() : null,
            'pending_support_conversations' => (int) SupportConversation::query()->whereIn('status', $waitingOp)->count(),
            'conversations_without_telegram_topic_id' => $withoutTopic,
            'failed_attachment_upload_last_24h' => $this->countAttachmentUploadFailuresSince($since24h),
            'spam_rejects_last_24h' => SupportChatMetrics::spamRejectsLast24h(),
            'avg_operator_reply_delay_seconds' => $this->averageOperatorReplyDelaySeconds($since24h),
            'oldest_unanswered_visitor_message_at' => $oldestUnanswered
                ? Carbon::parse($oldestUnanswered)->toIso8601String()
                : null,
        ];

        if (! $diagnosticsAvailable) {
            return array_merge($base, [
                'reason' => 'schema_mismatch',
                'failed_telegram_message_delivery_last_24h' => null,
                'failed_attachment_telegram_delivery_last_24h' => null,
                'telegram_delivery_summary' => null,
            ]);
        }

        return array_merge($base, [
            'failed_telegram_message_delivery_last_24h' => $this->countMessageTelegramFailuresSince($since24h),
            'failed_attachment_telegram_delivery_last_24h' => $this->countAttachmentTelegramFailuresSince($since24h),
            'telegram_delivery_summary' => $this->telegramDeliveryStatus->summary(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function diagnosticRows(int $limit = 50): array
    {
        $waitingOp = [SupportConversation::STATUS_WAITING_OPERATOR, SupportConversation::STATUS_OPEN];
        $rows = [];

        $open = SupportConversation::query()
            ->whereIn('status', $waitingOp)
            ->orderByDesc('last_message_at')
            ->limit($limit)
            ->get(['id', 'uuid', 'public_support_id', 'status', 'visitor_email', 'telegram_forum_topic_id', 'last_message_at', 'last_visitor_message_at']);

        foreach ($open as $c) {
            $rows[] = [
                'kind' => 'open_conversation',
                'conversation_id' => $c->id,
                'public_support_id' => $c->public_support_id,
                'uuid' => $c->uuid,
                'status' => $c->status,
                'telegram_topic_id' => $c->telegram_forum_topic_id,
                'last_message_at' => $c->last_message_at?->toIso8601String(),
            ];
        }

        if (! $this->schemaReadiness->isDiagnosticsAvailable()) {
            return $rows;
        }

        $failedMsgs = SupportMessage::query()
            ->whereNotNull('telegram_delivery_failed_at')
            ->where('sender_type', SupportMessage::SENDER_VISITOR)
            ->orderByDesc('telegram_delivery_failed_at')
            ->limit(25)
            ->get(['id', 'support_conversation_id', 'telegram_delivery_failed_at', 'telegram_delivery_error']);

        foreach ($failedMsgs as $m) {
            $rows[] = [
                'kind' => 'telegram_message_failed',
                'support_message_id' => $m->id,
                'conversation_id' => $m->support_conversation_id,
                'failed_at' => $m->telegram_delivery_failed_at?->toIso8601String(),
                'error' => $m->telegram_delivery_error,
            ];
        }

        $failedAtt = SupportAttachment::query()
            ->whereNotNull('telegram_delivery_failed_at')
            ->orderByDesc('telegram_delivery_failed_at')
            ->limit(25)
            ->get(['id', 'support_conversation_id', 'mime_type', 'telegram_delivery_failed_at', 'telegram_delivery_error']);

        foreach ($failedAtt as $a) {
            $rows[] = [
                'kind' => 'telegram_attachment_failed',
                'attachment_id' => $a->id,
                'conversation_id' => $a->support_conversation_id,
                'mime_type' => $a->mime_type,
                'failed_at' => $a->telegram_delivery_failed_at?->toIso8601String(),
                'error' => $a->telegram_delivery_error,
            ];
        }

        return $rows;
    }

    private function countFailedSupportJobsSince(Carbon $since): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        try {
            return (int) DB::table('failed_jobs')
                ->where('failed_at', '>=', $since)
                ->where(function ($q): void {
                    $q->where('payload', 'like', '%SendSupportVisitorMessageToTelegramJob%')
                        ->orWhere('payload', 'like', '%SendSupportAttachmentToTelegramJob%')
                        ->orWhere('payload', 'like', '%SupportChat%');
                })
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countMessageTelegramFailuresSince(Carbon $since): int
    {
        return (int) SupportMessage::query()
            ->whereNotNull('telegram_delivery_failed_at')
            ->where('telegram_delivery_failed_at', '>=', $since)
            ->count();
    }

    private function countAttachmentTelegramFailuresSince(Carbon $since): int
    {
        return (int) SupportAttachment::query()
            ->whereNotNull('telegram_delivery_failed_at')
            ->where('telegram_delivery_failed_at', '>=', $since)
            ->count();
    }

    private function countAttachmentUploadFailuresSince(Carbon $since): int
    {
        $sum = 0;
        for ($i = 0; $i < 2; $i++) {
            $day = $since->copy()->addDays($i)->format('Y-m-d');
            $sum += (int) \Illuminate\Support\Facades\Cache::get('support_chat:metrics:attachment_upload_failed:'.$day, 0);
        }

        return $sum;
    }

    private function averageOperatorReplyDelaySeconds(Carbon $since): ?int
    {
        if (! Schema::hasColumn('support_conversations', 'last_visitor_message_at')) {
            return null;
        }

        $avg = SupportConversation::query()
            ->whereNotNull('last_visitor_message_at')
            ->whereNotNull('last_operator_message_at')
            ->where('last_operator_message_at', '>=', $since)
            ->whereColumn('last_operator_message_at', '>=', 'last_visitor_message_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, last_visitor_message_at, last_operator_message_at)) as avg_sec')
            ->value('avg_sec');

        if ($avg === null) {
            return null;
        }

        return (int) round((float) $avg);
    }
}
