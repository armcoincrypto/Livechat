<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportConversation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Telegram forum-topic slash commands for support operators (never forwarded to website visitors).
 */
final class SupportTelegramOperatorCommandService
{
    private const CHAT_MEMBER_CACHE_TTL_SECONDS = 120;

    public function __construct(
        private readonly SupportConversationLifecycleService $lifecycle,
        private readonly SupportTelegramForumTopicService $forumTopics,
    ) {}

    /**
     * Handle slash commands when enabled, message is in a mapped forum topic, and body starts with "/".
     * Returns true if the update was fully handled (including unknown "/" in that topic).
     */
    public function tryHandle(array $message, array $from, string $rawTextBody): bool
    {
        if (! filter_var(config('support_chat.telegram.operator_commands_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
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
            ->first();

        if ($conversation === null) {
            return false;
        }

        $token = trim((string) config('support_chat.telegram.bot_token', ''));
        $chatId = $this->normalizeChatId(config('support_chat.telegram.group_id'));
        if ($token === '' || $chatId === '') {
            return false;
        }

        $userId = isset($from['id']) ? (int) $from['id'] : 0;
        if ($userId < 1) {
            $this->sendTopicReply($token, $chatId, $threadId, 'Only support admins can use this command.');

            return true;
        }

        if (! $this->isGroupAdminOrCreator($token, $chatId, $userId)) {
            $this->sendTopicReply($token, $chatId, $threadId, 'Only support admins can use this command.');
            Log::info('support-chat telegram: operator_command_denied_not_admin', [
                'support_conversation_id' => $conversation->id,
                'public_support_id' => $conversation->public_support_id,
                'telegram_user_id' => $userId,
            ]);

            return true;
        }

        $command = $this->normalizeSlashCommand($rawTextBody);
        if ($command === null) {
            $this->sendTopicReply($token, $chatId, $threadId, 'Unknown command. Use /status, /close, or /reopen.');

            return true;
        }

        return match ($command) {
            '/status' => $this->handleStatus($token, $chatId, $threadId, $conversation),
            '/close' => $this->handleClose($token, $chatId, $threadId, $conversation, $userId),
            '/reopen' => $this->handleReopen($token, $chatId, $threadId, $conversation, $userId),
            default => $this->handleUnknown($token, $chatId, $threadId, $conversation),
        };
    }

    private function handleStatus(string $token, string $chatId, int $threadId, SupportConversation $conversation): bool
    {
        $conversation->refresh();
        $page = $this->compactPageOneLine($conversation->page_url);

        $lines = [
            '#'.($conversation->public_support_id ?? '—'),
            'Status: '.$conversation->status,
            'Visitor: '.$conversation->visitor_name,
            'Email: '.$conversation->visitor_email,
            'Page: '.$page,
            'Topic: '.$threadId,
            'Last visitor: '.$this->humanizeShort($conversation->last_visitor_message_at),
            'Last operator: '.$this->humanizeShort($conversation->last_operator_message_at),
        ];

        $text = implode("\n", $lines);
        $this->sendTopicReply($token, $chatId, $threadId, $text, null);
        Log::info('support-chat telegram: operator_command_status', [
            'support_conversation_id' => $conversation->id,
            'public_support_id' => $conversation->public_support_id,
        ]);

        return true;
    }

    private function handleClose(string $token, string $chatId, int $threadId, SupportConversation $conversation, int $actorTelegramUserId): bool
    {
        $conversation->refresh();
        $this->lifecycle->closeByOperator($conversation, 'telegram_command', [
            'telegram_user_id' => $actorTelegramUserId,
        ]);
        $this->sendTopicReply($token, $chatId, $threadId, '✅ Conversation closed', null);
        $this->forumTopics->closeForumTopic($conversation->fresh());
        Log::info('support-chat telegram: operator_command_close', [
            'support_conversation_id' => $conversation->id,
            'public_support_id' => $conversation->public_support_id,
            'telegram_user_id' => $actorTelegramUserId,
        ]);

        return true;
    }

    private function handleReopen(string $token, string $chatId, int $threadId, SupportConversation $conversation, int $actorTelegramUserId): bool
    {
        $conversation->refresh();
        if (! $conversation->isClosed()) {
            $this->sendTopicReply($token, $chatId, $threadId, 'Conversation is not closed.');

            return true;
        }

        $this->lifecycle->reopenByOperator($conversation, 'operator', 'telegram_command');
        $this->forumTopics->reopenForumTopic($conversation->fresh());
        $this->sendTopicReply($token, $chatId, $threadId, '🟢 Conversation reopened', null);
        Log::info('support-chat telegram: operator_command_reopen', [
            'support_conversation_id' => $conversation->id,
            'public_support_id' => $conversation->public_support_id,
            'telegram_user_id' => $actorTelegramUserId,
        ]);

        return true;
    }

    private function handleUnknown(string $token, string $chatId, int $threadId, SupportConversation $conversation): bool
    {
        $this->sendTopicReply($token, $chatId, $threadId, 'Unknown command. Use /status, /close, or /reopen.');
        Log::info('support-chat telegram: operator_command_unknown', [
            'support_conversation_id' => $conversation->id,
            'public_support_id' => $conversation->public_support_id,
        ]);

        return true;
    }

    private function normalizeSlashCommand(string $raw): ?string
    {
        $trim = trim($raw);
        if ($trim === '' || $trim[0] !== '/') {
            return null;
        }

        $partsLine = explode("\n", $trim, 2);
        $firstLine = trim($partsLine[0] ?? $trim);
        $parts = preg_split('/\s+/', $firstLine, 2);
        $cmd = $parts[0] ?? '';
        if ($cmd === '') {
            return null;
        }
        if (str_contains($cmd, '@')) {
            $cmd = explode('@', $cmd, 2)[0];
        }

        return strtolower($cmd);
    }

    /**
     * Whether the Telegram user is an administrator or creator of the configured support group.
     */
    public function isConfiguredSupportGroupAdminOrCreator(int $telegramUserId): bool
    {
        if ($telegramUserId < 1) {
            return false;
        }

        $token = trim((string) config('support_chat.telegram.bot_token', ''));
        $chatId = $this->normalizeChatId(config('support_chat.telegram.group_id'));
        if ($token === '' || $chatId === '') {
            return false;
        }

        return $this->isGroupAdminOrCreator($token, $chatId, $telegramUserId);
    }

    private function isGroupAdminOrCreator(string $token, string $chatId, int $userId): bool
    {
        $cacheKey = 'support_tg_chat_member:'.$chatId.':'.$userId;

        return (bool) Cache::remember($cacheKey, self::CHAT_MEMBER_CACHE_TTL_SECONDS, function () use ($token, $chatId, $userId): bool {
            try {
                $response = Http::timeout(15)
                    ->acceptJson()
                    ->get("https://api.telegram.org/bot{$token}/getChatMember", [
                        'chat_id' => $chatId,
                        'user_id' => $userId,
                    ]);
            } catch (Throwable $e) {
                Log::warning('support-chat telegram: getChatMember transport_error', [
                    'telegram_user_id' => $userId,
                    'exception' => $e->getMessage(),
                ]);

                return false;
            }

            if (! $response->successful()) {
                Log::warning('support-chat telegram: getChatMember http_error', [
                    'telegram_user_id' => $userId,
                    'status' => $response->status(),
                ]);

                return false;
            }

            $data = $response->json();
            if (! is_array($data) || empty($data['ok'])) {
                return false;
            }

            $result = $data['result'] ?? null;
            if (! is_array($result)) {
                return false;
            }

            $status = isset($result['status']) && is_string($result['status']) ? $result['status'] : '';

            return in_array($status, ['creator', 'administrator'], true);
        });
    }

    private function humanizeShort(mixed $dt): string
    {
        if (! $dt instanceof \DateTimeInterface) {
            return '—';
        }

        return Carbon::instance($dt)->diffForHumans(null, false, true, 1);
    }

    private function compactPageOneLine(?string $pageUrl): string
    {
        if ($pageUrl === null || trim($pageUrl) === '') {
            return '—';
        }

        $trim = trim($pageUrl);
        $parts = parse_url($trim);
        if ($parts !== false && isset($parts['host'])) {
            $path = $parts['path'] ?? '';
            $q = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
            $display = $parts['host'].$path.$q;
        } else {
            $display = preg_replace('#^https?://#i', '', $trim) ?? $trim;
        }

        if (mb_strlen($display, 'UTF-8') > 160) {
            return mb_substr($display, 0, 157, 'UTF-8').'…';
        }

        return $display;
    }

    private function sendTopicReply(string $token, string $chatId, int $threadId, string $text, ?string $parseMode = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'message_thread_id' => $threadId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];
        if ($parseMode !== null && $parseMode !== '') {
            $payload['parse_mode'] = $parseMode;
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
        } catch (Throwable $e) {
            Log::warning('support-chat telegram: operator_command_reply transport_error', [
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        if (! $response->successful()) {
            Log::warning('support-chat telegram: operator_command_reply http_error', [
                'status' => $response->status(),
            ]);
        }
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
