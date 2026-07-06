<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAttachment;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use iEXPackages\SupportChat\Contracts\SupportChatServiceInterface;
use iEXPackages\SupportChat\Services\SupportChatDiagnosticsLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes Telegram Bot API updates for the support group: operator replies
 * threaded to the bot's visitor-notification messages are stored as operator support_messages.
 */
final class SupportTelegramInboundService
{
    public function __construct(
        private readonly SupportChatServiceInterface $supportChat,
        private readonly SupportTelegramOperatorCommandService $operatorCommands,
        private readonly SupportTelegramInboundMediaService $inboundMedia,
        private readonly SupportAttachmentStorageService $attachmentStorage,
    ) {}

    public function processWebhookUpdate(array $update): void
    {
        if (isset($update['callback_query'])) {
            return;
        }

        $message = $update['message'] ?? null;
        if (! is_array($message)) {
            return;
        }

        if (! $this->isMessageFromConfiguredGroup($message)) {
            Log::debug('support-chat telegram: inbound ignored wrong_chat', [
                'chat_id' => data_get($message, 'chat.id'),
            ]);

            return;
        }

        $from = $message['from'] ?? null;
        if (! is_array($from) || ! empty($from['is_bot'])) {
            return;
        }

        $telegramMessageId = isset($message['message_id']) ? (int) $message['message_id'] : 0;
        if ($telegramMessageId < 1) {
            return;
        }

        if ($this->operatorMessageAlreadyRecorded($telegramMessageId)) {
            return;
        }

        $rawBodyForCommands = $this->extractTextBody($message);
        if (
            $rawBodyForCommands !== null
            && str_starts_with(trim($rawBodyForCommands), '/')
            && filter_var(config('support_chat.telegram.operator_commands_enabled', false), FILTER_VALIDATE_BOOLEAN)
        ) {
            if ($this->operatorCommands->tryHandle($message, $from, $rawBodyForCommands)) {
                return;
            }
        }

        if ($this->tryProcessForumTopicOperatorMessage($message, $telegramMessageId, $from)) {
            return;
        }

        $replyTo = $message['reply_to_message'] ?? null;
        if (! is_array($replyTo)) {
            Log::debug('support-chat telegram: inbound ignored no_reply_thread');

            return;
        }

        $replyToFrom = $replyTo['from'] ?? null;
        if (! is_array($replyToFrom) || empty($replyToFrom['is_bot'])) {
            Log::debug('support-chat telegram: inbound ignored reply_not_to_bot');

            return;
        }

        $replyToMessageId = isset($replyTo['message_id']) ? (int) $replyTo['message_id'] : 0;
        if ($replyToMessageId < 1) {
            return;
        }

        $visitorRow = SupportMessage::query()
            ->where('sender_type', SupportMessage::SENDER_VISITOR)
            ->where('telegram_outbound_message_id', $replyToMessageId)
            ->first();

        if ($visitorRow === null) {
            Log::debug('support-chat telegram: inbound ignored no_mapped_visitor_message', [
                'reply_to_message_id' => $replyToMessageId,
            ]);

            return;
        }

        /** @var SupportConversation|null $conversation */
        $conversation = SupportConversation::query()->find($visitorRow->support_conversation_id);

        if ($conversation === null || ! $conversation->isOpen()) {
            Log::info('support-chat telegram: inbound ignored conversation_closed_or_missing', [
                'support_conversation_id' => $visitorRow->support_conversation_id,
                'conversation_uuid' => $conversation?->uuid,
                'public_support_id' => $conversation?->public_support_id,
            ]);

            return;
        }

        $rawBody = $this->extractTextBody($message);
        if ($rawBody === null) {
            Log::debug('support-chat telegram: inbound ignored no_text_caption', [
                'telegram_message_id' => $telegramMessageId,
            ]);

            return;
        }

        $maxLength = (int) config('support_chat.message_max_length', 8000);
        $body = $this->supportChat->sanitizeMessageBody($rawBody, $maxLength);
        if ($body === '') {
            Log::debug('support-chat telegram: inbound ignored empty_body_after_sanitize', [
                'telegram_message_id' => $telegramMessageId,
            ]);

            return;
        }

        try {
            $this->persistOperatorMessage(
                $conversation,
                $visitorRow,
                $telegramMessageId,
                $replyToMessageId,
                $body,
                $from,
                null,
                null,
            );
        } catch (QueryException $e) {
            if ($this->isUniqueTelegramInboundViolation($e)) {
                return;
            }
            throw $e;
        }
    }

