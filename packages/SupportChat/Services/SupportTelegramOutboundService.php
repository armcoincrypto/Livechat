<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use iEXPackages\SupportChat\Services\SupportChatDiagnosticsLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SupportTelegramOutboundService
{
    private const TELEGRAM_TEXT_MAX = 4096;

    public function __construct(
        private readonly SupportTelegramForumTopicService $forumTopics,
        private readonly SupportAiDraftService $aiDraft,
        private readonly SupportAiLearningService $aiLearning,
        private readonly SupportAiSuggestionUxService $ux,
        private readonly SupportTelegramAiActionService $telegramAiActions,
    ) {}

    /**
     * Whether outbound Telegram notifications are configured and enabled.
     */
    public function isEnabled(): bool
    {
        if (! filter_var(config('support_chat.telegram.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $token = $this->normalizeToken((string) config('support_chat.telegram.bot_token', ''));
        $chatId = $this->normalizeChatId(config('support_chat.telegram.group_id'));

        return $token !== '' && $chatId !== '';
    }

    /**
     * Send visitor message to the support Telegram group. Returns Telegram message_id or null on failure/disabled.
     */
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

        if (! $this->aiDraft->isTelegramSeparateMessageEnabled()) {
            if (mb_strlen($text, 'UTF-8') > self::TELEGRAM_TEXT_MAX) {
                $text = $this->truncateAiSectionOnly($text, $parseMode);
            }
        }

        if (mb_strlen($text, 'UTF-8') > self::TELEGRAM_TEXT_MAX) {
            $text = mb_substr($text, 0, max(0, self::TELEGRAM_TEXT_MAX - 1), 'UTF-8').'…';
        }

        $messageThreadId = null;
        $conversation = $message->conversation;
        if ($conversation !== null
            && filter_var(config('support_chat.telegram.use_forum_topics', false), FILTER_VALIDATE_BOOLEAN)) {
            $messageThreadId = $this->forumTopics->createForumTopic($conversation);
        }

        $telegramMessageId = $this->sendTelegramText(
            $token,
            $chatId,
            $text,
            $parseMode,
            $messageThreadId,
            $message->id,
            'visitor_notification',
        );

        if ($telegramMessageId === null) {
            return null;
        }

        $this->sendAiSuggestionsSeparateMessage($message, $token, $chatId, $messageThreadId, $telegramMessageId);

        if (! $this->aiDraft->isTelegramSeparateMessageEnabled()) {
            $this->enrichAppendLearningLineage($message, $telegramMessageId);
        }

        return $telegramMessageId;
    }

    /**
     * @return array{0: string, 1: string|null} [text, parse_mode]
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
        $prefix = "👤 Visitor:\n";
        $body = (string) $message->body;
        $base = $prefix.$body;

        if ($this->aiDraft->isTelegramSeparateMessageEnabled()) {
            return $base;
        }

        $remaining = max(0, self::TELEGRAM_TEXT_MAX - mb_strlen($base, 'UTF-8'));

        return $base.($remaining >= 200 ? $this->buildAiDraftAppendPlain($message, $remaining) : '');
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

        $bodyRaw = (string) $message->body;
        $bodyEsc = $this->escapeTelegramHtml($bodyRaw);

        $useForum = filter_var(config('support_chat.telegram.use_forum_topics', false), FILTER_VALIDATE_BOOLEAN);
        $footer = $useForum
            ? '<i>Reply in this topic to answer the visitor.</i>'
            : '<i>Reply to this message to answer the visitor.</i>';

        $sep = '━━━━━━━━━━━━';
        $headBlock = <<<HTML
<pre>{$sep}</pre>
🟢 <b>New Support Request</b>

<b>#{$publicId}</b> · {$locale}
👤 {$name}
🌐 {$originEsc}

HTML;
        $tailBlock = <<<HTML

<pre>{$sep}</pre>

{$footer}
HTML;

        $baseWithoutAi = $headBlock.'<pre>'.$bodyEsc.'</pre>'.$tailBlock;

        if ($this->aiDraft->isTelegramSeparateMessageEnabled()) {
            return $baseWithoutAi;
        }

        $remaining = max(0, self::TELEGRAM_TEXT_MAX - mb_strlen($baseWithoutAi, 'UTF-8'));
        $aiAppend = $remaining >= 200 ? $this->buildAiDraftAppendHtml($message, $remaining) : '';

        return $baseWithoutAi.$aiAppend;
    }

    private function sendAiSuggestionsSeparateMessage(
        SupportMessage $message,
        string $token,
        string $chatId,
        ?int $messageThreadId,
        ?int $visitorTelegramMessageId = null,
    ): void {
        if (! $this->aiDraft->isTelegramSeparateMessageEnabled()) {
            return;
        }

        $message->loadMissing('conversation');
        $conversation = $message->conversation;
        if ($conversation === null) {
            return;
        }

        try {
            $result = $this->aiDraft->draftForConversation(
                $conversation,
                $message,
                null,
                true,
            );
        } catch (Throwable $e) {
            Log::warning('support-chat ai: telegram_separate_failed', [
                'support_message_id' => $message->id,
                'stage' => 'draft',
                'exception' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);

            return;
        }

        if (($result['draft'] ?? null) === null && ($result['options'] ?? []) === []) {
            return;
        }

        $lineage = [];
        if ($visitorTelegramMessageId !== null && $visitorTelegramMessageId > 0) {
            $lineage['telegram_reply_to_message_id'] = $visitorTelegramMessageId;
        }

        $event = $this->recordAiSuggestionsForLearning(
            $conversation,
            $message,
            $result,
            'telegram_separate',
            $lineage,
        );

        $text = $this->aiDraft->formatTelegramSeparateMessage($result, self::TELEGRAM_TEXT_MAX);
        if ($text === null || trim($text) === '') {
            return;
        }

        $replyMarkup = null;
        if ($this->telegramAiActions->isEnabled() && $event !== null) {
            $options = $this->aiDraft->resolveDisplayOptions($result);
            $collapseState = $this->ux->resolveTelegramCollapseState($result, $options);
            $replyMarkup = $this->telegramAiActions->buildReplyMarkup(
                (int) $event->id,
                $result,
                (bool) ($collapseState['collapsed'] ?? false),
            );
        }

        $aiMessageId = $this->sendTelegramText(
            $token,
            $chatId,
            $text,
            'HTML',
            $messageThreadId,
            $message->id,
            'ai_suggestions',
            $replyMarkup,
        );

        if ($aiMessageId === null) {
            Log::warning('support-chat ai: telegram_separate_failed', [
                'support_message_id' => $message->id,
                'stage' => 'send',
            ]);

            return;
        }

        if ($event !== null) {
            $this->aiLearning->enrichEventLineage((int) $event->id, [
                'telegram_ai_message_id' => $aiMessageId,
                'telegram_reply_to_message_id' => $visitorTelegramMessageId,
            ]);
        }
    }

    private function buildAiDraftAppendPlain(SupportMessage $message, ?int $maxChars = null): string
    {
        if (! $this->aiDraft->isTelegramPreviewEnabled() || $this->aiDraft->isTelegramSeparateMessageEnabled()) {
            return '';
        }

        $result = $this->loadAiDraftResult($message);
        if ($result === null) {
            return '';
        }

        return $this->aiDraft->formatTelegramAppend($result, $maxChars);
    }

    private function buildAiDraftAppendHtml(SupportMessage $message, ?int $maxChars = null): string
    {
        if (! $this->aiDraft->isTelegramPreviewEnabled() || $this->aiDraft->isTelegramSeparateMessageEnabled()) {
            return '';
        }

        $result = $this->loadAiDraftResult($message);
        if ($result === null) {
            return '';
        }

        return $this->aiDraft->formatTelegramAppendHtml($result, $maxChars);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadAiDraftResult(SupportMessage $message): ?array
    {
        if (! $this->aiDraft->isTelegramPreviewEnabled()) {
            return null;
        }

        $message->loadMissing('conversation');
        $conversation = $message->conversation;
        if ($conversation === null) {
            return null;
        }

        try {
            $result = $this->aiDraft->draftForConversation(
                $conversation,
                $message,
                null,
                false,
            );
        } catch (Throwable $e) {
            Log::warning('support-chat ai: telegram_append_failed', [
                'support_message_id' => $message->id,
                'exception' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);

            return null;
        }

        if (($result['draft'] ?? null) === null && ($result['options'] ?? []) === []) {
            return null;
        }

        $event = $this->recordAiSuggestionsForLearning($conversation, $message, $result, 'telegram_append');

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $lineage
     */
    private function recordAiSuggestionsForLearning(
        SupportConversation $conversation,
        SupportMessage $message,
        array $result,
        string $source,
        array $lineage = [],
    ): ?\App\Models\SupportAiLearningEvent {
        try {
            $context = $this->aiDraft->resolveDraftContext($conversation, $message);
            if ($lineage !== []) {
                $context['lineage'] = $lineage;
            }

            return $this->aiLearning->recordAiSuggestions($conversation, $message, $result, $context, $source);
        } catch (Throwable $e) {
            Log::warning('support-chat ai:learning_record_failed', [
                'stage' => 'integration',
                'source' => $source,
                'support_message_id' => $message->id,
                'exception' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);

            return null;
        }
    }

    private function enrichAppendLearningLineage(SupportMessage $message, int $visitorTelegramMessageId): void
    {
        if ($visitorTelegramMessageId < 1) {
            return;
        }

        try {
            $event = \App\Models\SupportAiLearningEvent::query()
                ->where('message_id', (int) $message->id)
                ->where('metadata->source', 'telegram_append')
                ->orderByDesc('id')
                ->first();

            if ($event === null) {
                return;
            }

            $this->aiLearning->enrichEventLineage((int) $event->id, [
                'telegram_reply_to_message_id' => $visitorTelegramMessageId,
            ]);
        } catch (Throwable $e) {
            Log::warning('support-chat ai:learning_lineage_enrich_failed', [
                'stage' => 'telegram_append',
                'support_message_id' => $message->id,
                'exception' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);
        }
    }

    private function sendTelegramText(
        string $token,
        string $chatId,
        string $text,
        ?string $parseMode,
        ?int $messageThreadId,
        int $supportMessageId,
        string $kind,
        ?array $replyMarkup = null,
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
        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
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
            Log::warning('support-chat telegram: outbound sendMessage missing_message_id', [
                'support_message_id' => $supportMessageId,
                'kind' => $kind,
                'response' => $data,
            ]);

            return null;
        }

        return (int) $result['message_id'];
    }

    private function truncateAiSectionOnly(string $text, ?string $parseMode): string
    {
        $marker = '🤖 AI reply suggestions';
        $pos = mb_strpos($text, $marker, 0, 'UTF-8');
        if ($pos === false) {
            $marker = '🤖 <b>AI reply suggestions</b>';
            $pos = mb_strpos($text, $marker, 0, 'UTF-8');
        }
        if ($pos === false) {
            return $text;
        }

        $base = mb_substr($text, 0, $pos, 'UTF-8');
        $note = $parseMode === 'HTML'
            ? "\n<pre>━━━━━━━━━━━━</pre>\n<i>AI suggestions shortened for Telegram.</i>"
            : "\n━━━━━━━━━━━━\nAI suggestions shortened for Telegram.";

        return rtrim($base).$note;
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
