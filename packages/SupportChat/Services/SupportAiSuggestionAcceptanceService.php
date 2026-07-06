<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAiLearningEvent;
use App\Models\SupportAiSuggestionUsage;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tracks whether operators used AI suggestions (telemetry only — never blocks replies).
 */
final class SupportAiSuggestionAcceptanceService
{
    /** @var list<string> */
    private const GENERIC_REPLY_PATTERNS = [
        '/^(?:hello|hi|hey|ok|okay|thanks|thank you|please wait|sure|done|got it)[\s!.?]*$/iu',
        '/^(?:спасибо|здравствуйте|привет|ок|ожидайте|хорошо|понял)[\s!.?👍]*$/iu',
    ];

    public function __construct(
        private readonly SupportAiLearningService $learning,
        private readonly SupportAiOperatorUsageService $operatorUsage,
    ) {}

    public function isEnabled(): bool
    {
        if (! filter_var(config('support_chat.ai.acceptance_tracking.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        try {
            return Schema::hasTable('support_ai_suggestion_usages');
        } catch (Throwable) {
            return false;
        }
    }

    public function recordForOperatorReply(
        SupportConversation $conversation,
        SupportMessage $operatorMessage,
        ?SupportMessage $visitorAnchor = null,
    ): ?SupportAiSuggestionUsage {
        if (! $this->isEnabled()) {
            return null;
        }

        if ($operatorMessage->sender_type !== SupportMessage::SENDER_OPERATOR) {
            return null;
        }

        try {
            if ($this->usageExistsForOperatorMessage((int) $operatorMessage->id)) {
                return SupportAiSuggestionUsage::query()
                    ->where('operator_message_id', (int) $operatorMessage->id)
                    ->first();
            }

            $operatorText = $this->learning->sanitizeLearningText((string) $operatorMessage->body);
            if ($operatorText === '') {
                return null;
            }

            $match = $this->resolveSuggestionMatch($conversation, $operatorMessage, $operatorText, $visitorAnchor);
            $decision = $this->classifyDecision($operatorText, $match);

            $usage = new SupportAiSuggestionUsage;
            $usage->conversation_id = (int) $conversation->id;
            $usage->visitor_message_id = $match['visitor_message_id'];
            $usage->suggestion_id = $match['suggestion_id'];
            $usage->operator_message_id = (int) $operatorMessage->id;
            $usage->learning_event_id = $match['learning_event_id'];
            $usage->decision = $decision;
            $usage->edit_distance = $match['edit_distance'];
            $usage->similarity_score = $match['similarity_score'];
            $usage->matched_by = $match['matched_by'];
            $usage->suggestion_text_hash = $match['suggestion_text_hash'];
            $usage->operator_text_hash = $this->learning->hashSuggestion($operatorText);
            $usage->suggestion_preview = $match['suggestion_preview'];
            $usage->operator_reply_preview = $this->previewText($operatorText);
            $usage->metadata = [
                'intent' => $match['intent'],
                'source' => $match['source'],
                'event_fingerprint' => $this->resolveEventFingerprint($match['learning_event_id']),
            ];
            $usage->save();

            $this->operatorUsage->recordOperatorOutcome(
                $usage,
                $conversation,
                $operatorMessage,
                $visitorAnchor,
            );

            return $usage;
        } catch (Throwable $e) {
            Log::warning('support-chat ai:acceptance_record_failed', [
                'support_conversation_id' => $conversation->id,
                'support_message_id' => $operatorMessage->id,
                'exception' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);

            return null;
        }
    }

    public function recordForTelegramButton(
        SupportAiLearningEvent $event,
        string $decision,
        ?int $telegramUserId = null,
        int $suggestionIndex = 0,
    ): ?SupportAiSuggestionUsage {
        if (! $this->isEnabled()) {
            return null;
        }

        if (! in_array($decision, [
            SupportAiSuggestionUsage::DECISION_ACCEPTED_EXACT,
            SupportAiSuggestionUsage::DECISION_ACCEPTED_MODIFIED,
            SupportAiSuggestionUsage::DECISION_IGNORED,
        ], true)) {
            return null;
        }

        try {
            $existing = SupportAiSuggestionUsage::query()
                ->where('learning_event_id', (int) $event->id)
                ->where('matched_by', SupportAiSuggestionUsage::MATCHED_BY_OPERATOR_TELEGRAM_BUTTON)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $suggestions = is_array($event->suggestions) ? $event->suggestions : [];
            $suggestionText = '';
            foreach (array_values($suggestions) as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $slot = isset($row['index']) ? (int) $row['index'] : $index;
                if ($slot === $suggestionIndex || ($suggestionIndex === 0 && $index === 0)) {
                    $suggestionText = $this->learning->sanitizeLearningText((string) ($row['text'] ?? ''));
                    break;
                }
            }

            if ($suggestionText === '' && isset($suggestions[0]) && is_array($suggestions[0])) {
                $suggestionText = $this->learning->sanitizeLearningText((string) ($suggestions[0]['text'] ?? ''));
            }

            $metadata = is_array($event->metadata) ? $event->metadata : [];

            $usage = new SupportAiSuggestionUsage;
            $usage->conversation_id = (int) $event->conversation_id;
            $usage->visitor_message_id = $event->message_id !== null ? (int) $event->message_id : null;
            $usage->suggestion_id = $suggestionIndex;
            $usage->operator_message_id = null;
            $usage->learning_event_id = (int) $event->id;
            $usage->decision = $decision;
            $usage->edit_distance = null;
            $usage->similarity_score = null;
            $usage->matched_by = SupportAiSuggestionUsage::MATCHED_BY_OPERATOR_TELEGRAM_BUTTON;
            $usage->suggestion_text_hash = $suggestionText !== '' ? $this->learning->hashSuggestion($suggestionText) : null;
            $usage->operator_text_hash = null;
            $usage->suggestion_preview = $suggestionText !== '' ? $this->previewText($suggestionText) : null;
            $usage->operator_reply_preview = null;
            $usage->metadata = [
                'intent' => $event->intent,
                'source' => isset($metadata['source']) ? (string) $metadata['source'] : null,
                'event_fingerprint' => $metadata['event_fingerprint'] ?? null,
                'telegram_user_id' => $telegramUserId,
                'recorded_via' => 'telegram_button',
                'recorded_at' => now()->toIso8601String(),
            ];
            $usage->save();

            return $usage;
        } catch (Throwable $e) {
            Log::warning('support-chat ai:acceptance_button_failed', [
                'learning_event_id' => $event->id,
                'exception' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);

            return null;
        }
    }

    /**
     * @return array{
     *     learning_event_id: int|null,
     *     visitor_message_id: int|null,
     *     suggestion_id: int|null,
     *     suggestion_text: string|null,
     *     suggestion_preview: string|null,
     *     suggestion_text_hash: string|null,
     *     similarity_score: float|null,
     *     edit_distance: int|null,
     *     matched_by: string,
     *     intent: string|null,
     *     source: string|null
     * }
     */
    private function resolveSuggestionMatch(
        SupportConversation $conversation,
        SupportMessage $operatorMessage,
        string $operatorText,
        ?SupportMessage $visitorAnchor,
    ): array {
        $empty = $this->emptyMatchPayload();
        $conversationId = (int) $conversation->id;
        $anchorId = $this->resolveVisitorAnchorId($operatorMessage, $visitorAnchor);
        $replyToTelegramId = (int) ($operatorMessage->telegram_reply_to_message_id ?? 0);

        if ($replyToTelegramId > 0) {
            $event = $this->learning->findEventByTelegramAiMessageId($conversationId, $replyToTelegramId);
            if ($event !== null) {
                return $this->buildMatchFromEvent(
                    $event,
                    $operatorText,
                    SupportAiSuggestionUsage::MATCHED_BY_TELEGRAM_AI_MESSAGE,
                );
            }
        }

        if ($anchorId !== null && $anchorId > 0) {
            $event = $this->learning->findEventByVisitorMessageId($conversationId, $anchorId);
            if ($event !== null) {
                return $this->buildMatchFromEvent(
                    $event,
                    $operatorText,
                    SupportAiSuggestionUsage::MATCHED_BY_VISITOR_ANCHOR,
                );
            }
        }

        $lineageEvent = $this->findLineageEvent($conversation, $operatorMessage, $anchorId);
        if ($lineageEvent !== null) {
            return $this->buildMatchFromEvent(
                $lineageEvent,
                $operatorText,
                SupportAiSuggestionUsage::MATCHED_BY_LINEAGE,
            );
        }

        if ($anchorId !== null && $anchorId > 0) {
            $fingerprintEvent = $this->findEventByVisitorFingerprint($conversationId, $anchorId);
            if ($fingerprintEvent !== null) {
                return $this->buildMatchFromEvent(
                    $fingerprintEvent,
                    $operatorText,
                    SupportAiSuggestionUsage::MATCHED_BY_EVENT_FINGERPRINT,
                );
            }
        }

        $recentEvent = $this->findRecentConversationEvent($conversation, $operatorMessage);
        if ($recentEvent !== null) {
            return $this->buildMatchFromEvent(
                $recentEvent,
                $operatorText,
                SupportAiSuggestionUsage::MATCHED_BY_SAME_CONVERSATION_RECENT,
            );
        }

        $textMatch = $this->findTextSimilarityEvent($conversation, $operatorMessage, $operatorText);
        if ($textMatch !== null) {
            return $textMatch;
        }

        return $empty;
    }

    /**
     * @return array{
     *     learning_event_id: int|null,
     *     visitor_message_id: int|null,
     *     suggestion_id: int|null,
     *     suggestion_text: string|null,
     *     suggestion_preview: string|null,
     *     suggestion_text_hash: string|null,
     *     similarity_score: float|null,
     *     edit_distance: int|null,
     *     matched_by: string,
     *     intent: string|null,
     *     source: string|null
     * }
     */
    private function emptyMatchPayload(): array
    {
        return [
            'learning_event_id' => null,
            'visitor_message_id' => null,
            'suggestion_id' => null,
            'suggestion_text' => null,
            'suggestion_preview' => null,
            'suggestion_text_hash' => null,
            'similarity_score' => null,
            'edit_distance' => null,
            'matched_by' => SupportAiSuggestionUsage::MATCHED_BY_FALLBACK_UNKNOWN,
            'intent' => null,
            'source' => null,
        ];
    }

    private function findEventByVisitorFingerprint(int $conversationId, int $visitorMessageId): ?SupportAiLearningEvent
    {
        return SupportAiLearningEvent::query()
            ->where('conversation_id', $conversationId)
            ->where('message_id', $visitorMessageId)
            ->whereNotNull('metadata->event_fingerprint')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findTextSimilarityEvent(
        SupportConversation $conversation,
        SupportMessage $operatorMessage,
        string $operatorText,
    ): ?array {
        $windowMinutes = max(1, min(120, (int) config('support_chat.ai.acceptance_tracking.recent_window_minutes', 15)));
        $operatorAt = $operatorMessage->created_at ?? now();
        $since = $operatorAt->copy()->subMinutes($windowMinutes);

        $events = SupportAiLearningEvent::query()
            ->where('conversation_id', (int) $conversation->id)
            ->whereNotNull('suggestions')
            ->where('created_at', '<=', $operatorAt)
            ->where('created_at', '>=', $since)
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->all();

        if ($events === []) {
            return null;
        }

        $best = $this->learning->pickBestEventByTextSimilarity($events, $operatorText);
        if ($best === null) {
            return null;
        }

        $event = $best['event'];
        $metadata = is_array($event->metadata) ? $event->metadata : [];
        $text = $this->learning->sanitizeLearningText((string) $best['suggestion_text']);

        return [
            'learning_event_id' => (int) $event->id,
            'visitor_message_id' => $event->message_id !== null ? (int) $event->message_id : null,
            'suggestion_id' => (int) $best['suggestion_index'],
            'suggestion_text' => $text,
            'suggestion_preview' => $this->previewText($text),
            'suggestion_text_hash' => $this->learning->hashSuggestion($text),
            'similarity_score' => (float) $best['similarity_score'],
            'edit_distance' => $this->computeEditDistance($text, $operatorText),
            'matched_by' => SupportAiSuggestionUsage::MATCHED_BY_TEXT_SIMILARITY,
            'intent' => $event->intent,
            'source' => isset($metadata['source']) ? (string) $metadata['source'] : null,
        ];
    }

    private function resolveVisitorAnchorId(
        SupportMessage $operatorMessage,
        ?SupportMessage $visitorAnchor,
    ): ?int {
        $anchorId = $visitorAnchor?->id;
        if ($anchorId !== null && $anchorId > 0) {
            return (int) $anchorId;
        }

        $meta = is_array($operatorMessage->metadata) ? $operatorMessage->metadata : [];
        $tele = is_array($meta['telegram'] ?? null) ? $meta['telegram'] : [];
        $fromMeta = isset($tele['reply_anchor_support_message_id'])
            ? (int) $tele['reply_anchor_support_message_id']
            : null;

        return ($fromMeta !== null && $fromMeta > 0) ? $fromMeta : null;
    }

    private function findLineageEvent(
        SupportConversation $conversation,
        SupportMessage $operatorMessage,
        ?int $anchorId,
    ): ?SupportAiLearningEvent {
        $byOperator = SupportAiLearningEvent::query()
            ->where('conversation_id', (int) $conversation->id)
            ->where('metadata->operator_message_id', (int) $operatorMessage->id)
            ->orderByDesc('id')
            ->first();

        if ($byOperator !== null) {
            return $byOperator;
        }

        if ($anchorId !== null && $anchorId > 0) {
            $byAnchor = SupportAiLearningEvent::query()
                ->where('conversation_id', (int) $conversation->id)
                ->where('message_id', $anchorId)
                ->orderByDesc('id')
                ->first();

            if ($byAnchor !== null) {
                return $byAnchor;
            }
        }

        return null;
    }

    private function findRecentConversationEvent(
        SupportConversation $conversation,
        SupportMessage $operatorMessage,
    ): ?SupportAiLearningEvent {
        $windowMinutes = max(1, min(120, (int) config('support_chat.ai.acceptance_tracking.recent_window_minutes', 15)));
        $operatorAt = $operatorMessage->created_at ?? now();
        $since = $operatorAt->copy()->subMinutes($windowMinutes);

        return SupportAiLearningEvent::query()
            ->where('conversation_id', (int) $conversation->id)
            ->whereNotNull('suggestions')
            ->where('created_at', '<=', $operatorAt)
            ->where('created_at', '>=', $since)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{
     *     learning_event_id: int|null,
     *     visitor_message_id: int|null,
     *     suggestion_id: int|null,
     *     suggestion_text: string|null,
     *     suggestion_preview: string|null,
     *     suggestion_text_hash: string|null,
     *     similarity_score: float|null,
     *     edit_distance: int|null,
     *     matched_by: string,
     *     intent: string|null,
     *     source: string|null
     * }
     */
    private function buildMatchFromEvent(
        SupportAiLearningEvent $event,
        string $operatorText,
        string $matchedBy,
    ): array {
        $suggestions = is_array($event->suggestions) ? $event->suggestions : [];
        $best = $this->pickBestSuggestion($suggestions, $operatorText);

        $metadata = is_array($event->metadata) ? $event->metadata : [];

        if ($best === null) {
            return [
                'learning_event_id' => (int) $event->id,
                'visitor_message_id' => $event->message_id !== null ? (int) $event->message_id : null,
                'suggestion_id' => null,
                'suggestion_text' => null,
                'suggestion_preview' => null,
                'suggestion_text_hash' => null,
                'similarity_score' => null,
                'edit_distance' => null,
                'matched_by' => SupportAiSuggestionUsage::MATCHED_BY_FALLBACK_UNKNOWN,
                'intent' => $event->intent,
                'source' => isset($metadata['source']) ? (string) $metadata['source'] : null,
            ];
        }

        $resolvedMatchedBy = $matchedBy;

        return [
            'learning_event_id' => (int) $event->id,
            'visitor_message_id' => $event->message_id !== null ? (int) $event->message_id : null,
            'suggestion_id' => $best['suggestion_id'],
            'suggestion_text' => $best['suggestion_text'],
            'suggestion_preview' => $this->previewText($best['suggestion_text']),
            'suggestion_text_hash' => $this->learning->hashSuggestion($best['suggestion_text']),
            'similarity_score' => $best['similarity_score'],
            'edit_distance' => $best['edit_distance'],
            'matched_by' => $resolvedMatchedBy,
            'intent' => $event->intent,
            'source' => isset($metadata['source']) ? (string) $metadata['source'] : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $suggestions
     * @return array{suggestion_id: int, suggestion_text: string, similarity_score: float, edit_distance: int}|null
     */
    private function pickBestSuggestion(array $suggestions, string $operatorText): ?array
    {
        $best = null;
        $bestScore = -1.0;

        foreach (array_values($suggestions) as $index => $suggestion) {
            if (! is_array($suggestion)) {
                continue;
            }

            $text = trim((string) ($suggestion['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $sanitized = $this->learning->sanitizeLearningText($text);
            $score = $this->learning->computeEditDistanceRatio($sanitized, $operatorText);
            $suggestionId = isset($suggestion['index']) ? (int) $suggestion['index'] : $index;

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'suggestion_id' => $suggestionId,
                    'suggestion_text' => $sanitized,
                    'similarity_score' => round($score, 4),
                    'edit_distance' => $this->computeEditDistance($sanitized, $operatorText),
                ];
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $match
     */
    private function classifyDecision(string $operatorText, array $match): string
    {
        $suggestionText = trim((string) ($match['suggestion_text'] ?? ''));
        if ($suggestionText === '') {
            return SupportAiSuggestionUsage::DECISION_UNKNOWN;
        }

        $operatorNorm = $this->normalizeForComparison($operatorText);
        $suggestionNorm = $this->normalizeForComparison($suggestionText);

        if ($this->isTooShortForMeaningfulMatch($operatorNorm)
            || $this->isTooShortForMeaningfulMatch($suggestionNorm)) {
            return SupportAiSuggestionUsage::DECISION_UNKNOWN;
        }

        if ($this->isGenericOnlyReply($operatorNorm)) {
            return SupportAiSuggestionUsage::DECISION_IGNORED;
        }

        $score = $match['similarity_score'];
        if ($score === null) {
            return SupportAiSuggestionUsage::DECISION_UNKNOWN;
        }

        if ($score >= $this->exactThreshold()) {
            return SupportAiSuggestionUsage::DECISION_ACCEPTED_EXACT;
        }

        if ($score >= $this->modifiedThreshold()) {
            return SupportAiSuggestionUsage::DECISION_ACCEPTED_MODIFIED;
        }

        return SupportAiSuggestionUsage::DECISION_IGNORED;
    }

    private function usageExistsForOperatorMessage(int $operatorMessageId): bool
    {
        return SupportAiSuggestionUsage::query()
            ->where('operator_message_id', $operatorMessageId)
            ->exists();
    }

    private function normalizeForComparison(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = $this->learning->sanitizeLearningText($text);
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function isTooShortForMeaningfulMatch(string $normalizedText): bool
    {
        $min = max(8, min(80, (int) config('support_chat.ai.acceptance_tracking.min_meaningful_length', 20)));

        return mb_strlen($normalizedText, 'UTF-8') < $min;
    }

    private function isGenericOnlyReply(string $normalizedText): bool
    {
        foreach (self::GENERIC_REPLY_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalizedText) === 1) {
                return true;
            }
        }

        return false;
    }

    private function computeEditDistance(string $a, string $b): int
    {
        $na = Str::ascii(mb_strtolower(trim($a), 'UTF-8'));
        $nb = Str::ascii(mb_strtolower(trim($b), 'UTF-8'));

        if ($na === $nb) {
            return 0;
        }

        if ($na === '' || $nb === '') {
            return max(mb_strlen($a, 'UTF-8'), mb_strlen($b, 'UTF-8'));
        }

        return levenshtein($na, $nb);
    }

    private function previewText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text, 'UTF-8') <= 300) {
            return $text;
        }

        return mb_substr($text, 0, 297, 'UTF-8').'…';
    }

    private function exactThreshold(): float
    {
        return max(0.9, min(1.0, (float) config('support_chat.ai.acceptance_tracking.exact_threshold', 0.98)));
    }

    private function modifiedThreshold(): float
    {
        return max(0.4, min(0.97, (float) config('support_chat.ai.acceptance_tracking.modified_threshold', 0.60)));
    }

    private function resolveEventFingerprint(?int $learningEventId): ?string
    {
        if ($learningEventId === null || $learningEventId < 1) {
            return null;
        }

        $metadata = SupportAiLearningEvent::query()
            ->whereKey($learningEventId)
            ->value('metadata');

        if (! is_array($metadata)) {
            return null;
        }

        $fingerprint = $metadata['event_fingerprint'] ?? null;

        return is_string($fingerprint) && $fingerprint !== '' ? $fingerprint : null;
    }
}
