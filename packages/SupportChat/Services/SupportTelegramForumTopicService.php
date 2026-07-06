<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportConversation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Optional Telegram forum topic (one per support conversation).
 * Disabled unless support_chat.telegram.use_forum_topics is true.
 */
final class SupportTelegramForumTopicService
{
    private const TOPIC_NAME_MAX_UTF8 = 128;

    /**
     * Create a forum topic for the conversation when feature is enabled, or return existing id.
     * On failure: log warning, return null (caller falls back to flat group sendMessage).
     */
    public function createForumTopic(SupportConversation $conversation): ?int
    {
        if (! filter_var(config('support_chat.telegram.use_forum_topics', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        if (! filter_var(config('support_chat.telegram.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $token = trim((string) config('support_chat.telegram.bot_token', ''));
        $chatId = $this->normalizeChatId(config('support_chat.telegram.group_id'));
        if ($token === '' || $chatId === '') {
            return null;
        }

        try {
            return DB::transaction(function () use ($conversation, $token, $chatId): ?int {
                /** @var SupportConversation|null $locked */
                $locked = SupportConversation::query()
                    ->whereKey($conversation->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked === null) {
                    return null;
                }

                if ($locked->telegram_forum_topic_id !== null) {
                    return (int) $locked->telegram_forum_topic_id;
                }

                $topicName = $this->buildTopicName($locked);
                $response = Http::timeout(25)
                    ->acceptJson()
                    ->asJson()
                    ->post("https://api.telegram.org/bot{$token}/createForumTopic", [
                        'chat_id' => $chatId,
                        'name' => $topicName,
                    ]);

                if (! $response->successful()) {
                    SupportChatDiagnosticsLog::topicCreateFailed([
                        'conversation_id' => $locked->id,
                        'public_support_id' => $locked->public_support_id,
                        'error_code' => 'http_error',
                        'reason' => 'HTTP '.$response->status(),
                    ]);

                    return null;
                }

                $data = $response->json();
                if (! is_array($data) || empty($data['ok'])) {
                    SupportChatDiagnosticsLog::topicCreateFailed([
                        'conversation_id' => $locked->id,
                        'public_support_id' => $locked->public_support_id,
                        'error_code' => 'api_rejected',
                        'reason' => is_array($data) ? (string) ($data['description'] ?? 'api_rejected') : 'api_rejected',
                    ]);

                    return null;
                }

                $result = $data['result'] ?? null;
                $threadId = is_array($result) && isset($result['message_thread_id'])
                    ? (int) $result['message_thread_id']
                    : 0;

                if ($threadId < 1) {
                    SupportChatDiagnosticsLog::topicCreateFailed([
                        'conversation_id' => $locked->id,
                        'public_support_id' => $locked->public_support_id,
                        'error_code' => 'missing_message_thread_id',
                    ]);

                    return null;
                }

                $now = now();
                SupportConversation::query()->whereKey($locked->id)->update([
                    'telegram_forum_topic_id' => $threadId,
                    'telegram_forum_topic_created_at' => $now,
                ]);

                Log::info('support-chat telegram: forum_topic_created', [
                    'support_conversation_id' => $locked->id,
                    'public_support_id' => $locked->public_support_id,
                    'telegram_forum_topic_id' => $threadId,
                ]);

                return $threadId;
            });
        } catch (Throwable $e) {
            SupportChatDiagnosticsLog::topicCreateFailed([
                'conversation_id' => $conversation->id,
                'public_support_id' => $conversation->public_support_id,
                'error_code' => 'exception',
                'reason' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);

            return null;
        }
    }

    /**
     * Close the Telegram forum topic (Telegram /close when auto_close_topics is enabled).
     * On success sets telegram_topic_closed_at. On failure: Log::warning, returns false.
     */
    public function closeForumTopic(SupportConversation $conversation): bool
    {
        if (! filter_var(config('support_chat.telegram.auto_close_topics', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (! filter_var(config('support_chat.telegram.use_forum_topics', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (! filter_var(config('support_chat.telegram.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $token = trim((string) config('support_chat.telegram.bot_token', ''));
        $chatId = $this->normalizeChatId(config('support_chat.telegram.group_id'));
        if ($token === '' || $chatId === '') {
            return false;
        }

        $conversation->refresh();
        $threadId = $conversation->telegram_forum_topic_id;
        if ($threadId === null || (int) $threadId < 1) {
            return false;
        }

        if (! $this->callTelegramForumTopicAction($token, $chatId, (int) $threadId, 'closeForumTopic')) {
            return false;
        }

        SupportConversation::query()->whereKey($conversation->id)->update([
            'telegram_topic_closed_at' => now(),
        ]);

        Log::info('support-chat telegram: forum_topic_closed', [
            'support_conversation_id' => $conversation->id,
            'public_support_id' => $conversation->public_support_id,
            'message_thread_id' => (int) $threadId,
        ]);

        return true;
    }

    /**
     * Reopen the Telegram forum topic (Telegram /reopen when auto_close_topics is enabled).
     * On success clears telegram_topic_closed_at and sets telegram_topic_reopened_at.
     */
    public function reopenForumTopic(SupportConversation $conversation): bool
    {
        if (! filter_var(config('support_chat.telegram.auto_close_topics', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (! filter_var(config('support_chat.telegram.use_forum_topics', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (! filter_var(config('support_chat.telegram.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $token = trim((string) config('support_chat.telegram.bot_token', ''));
        $chatId = $this->normalizeChatId(config('support_chat.telegram.group_id'));
        if ($token === '' || $chatId === '') {
            return false;
        }

        $conversation->refresh();
        $threadId = $conversation->telegram_forum_topic_id;
        if ($threadId === null || (int) $threadId < 1) {
            return false;
        }

        if (! $this->callTelegramForumTopicAction($token, $chatId, (int) $threadId, 'reopenForumTopic')) {
            return false;
        }

        $now = now();
        SupportConversation::query()->whereKey($conversation->id)->update([
            'telegram_topic_closed_at' => null,
            'telegram_topic_reopened_at' => $now,
        ]);

        Log::info('support-chat telegram: forum_topic_reopened', [
            'support_conversation_id' => $conversation->id,
            'public_support_id' => $conversation->public_support_id,
            'message_thread_id' => (int) $threadId,
        ]);

        return true;
    }

    private function callTelegramForumTopicAction(string $token, string $chatId, int $messageThreadId, string $method): bool
    {
        try {
            $response = Http::timeout(25)
                ->acceptJson()
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/{$method}", [
                    'chat_id' => $chatId,
                    'message_thread_id' => $messageThreadId,
                ]);
        } catch (Throwable $e) {
            Log::warning('support-chat telegram: forum_topic_action transport_error', [
                'method' => $method,
                'message_thread_id' => $messageThreadId,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('support-chat telegram: forum_topic_action http_error', [
                'method' => $method,
                'message_thread_id' => $messageThreadId,
                'status' => $response->status(),
            ]);

            return false;
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['ok'])) {
            Log::warning('support-chat telegram: forum_topic_action api_rejected', [
                'method' => $method,
                'message_thread_id' => $messageThreadId,
                'description' => is_array($data) ? ($data['description'] ?? null) : null,
            ]);

            return false;
        }

        return true;
    }

    private function buildTopicName(SupportConversation $conversation): string
    {
        $publicId = $conversation->public_support_id !== null && $conversation->public_support_id !== ''
            ? $conversation->public_support_id
            : ('#'.$conversation->id);

        $name = trim($conversation->visitor_name) !== '' ? trim($conversation->visitor_name) : 'Guest';
        $name = preg_replace('/[\r\n\x00]+/u', ' ', $name) ?? $name;
        $name = trim($name);

        $raw = $publicId.' '.$name;
        if (mb_strlen($raw, 'UTF-8') <= self::TOPIC_NAME_MAX_UTF8) {
            return $raw;
        }

        $prefix = $publicId.' ';
        $budget = max(1, self::TOPIC_NAME_MAX_UTF8 - mb_strlen($prefix, 'UTF-8'));
        $suffix = mb_substr($name, 0, $budget, 'UTF-8');

        return $prefix.$suffix;
    }

    private function normalizeChatId(mixed $groupId): string
    {
        if ($groupId === null) {
            return '';
        }
        if (is_int($groupId) || is_float($groupId)) {
            return (string) (int) $groupId;
        }

        return trim((string) $groupId);
    }
}