    /**
     * Forum-topic path: match operator message to conversation by message_thread_id when enabled.
     */
    private function tryProcessForumTopicOperatorMessage(array $message, int $telegramMessageId, array $from): bool
    {
        if (! filter_var(config('support_chat.telegram.use_forum_topics', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $threadRaw = data_get($message, 'message_thread_id');
        if ($threadRaw === null || $threadRaw === '') {
            return false;
        }

        $threadId = (int) $threadRaw;
        if ($threadId < 1) {
            return false;
        }

        /** @var SupportConversation|null $conversation */
        $conversation = SupportConversation::query()
            ->where('telegram_forum_topic_id', $threadId)
            ->where('status', '!=', SupportConversation::STATUS_CLOSED)
            ->first();

        if ($conversation === null || ! $conversation->isOpen()) {
            return false;
        }

        $visitorAnchor = $this->resolveVisitorAnchorForForumTopic($message, $conversation);
        if ($visitorAnchor === null) {
            Log::debug('support-chat telegram: inbound forum_topic_no_visitor_anchor', [
                'conversation_uuid' => $conversation->uuid,
                'public_support_id' => $conversation->public_support_id,
                'message_thread_id' => $threadId,
            ]);

            return false;
        }

        $hasMedia = $this->hasInboundMedia($message);
        $mediaEnabled = filter_var(config('support_chat.telegram.inbound_attachments_enabled', false), FILTER_VALIDATE_BOOLEAN);

        // Text-only / non-media: never invoke attachment pipeline (normal text must not depend on media code).
        if (! $hasMedia) {
            $this->logInboundRouteDecision($message, $threadId, $mediaEnabled, 'forum_text_only_shape');

            return $this->tryPersistForumTopicOperatorText(
                $conversation,
                $visitorAnchor,
                $message,
                $telegramMessageId,
                $from,
                $threadId,
            );
        }

        // Media-shaped payload but feature disabled: persist caption/text only; do not call media service.
        if (! $mediaEnabled) {
            $this->logInboundRouteDecision($message, $threadId, $mediaEnabled, 'forum_text_media_disabled');

            return $this->tryPersistForumTopicOperatorText(
                $conversation,
                $visitorAnchor,
                $message,
                $telegramMessageId,
                $from,
                $threadId,
            );
        }

        $this->logInboundRouteDecision($message, $threadId, $mediaEnabled, 'forum_media_ingest_attempt');

        $ingest = $this->inboundMedia->tryIngestForumTopicOperatorMedia(
            $conversation,
            $visitorAnchor,
            $message,
            $telegramMessageId,
            $from,
        );

        if ($ingest !== null && ($ingest[0] ?? null) === '__skip__') {
            $skipReason = isset($ingest[1]) && is_string($ingest[1]) ? $ingest[1] : '';
            if ($skipReason === 'not_admin') {
                $this->logInboundRouteDecision($message, $threadId, $mediaEnabled, 'forum_media_skip_not_admin');

                return true;
            }
            $fallbackBody = $this->extractTextBody($message);
            if ($fallbackBody === null || trim($fallbackBody) === '') {
                $this->logInboundRouteDecision($message, $threadId, $mediaEnabled, 'forum_media_skip_no_fallback_text');

                return true;
            }
            $this->logInboundRouteDecision($message, $threadId, $mediaEnabled, 'forum_text_after_media_skip');

            return $this->tryPersistForumTopicOperatorText(
                $conversation,
                $visitorAnchor,
                $message,
                $telegramMessageId,
                $from,
                $threadId,
            );
        }

        if ($ingest !== null && $ingest[0] instanceof SupportAttachment) {
            $replyToTelegramMessageId = null;
            $replyTo = $message['reply_to_message'] ?? null;
            if (is_array($replyTo) && isset($replyTo['message_id'])) {
                $rid = (int) $replyTo['message_id'];
                $replyToTelegramMessageId = $rid > 0 ? $rid : null;
            }

            try {
                $this->persistOperatorMessage(
                    $conversation,
                    $visitorAnchor,
                    $telegramMessageId,
                    $replyToTelegramMessageId,
                    $ingest[1],
                    $from,
                    $threadId,
                    $ingest[0],
                );
            } catch (QueryException $e) {
                if ($this->isUniqueTelegramInboundViolation($e)) {
                    $this->logInboundRouteDecision($message, $threadId, $mediaEnabled, 'forum_media_duplicate_ignored');

                    return true;
                }
                throw $e;
            }
            $this->logInboundRouteDecision($message, $threadId, $mediaEnabled, 'forum_media_attachment_stored');

            return true;
        }

        // Media shape + flag on but ingest returned null (e.g. defence-in-depth in media spec): caption/text only.
        $this->logInboundRouteDecision($message, $threadId, $mediaEnabled, 'forum_text_after_media_null');

        return $this->tryPersistForumTopicOperatorText(
            $conversation,
            $visitorAnchor,
            $message,
            $telegramMessageId,
            $from,
            $threadId,
        );
    }

    /**
     * Telegram inbound attachment handling only applies when the payload carries a real photo[] or document.
     */
    private function hasInboundMedia(array $message): bool
    {
        if (isset($message['photo']) && is_array($message['photo']) && $message['photo'] !== []) {
            foreach ($message['photo'] as $p) {
                if (is_array($p) && isset($p['file_id']) && is_string($p['file_id']) && $p['file_id'] !== '') {
                    return true;
                }
            }
        }

        if (isset($message['document']) && is_array($message['document'])) {
            $fid = isset($message['document']['file_id']) && is_string($message['document']['file_id'])
                ? $message['document']['file_id']
                : null;

            return $fid !== null && $fid !== '';
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function logInboundRouteDecision(array $message, int $threadId, bool $mediaEnabled, string $decision): void
    {
        $hasText = isset($message['text']) && is_string($message['text']) && trim($message['text']) !== '';
        $hasPhoto = false;
        if (isset($message['photo']) && is_array($message['photo']) && $message['photo'] !== []) {
            foreach ($message['photo'] as $p) {
                if (is_array($p) && isset($p['file_id']) && is_string($p['file_id']) && $p['file_id'] !== '') {
                    $hasPhoto = true;
                    break;
                }
            }
        }
        $hasDocument = isset($message['document']) && is_array($message['document'])
            && isset($message['document']['file_id'])
            && is_string($message['document']['file_id'])
            && $message['document']['file_id'] !== '';

        Log::debug('support_chat_inbound_route_decision', [
            'has_text' => $hasText,
            'has_photo' => $hasPhoto,
            'has_document' => $hasDocument,
            'media_enabled' => $mediaEnabled,
            'thread_id' => $threadId,
            'decision' => $decision,
        ]);
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $from
     */
    private function tryPersistForumTopicOperatorText(
        SupportConversation $conversation,
        SupportMessage $visitorAnchor,
        array $message,
        int $telegramMessageId,
        array $from,
        int $threadId,
    ): bool {
        $rawBody = $this->extractTextBody($message);
        if ($rawBody === null) {
            Log::debug('support-chat telegram: inbound forum_topic_no_text_caption', [
                'telegram_message_id' => $telegramMessageId,
            ]);

            return false;
        }

        $maxLength = (int) config('support_chat.message_max_length', 8000);
        $body = $this->supportChat->sanitizeMessageBody($rawBody, $maxLength);
        if ($body === '') {
            Log::debug('support-chat telegram: inbound forum_topic_empty_body_after_sanitize', [
                'telegram_message_id' => $telegramMessageId,
            ]);

            return false;
        }

        $replyToTelegramMessageId = null;
        $replyTo = $message['reply_to_message'] ?? null;
        if (is_array($replyTo) && isset($replyTo['message_id'])) {
            $rid = (int) $replyTo['message_id'];
            $replyToTelegramMessageId = $rid > 0 ? $rid : null;
        }

        try {
            $this->persistOperatorMessage(
                $conversation,
                $visitorAnchor,
                $telegramMessageId,
                $replyToTelegramMessageId,
                $body,
                $from,
                $threadId,
                null,
            );
        } catch (QueryException $e) {
            if ($this->isUniqueTelegramInboundViolation($e)) {
                return true;
            }
            throw $e;
        }

        $this->logInboundRouteDecision(
            $message,
            $threadId,
            filter_var(config('support_chat.telegram.inbound_attachments_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'forum_text_persisted',
        );

        return true;
    }

    private function resolveVisitorAnchorForForumTopic(array $message, SupportConversation $conversation): ?SupportMessage
    {
        $replyTo = $message['reply_to_message'] ?? null;
        if (is_array($replyTo)) {
            $replyToFrom = $replyTo['from'] ?? null;
            if (is_array($replyToFrom) && ! empty($replyToFrom['is_bot'])) {
                $replyToMessageId = isset($replyTo['message_id']) ? (int) $replyTo['message_id'] : 0;
                if ($replyToMessageId > 0) {
                    $row = SupportMessage::query()
                        ->where('support_conversation_id', $conversation->id)
                        ->where('sender_type', SupportMessage::SENDER_VISITOR)
                        ->where('telegram_outbound_message_id', $replyToMessageId)
                        ->first();
                    if ($row !== null) {
                        return $row;
                    }
                }
            }
        }

        return SupportMessage::query()
            ->where('support_conversation_id', $conversation->id)
            ->where('sender_type', SupportMessage::SENDER_VISITOR)
            ->orderByDesc('id')
            ->first();
    }

    private function isMessageFromConfiguredGroup(array $message): bool
    {
        $chatId = data_get($message, 'chat.id');
        $expected = config('support_chat.telegram.group_id');

        return $this->telegramChatIdsEqual($chatId, $expected);
    }

    /**
     * @param  mixed  $a  Telegram chat.id from payload
     * @param  mixed  $b  Configured SUPPORT_TELEGRAM_GROUP_ID
     */
    private function telegramChatIdsEqual(mixed $a, mixed $b): bool
    {
        $na = $this->normalizeTelegramChatId($a);
        $nb = $this->normalizeTelegramChatId($b);

        return $na !== '' && $nb !== '' && $na === $nb;
    }

    private function normalizeTelegramChatId(mixed $id): string
    {
        if ($id === null) {
            return '';
        }
        if (is_int($id) || is_float($id)) {
            $id = (string) (int) $id;
        }

        return trim((string) $id);
    }

    private function operatorMessageAlreadyRecorded(int $telegramMessageId): bool
    {
        return SupportMessage::query()
            ->where('sender_type', SupportMessage::SENDER_OPERATOR)
            ->where('telegram_inbound_message_id', $telegramMessageId)
            ->exists();
    }

    /**
     * @return string|null Text or caption; null if unsupported message type
     */
    private function extractTextBody(array $message): ?string
    {
        if (isset($message['text']) && is_string($message['text'])) {
            return $message['text'];
        }
        if (isset($message['caption']) && is_string($message['caption'])) {
            return $message['caption'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $from
     */
    private function persistOperatorMessage(
        SupportConversation $conversation,
        SupportMessage $visitorAnchor,
        int $telegramMessageId,
        ?int $replyToTelegramMessageId,
        string $body,
        array $from,
        ?int $messageThreadId,
        ?SupportAttachment $pendingAttachment = null,
    ): void {
        $savedOperatorMessage = null;

        DB::transaction(function () use ($conversation, $visitorAnchor, $telegramMessageId, $replyToTelegramMessageId, $body, $from, $messageThreadId, $pendingAttachment, &$savedOperatorMessage): void {
            $conversation->refresh();
            if (! $conversation->isOpen()) {
                if ($pendingAttachment !== null) {
                    $this->attachmentStorage->discardOperatorInboundAttachment($pendingAttachment);
                }
                Log::info('support-chat telegram: inbound skip_persist conversation_closed_in_tx', [
                    'conversation_uuid' => $conversation->uuid,
                    'public_support_id' => $conversation->public_support_id,
                ]);

                return;
            }

            if ($this->operatorMessageAlreadyRecorded($telegramMessageId)) {
                if ($pendingAttachment !== null) {
                    $this->attachmentStorage->discardOperatorInboundAttachment($pendingAttachment);
                }

                return;
            }

            $operatorUserId = isset($from['id']) ? (int) $from['id'] : null;
            $operatorUsername = isset($from['username']) && is_string($from['username']) ? $from['username'] : null;
            $displayName = $this->buildTelegramOperatorDisplayName($from);

            $teleMeta = [
                'from_user_id' => $operatorUserId,
                'from_username' => $operatorUsername,
                'from_first_name' => isset($from['first_name']) ? (string) $from['first_name'] : null,
                'from_last_name' => isset($from['last_name']) ? (string) $from['last_name'] : null,
                'reply_anchor_support_message_id' => $visitorAnchor->id,
            ];
            if ($messageThreadId !== null && $messageThreadId > 0) {
                $teleMeta['message_thread_id'] = $messageThreadId;
            }

            $meta = [
                'telegram' => $teleMeta,
            ];

            $operatorMessage = new SupportMessage;
            $operatorMessage->support_conversation_id = (int) $conversation->id;
            $operatorMessage->sender_type = SupportMessage::SENDER_OPERATOR;
            $operatorMessage->body = $body;
            $operatorMessage->metadata = $meta;
            $operatorMessage->telegram_inbound_message_id = $telegramMessageId;
            $operatorMessage->telegram_reply_to_message_id = $replyToTelegramMessageId;
            $operatorMessage->save();

            if ($pendingAttachment !== null) {
                SupportAttachment::query()->whereKey($pendingAttachment->id)->update([
                    'support_message_id' => (int) $operatorMessage->id,
                ]);
            }

            $now = now();
            $conversation->forceFill([
                'last_message_at' => $now,
                'last_message_sender_type' => SupportMessage::SENDER_OPERATOR,
                'last_operator_message_at' => $now,
                'status' => SupportConversation::STATUS_WAITING_VISITOR,
                'last_operator_telegram_user_id' => $operatorUserId,
                'last_operator_telegram_username' => $operatorUsername !== null ? mb_substr($operatorUsername, 0, 64) : null,
                'last_operator_display_name' => $displayName !== null ? mb_substr($displayName, 0, 191) : null,
            ])->save();

            SupportChatDiagnosticsLog::operatorReply([
                'conversation_id' => $conversation->id,
                'public_support_id' => $conversation->public_support_id,
                'support_message_id' => $operatorMessage->id,
                'telegram_message_id' => $telegramMessageId,
                'telegram_topic_id' => $messageThreadId > 0 ? $messageThreadId : null,
            ]);

            $savedOperatorMessage = $operatorMessage;
        });
    }

    /**
     * @param  array<string, mixed>  $from
     */
    private function buildTelegramOperatorDisplayName(array $from): ?string
    {
        $first = isset($from['first_name']) && is_string($from['first_name']) ? trim($from['first_name']) : '';
        $last = isset($from['last_name']) && is_string($from['last_name']) ? trim($from['last_name']) : '';

        $full = trim($first.' '.$last);
        if ($full !== '') {
            return $full;
        }

        if (isset($from['username']) && is_string($from['username']) && trim($from['username']) !== '') {
            return '@'.ltrim(trim($from['username']), '@');
        }

        return null;
    }

    private function isUniqueTelegramInboundViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;

        return $sqlState === '23000'
            || str_contains(strtolower($e->getMessage()), 'duplicate');
    }
}
