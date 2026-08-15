<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAttachment;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use iEXPackages\SupportChat\Jobs\SendSupportAttachmentToTelegramJob;
use iEXPackages\SupportChat\Jobs\SendSupportVisitorMessageToTelegramJob;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class SupportChatAdminRetryService
{
    private const RATE_LIMIT_SECONDS = 60;

    public function __construct(
        private readonly SupportTelegramForumTopicService $forumTopics,
        private readonly SupportChatDeliveryDiagnostics $delivery,
    ) {}

    public function retryTelegramMessage(SupportMessage $message, int $adminUserId): void
    {
        $this->assertRateLimit('message', $message->id, $adminUserId);

        if ($message->sender_type !== SupportMessage::SENDER_VISITOR) {
            throw new RuntimeException('Only visitor messages can be retried to Telegram.');
        }

        if ($message->telegram_outbound_message_id !== null) {
            throw new RuntimeException('Message already delivered to Telegram.');
        }

        $this->delivery->clearMessageTelegramFailure((int) $message->id);

        SendSupportVisitorMessageToTelegramJob::dispatch((int) $message->id);

        SupportChatDiagnosticsLog::adminRetry('retry_telegram_message', [
            'support_message_id' => $message->id,
            'conversation_id' => $message->support_conversation_id,
            'admin_user_id' => $adminUserId,
        ]);
    }

    public function recreateForumTopic(SupportConversation $conversation, int $adminUserId): void
    {
        $this->assertRateLimit('topic', (int) $conversation->id, $adminUserId);

        if (! filter_var(config('support_chat.telegram.use_forum_topics', false), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('Forum topics are disabled.');
        }

        if ($conversation->telegram_forum_topic_id !== null) {
            throw new RuntimeException('Conversation already has a Telegram topic.');
        }

        $topicId = $this->forumTopics->createForumTopic($conversation);
        if ($topicId === null || $topicId < 1) {
            throw new RuntimeException('Could not create Telegram forum topic.');
        }

        SupportChatDiagnosticsLog::adminRetry('recreate_forum_topic', [
            'conversation_id' => $conversation->id,
            'public_support_id' => $conversation->public_support_id,
            'telegram_topic_id' => $topicId,
            'admin_user_id' => $adminUserId,
        ]);
    }

    public function retryAttachmentTelegram(SupportAttachment $attachment, int $adminUserId): void
    {
        $this->assertRateLimit('attachment', $attachment->id, $adminUserId);

        if ($attachment->sender_type !== SupportAttachment::SENDER_VISITOR) {
            throw new RuntimeException('Only visitor attachments can be retried.');
        }

        if ($attachment->telegram_message_id !== null) {
            throw new RuntimeException('Attachment already sent to Telegram.');
        }

        $this->delivery->clearAttachmentTelegramFailure((int) $attachment->id);

        SendSupportAttachmentToTelegramJob::dispatch((int) $attachment->id);

        SupportChatDiagnosticsLog::adminRetry('retry_attachment_telegram', [
            'attachment_id' => $attachment->id,
            'conversation_id' => $attachment->support_conversation_id,
            'mime_type' => $attachment->mime_type,
            'admin_user_id' => $adminUserId,
        ]);
    }

    private function assertRateLimit(string $kind, int $entityId, int $adminUserId): void
    {
        $key = sprintf('support_chat:admin_retry:%d:%s:%d', $adminUserId, $kind, $entityId);
        if (! Cache::add($key, 1, self::RATE_LIMIT_SECONDS)) {
            throw new RuntimeException('Retry rate limit — wait before trying again.');
        }
    }
}
