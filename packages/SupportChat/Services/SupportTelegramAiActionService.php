<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAiLearningEvent;
use App\Models\SupportAiSuggestionUsage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Telegram inline actions for AI suggestion cards (Used / Modified / Ignore / Full).
 * Operator feedback only — never sends messages to visitors.
 */
final class SupportTelegramAiActionService
{
    public const CALLBACK_USED = 'u';

    public const CALLBACK_MODIFIED = 'm';

    public const CALLBACK_IGNORED = 'i';

    public const CALLBACK_FULL = 'f';

    public function __construct(
        private readonly SupportAiSuggestionUxService $ux,
        private readonly SupportAiDraftService $aiDraft,
        private readonly SupportAiLearningService $aiLearning,
        private readonly SupportAiSuggestionAcceptanceService $acceptance,
    ) {}

    public function isEnabled(): bool
    {
        if (! filter_var(config('support_chat.ai.telegram_actions.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (! filter_var(config('support_chat.telegram.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return $this->acceptance->isEnabled();
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>|null Telegram reply_markup
     */
    public function buildReplyMarkup(?int $learningEventId, array $result, bool $collapsed = false, ?string $selectedAction = null): ?array
    {
        if (! $this->isEnabled() || $learningEventId === null || $learningEventId < 1) {
            return null;
        }

        if ($selectedAction !== null) {
            return [
                'inline_keyboard' => [[
                    ['text' => $this->selectedActionLabel($selectedAction), 'callback_data' => 'ai:x:'.$learningEventId],
                ]],
            ];
        }

        $confidence = strtolower((string) ($result['operator_confidence'] ?? $result['confidence'] ?? 'medium'));
        $usedLabel = ($confidence === 'medium') ? '✅ Used main' : '✅ Used';

        $rows = [];
        if ($collapsed && $this->ux->isTelegramCollapseLongEnabled()) {
            $rows[] = [[
                'text' => '📖 Full',
                'callback_data' => $this->buildCallbackData(self::CALLBACK_FULL, $learningEventId),
            ]];
        }

        $rows[] = [
            ['text' => $usedLabel, 'callback_data' => $this->buildCallbackData(self::CALLBACK_USED, $learningEventId)],
            ['text' => '✏️ Modified', 'callback_data' => $this->buildCallbackData(self::CALLBACK_MODIFIED, $learningEventId)],
            ['text' => '🚫 Ignore', 'callback_data' => $this->buildCallbackData(self::CALLBACK_IGNORED, $learningEventId)],
        ];

        return ['inline_keyboard' => $rows];
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     */
    public function handleCallbackQuery(array $callbackQuery): void
    {
        if (! $this->isEnabled()) {
            $this->answerCallbackQuery($callbackQuery, 'Actions disabled', true);

            return;
        }

        $data = isset($callbackQuery['data']) && is_string($callbackQuery['data'])
            ? trim($callbackQuery['data'])
            : '';
        if ($data === '' || ! str_starts_with($data, 'ai:')) {
            return;
        }

        $parts = explode(':', $data, 3);
        if (count($parts) !== 3) {
            $this->answerCallbackQuery($callbackQuery, 'Invalid action', true);

            return;
        }

        [, $action, $eventIdRaw] = $parts;
        $eventId = (int) $eventIdRaw;
        if ($eventId < 1) {
            $this->answerCallbackQuery($callbackQuery, 'Invalid action', true);

            return;
        }

        if ($action === 'x') {
            $this->answerCallbackQuery($callbackQuery, 'Already recorded', true);

            return;
        }

        if (! in_array($action, [self::CALLBACK_USED, self::CALLBACK_MODIFIED, self::CALLBACK_IGNORED, self::CALLBACK_FULL], true)) {
            $this->answerCallbackQuery($callbackQuery, 'Unknown action', true);

            return;
        }

        if (! $this->isAuthorizedCallback($callbackQuery)) {
            $this->answerCallbackQuery($callbackQuery, 'Not authorized', true);

            return;
        }

        $event = SupportAiLearningEvent::query()->find($eventId);
        if ($event === null) {
            $this->answerCallbackQuery($callbackQuery, 'Suggestion not found', true);

            return;
        }

        if (! $this->callbackMessageMatchesEvent($callbackQuery, $event)) {
            $this->answerCallbackQuery($callbackQuery, 'Not authorized', true);

            return;
        }

        if ($action === self::CALLBACK_FULL) {
            $this->handleExpandFull($callbackQuery, $event);

            return;
        }

        $existing = $this->findButtonUsageForEvent($eventId);
        if ($existing !== null) {
            $this->answerCallbackQuery($callbackQuery, $this->selectedActionLabel($this->decisionToAction($existing->decision)), true);
            $this->refreshMessageMarkup($callbackQuery, $event, $this->decisionToAction($existing->decision));

            return;
        }

        $decision = match ($action) {
            self::CALLBACK_USED => SupportAiSuggestionUsage::DECISION_ACCEPTED_EXACT,
            self::CALLBACK_MODIFIED => SupportAiSuggestionUsage::DECISION_ACCEPTED_MODIFIED,
            default => SupportAiSuggestionUsage::DECISION_IGNORED,
        };

        $from = is_array($callbackQuery['from'] ?? null) ? $callbackQuery['from'] : [];
        $telegramUserId = isset($from['id']) ? (int) $from['id'] : null;

        $usage = $this->acceptance->recordForTelegramButton($event, $decision, $telegramUserId);
        if ($usage === null) {
            $this->answerCallbackQuery($callbackQuery, 'Could not record feedback', true);

            return;
        }

        $confirm = match ($action) {
            self::CALLBACK_USED => '✅ Marked as used',
            self::CALLBACK_MODIFIED => '✏️ Marked as modified',
            default => '🚫 Marked as ignored',
        };

        $this->answerCallbackQuery($callbackQuery, $confirm, false);
        $this->refreshMessageMarkup($callbackQuery, $event, $action);
    }

    private function handleExpandFull(array $callbackQuery, SupportAiLearningEvent $event): void
    {
        $metadata = is_array($event->metadata) ? $event->metadata : [];
        $telegramUx = is_array($metadata['telegram_ux'] ?? null) ? $metadata['telegram_ux'] : [];

        if (! ($telegramUx['collapsed'] ?? false) || ($telegramUx['expanded'] ?? false)) {
            $this->answerCallbackQuery($callbackQuery, 'Already expanded', true);

            return;
        }

        $result = $this->rebuildDraftResultFromEvent($event);
        if ($result === null) {
            $this->answerCallbackQuery($callbackQuery, 'Could not expand', true);

            return;
        }

        $result['telegram_ux'] = [
            'collapsed' => false,
            'expanded' => true,
        ];

        $text = $this->aiDraft->formatTelegramSeparateMessage($result);
        if ($text === null || trim($text) === '') {
            $this->answerCallbackQuery($callbackQuery, 'Could not expand', true);

            return;
        }

        $telegramUx['expanded'] = true;
        $telegramUx['collapsed'] = false;
        $metadata['telegram_ux'] = $telegramUx;
        $event->metadata = $metadata;
        $event->save();

        $markup = $this->buildReplyMarkup((int) $event->id, $result, false);
        $edited = $this->editMessageText($callbackQuery, $text, $markup);
        if (! $edited) {
            $this->answerCallbackQuery($callbackQuery, 'Could not update message', true);

            return;
        }

        $this->answerCallbackQuery($callbackQuery, 'Full reply shown', false);
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     */
    private function refreshMessageMarkup(array $callbackQuery, SupportAiLearningEvent $event, string $selectedAction): void
    {
        $result = $this->rebuildDraftResultFromEvent($event);
        if ($result === null) {
            return;
        }

        $metadata = is_array($event->metadata) ? $event->metadata : [];
        $telegramUx = is_array($metadata['telegram_ux'] ?? null) ? $metadata['telegram_ux'] : [];
        $collapsed = (bool) ($telegramUx['collapsed'] ?? false) && ! ($telegramUx['expanded'] ?? false);

        $markup = $this->buildReplyMarkup((int) $event->id, $result, $collapsed, $selectedAction);
        $this->editMessageReplyMarkup($callbackQuery, $markup);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rebuildDraftResultFromEvent(SupportAiLearningEvent $event): ?array
    {
        $suggestions = is_array($event->suggestions) ? $event->suggestions : [];
        if ($suggestions === []) {
            return null;
        }

        $metadata = is_array($event->metadata) ? $event->metadata : [];
        $options = [];
        foreach ($suggestions as $row) {
            if (! is_array($row)) {
                continue;
            }
            $text = trim((string) ($row['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $options[] = [
                'label' => (string) ($row['label'] ?? ''),
                'style' => (string) ($row['style'] ?? ''),
                'text' => $text,
            ];
        }

        if ($options === []) {
            return null;
        }

        return [
            'language' => (string) ($event->language ?? 'en'),
            'confidence' => (string) ($metadata['confidence'] ?? 'medium'),
            'operator_confidence' => (string) ($metadata['operator_confidence'] ?? $metadata['confidence'] ?? 'medium'),
            'ux' => is_array($metadata['ux'] ?? null) ? $metadata['ux'] : [],
            'options' => $options,
            'choices' => (int) ($metadata['choices'] ?? count($options)),
            'telegram_ux' => is_array($metadata['telegram_ux'] ?? null) ? $metadata['telegram_ux'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     */
    private function isAuthorizedCallback(array $callbackQuery): bool
    {
        $from = $callbackQuery['from'] ?? null;
        if (! is_array($from) || ! empty($from['is_bot'])) {
            return false;
        }

        $message = $callbackQuery['message'] ?? null;
        if (! is_array($message)) {
            return false;
        }

        $chatId = data_get($message, 'chat.id');
        $expected = config('support_chat.telegram.group_id');

        return $this->telegramChatIdsEqual($chatId, $expected);
    }

    private function callbackMessageMatchesEvent(array $callbackQuery, SupportAiLearningEvent $event): bool
    {
        $message = $callbackQuery['message'] ?? null;
        if (! is_array($message)) {
            return false;
        }

        $telegramMessageId = isset($message['message_id']) ? (int) $message['message_id'] : 0;
        if ($telegramMessageId < 1) {
            return false;
        }

        $metadata = is_array($event->metadata) ? $event->metadata : [];
        $storedId = isset($metadata['telegram_ai_message_id']) ? (int) $metadata['telegram_ai_message_id'] : 0;

        return $storedId > 0 && $storedId === $telegramMessageId;
    }

    private function findButtonUsageForEvent(int $learningEventId): ?SupportAiSuggestionUsage
    {
        return SupportAiSuggestionUsage::query()
            ->where('learning_event_id', $learningEventId)
            ->where('matched_by', SupportAiSuggestionUsage::MATCHED_BY_OPERATOR_TELEGRAM_BUTTON)
            ->orderByDesc('id')
            ->first();
    }

    private function decisionToAction(string $decision): string
    {
        return match ($decision) {
            SupportAiSuggestionUsage::DECISION_ACCEPTED_EXACT => self::CALLBACK_USED,
            SupportAiSuggestionUsage::DECISION_ACCEPTED_MODIFIED => self::CALLBACK_MODIFIED,
            SupportAiSuggestionUsage::DECISION_IGNORED => self::CALLBACK_IGNORED,
            default => self::CALLBACK_IGNORED,
        };
    }

    private function selectedActionLabel(string $action): string
    {
        return match ($action) {
            self::CALLBACK_USED => '✅ Marked used',
            self::CALLBACK_MODIFIED => '✏️ Marked modified',
            self::CALLBACK_IGNORED => '🚫 Marked ignored',
            default => '✅ Recorded',
        };
    }

    private function buildCallbackData(string $action, int $learningEventId): string
    {
        return 'ai:'.$action.':'.$learningEventId;
    }

    /**
     * @param  array<string, mixed>|null  $replyMarkup
     */
    private function editMessageText(array $callbackQuery, string $text, ?array $replyMarkup): bool
    {
        $message = $callbackQuery['message'] ?? null;
        if (! is_array($message)) {
            return false;
        }

        $chatId = data_get($message, 'chat.id');
        $messageId = isset($message['message_id']) ? (int) $message['message_id'] : 0;
        if ($messageId < 1) {
            return false;
        }

        $token = $this->botToken();
        if ($token === '') {
            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];
        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        return $this->postTelegramApi($token, 'editMessageText', $payload);
    }

    /**
     * @param  array<string, mixed>|null  $replyMarkup
     */
    private function editMessageReplyMarkup(array $callbackQuery, ?array $replyMarkup): bool
    {
        $message = $callbackQuery['message'] ?? null;
        if (! is_array($message)) {
            return false;
        }

        $chatId = data_get($message, 'chat.id');
        $messageId = isset($message['message_id']) ? (int) $message['message_id'] : 0;
        if ($messageId < 1) {
            return false;
        }

        $token = $this->botToken();
        if ($token === '') {
            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup ?? ['inline_keyboard' => []],
        ];

        return $this->postTelegramApi($token, 'editMessageReplyMarkup', $payload);
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     */
    private function answerCallbackQuery(array $callbackQuery, string $text, bool $alert): void
    {
        $callbackQueryId = isset($callbackQuery['id']) ? (string) $callbackQuery['id'] : '';
        if ($callbackQueryId === '') {
            return;
        }

        $token = $this->botToken();
        if ($token === '') {
            return;
        }

        try {
            Http::timeout(10)
                ->acceptJson()
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
                    'callback_query_id' => $callbackQueryId,
                    'text' => mb_substr($text, 0, 200, 'UTF-8'),
                    'show_alert' => $alert,
                ]);
        } catch (Throwable $e) {
            Log::warning('support-chat telegram: answerCallbackQuery failed', [
                'exception' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postTelegramApi(string $token, string $method, array $payload): bool
    {
        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/{$method}", $payload);
        } catch (Throwable $e) {
            Log::warning('support-chat telegram: api_call_failed', [
                'method' => $method,
                'exception' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('support-chat telegram: api_call_http_error', [
                'method' => $method,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 300, 'UTF-8'),
            ]);

            return false;
        }

        $data = $response->json();

        return is_array($data) && ! empty($data['ok']);
    }

    private function botToken(): string
    {
        return trim((string) config('support_chat.telegram.bot_token', ''));
    }

    /**
     * @param  mixed  $a
     * @param  mixed  $b
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
}
