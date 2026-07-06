<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Plain operator chat: visitor messages are forwarded to Telegram; operators reply via inbound webhook.
 */
final class SupportTelegramOutboundService
{
    private const TELEGRAM_TEXT_MAX = 4096;

    public function __construct(
        private readonly SupportTelegramForumTopicService $forumTopics,
    ) {}

    public function isEnabled(): bool
    {
        if (! filter_var(config('support_chat.telegram.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $token = $this->normalizeToken((string) config('support_chat.telegram.bot_token', ''));
        $chatId = $this->normalizeChatId(config('support_chat.telegram.group_id'));

        return $token !== '' && $chatId !== '';
    }

    public function sendVisitorMessageNotification(SupportMessage $message): ?int
    {
        if (! $this->isEnabled()) {
            return null;
        }

        if ($message->sender_type !== SupportMessage::SENDER_VISITOR) {
            return null;
        }

        $token = $this->normalizeToken((string) config('support_chat.telegram.bot_token', ''));
        $chatId = $this->normalizeChatId(config('support_chat.telegram.group_id'));

        if ($token === '' || $chatId === '') {
            return null;
        }

        $message->loadMissing('conversation');

        [$text, $parseMode] = $this->buildNotificationTextAndParseMode($message);

        if (mb_strlen($text, 'UTF-8') > self::TELEGRAM_TEXT_MAX) {
            $text = mb_substr($text, 0, max(0, self::TELEGRAM_TEXT_MAX - 1), 'UTF-8').'…';
        }

        $messageThreadId = null;
        $conversation = $message->conversation;
        if ($conversation !== null
            && filter_var(config('support_chat.telegram.use_forum_topics', false), FILTER_VALIDATE_BOOLEAN)) {
            $messageThreadId = $this->forumTopics->createForumTopic($conversation);
        }

        return $this->sendTelegramText(
            $token,
            $chatId,
            $text,
            $parseMode,
            $messageThreadId,
            $message->id,
            'visitor_notification',
        );
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function buildNotificationTextAndParseMode(SupportMessage $message): array
    {
        if ($this->isFollowUpVisitorNotification($message)) {
            return [$this->buildFollowUpVisitorNotificationPlain($message), null];
        }

        return [$this->buildFirstVisitorNotificationHtml($message), 'HTML'];
    }

    private function isFollowUpVisitorNotification(SupportMessage $message): bool
    {
        return SupportMessage::query()
            ->where('support_conversation_id', $message->support_conversation_id)
            ->where('sender_type', SupportMessage::SENDER_VISITOR)
            ->where('id', '<', $message->id)
            ->exists();
    }

    private function buildFollowUpVisitorNotificationPlain(SupportMessage $message): string
    {
        return "👤 Visitor:\n".(string) $message->body;
    }

    private function buildFirstVisitorNotificationHtml(SupportMessage $message): string
    {
        $conversation = $message->conversation;
        $name = $conversation !== null ? $this->escapeTelegramHtml((string) $conversation->visitor_name) : '—';
        $locale = $conversation !== null && $conversation->locale !== null && $conversation->locale !== ''
            ? $this->escapeTelegramHtml((string) $conversation->locale)
            : '—';
        $publicId = $conversation !== null && $conversation->public_support_id !== null && $conversation->public_support_id !== ''
            ? $this->escapeTelegramHtml((string) $conversation->public_support_id)
            : '—';

        $origin = $this->compactOriginDisplay(
            $conversation !== null ? $conversation->page_url : null,
        );
        $originEsc = $this->escapeTelegramHtml($origin);
        $bodyEsc = $this->escapeTelegramHtml((string) $message->body);

        $useForum = filter_var(config('support_chat.telegram.use_forum_topics', false), FILTER_VALIDATE_BOOLEAN);
        $footer = $useForum
            ? '<i>Reply in this topic to answer the visitor.</i>'
            : '<i>Reply to this message to answer the visitor.</i>';

        $sep = '━━━━━━━━━━━━';

        return <<<HTML
<pre>{$sep}</pre>
🟢 <b>New chat message</b>

<b>#{$publicId}</b> · {$locale}
👤 {$name}
🌐 {$originEsc}

<pre>{$bodyEsc}</pre>
<pre>{$sep}</pre>

{$footer}
HTML;
    }

    private function sendTelegramText(
        string $token,
        string $chatId,
        string $text,
        ?string $parseMode,
        ?int $messageThreadId,
        int $supportMessageId,
        string $kind,
    ): ?int {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];
        if ($parseMode !== null && $parseMode !== '') {
            $payload['parse_mode'] = $parseMode;
        }
        if ($messageThreadId !== null && $messageThreadId > 0) {
            $payload['message_thread_id'] = $messageThreadId;
        }

        try {
            $response = Http::timeout(25)
                ->acceptJson()
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
        } catch (Throwable $e) {
            Log::warning('support-chat telegram: outbound sendMessage transport_error', [
                'support_message_id' => $supportMessageId,
                'kind' => $kind,
                'exception' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('support-chat telegram: outbound sendMessage http_error', [
                'support_message_id' => $supportMessageId,
                'kind' => $kind,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500, 'UTF-8'),
            ]);

            return null;
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['ok'])) {
            Log::warning('support-chat telegram: outbound sendMessage api_rejected', [
                'support_message_id' => $supportMessageId,
                'kind' => $kind,
                'response' => $data,
            ]);

            return null;
        }

        $result = $data['result'] ?? null;
        if (! is_array($result) || ! isset($result['message_id'])) {
            return null;
        }

        return (int) $result['message_id'];
    }

    private function escapeTelegramHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function compactOriginDisplay(?string $pageUrl): string
    {
        if ($pageUrl === null || trim($pageUrl) === '') {
            return '—';
        }

        $trim = trim($pageUrl);
        $parts = parse_url($trim);
        if ($parts !== false && isset($parts['host'])) {
            $path = isset($parts['path']) ? $parts['path'] : '';
            $q = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
            $display = $parts['host'].$path.$q;
        } else {
            $display = preg_replace('#^https?://#i', '', $trim) ?? $trim;
        }

        return $this->truncateForHeaderLine($display, 120);
    }

    private function normalizeToken(string $token): string
    {
        return trim($token);
    }

    private function normalizeChatId(mixed $groupId): string
    {
        if ($groupId === null) {
            return '';
        }

        if (is_int($groupId)) {
            return (string) $groupId;
        }

        return trim((string) $groupId);
    }

    private function truncateForHeaderLine(string $value, int $maxUtf8Chars): string
    {
        if ($maxUtf8Chars < 1) {
            return '';
        }

        if (mb_strlen($value, 'UTF-8') <= $maxUtf8Chars) {
            return $value;
        }

        return mb_substr($value, 0, max(0, $maxUtf8Chars - 1), 'UTF-8').'…';
    }
}
