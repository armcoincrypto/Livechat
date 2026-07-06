<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAiLearningEvent;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records AI suggestion snapshots and operator outcomes for offline learning analysis.
 * Never modifies live prompts or sends messages to visitors.
 */
final class SupportAiLearningService
{
    /** @var list<string> */
    private const SAFETY_FLAG_PATTERNS = [
        'fake_eta' => '/\b(?:within|in)\s+\d+\s*(?:min(?:ute)?s?|hours?|h)\b|\b(?:скоро|через\s+\d+\s*(?:мин|час))/iu',
        'fake_confirmation' => '/\b(?:confirmed|completed|done|успешно|подтвержден|завершен)\b/iu',
        'guarantee_claim' => '/\b(?:guarantee|guaranteed|гарантир|100\s*%)\b/iu',
        'funds_safe_claim' => '/\b(?:funds?\s+(?:are\s+)?safe|деньги\s+в\s+безопас|средства\s+в\s+безопас)/iu',
        'asks_known_data_again' => '/\b(?:please\s+(?:send|provide|share)|пришлите|отправьте|укажите)\b.*\b(?:order|заявк|tx|hash|сеть|network)/iu',
        'excessive_greeting' => '/^(?:hello|hi|привет|здравствуй)[!.?\s]{0,12}$/iu',
        'unknown_status_claim' => '/\b(?:already\s+(?:sent|processed|paid)|уже\s+(?:отправлен|обработан|выплачен))\b/iu',
    ];

    public function isEnabled(): bool
    {
        if (! filter_var(env('SUPPORT_AI_LEARNING_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return Schema::hasTable('support_ai_learning_events');
    }

    /**
     * @param  array<string, mixed>  $draftResult
     * @param  array<string, mixed>  $context
     */
    public function recordAiSuggestions(
        SupportConversation $conversation,
        SupportMessage $triggerMessage,
        array $draftResult,
        array $context = [],
        string $source = 'telegram',
    ): ?SupportAiLearningEvent {
        if (! $this->isEnabled()) {
            return null;
        }

        try {
            $options = $this->extractSuggestionTexts($draftResult);
            if ($options === []) {
                return null;
            }

            $sanitizedOptions = [];
            $hashes = [];
            foreach ($options as $index => $option) {
                $text = $this->sanitizeLearningText((string) ($option['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $hash = $this->hashSuggestion($text);
                $sanitizedOptions[] = [
                    'index' => $index,
                    'label' => (string) ($option['label'] ?? ''),
                    'style' => (string) ($option['style'] ?? ''),
                    'text' => $text,
                    'suggestion_slot' => $index,
                    'suggestion_hash' => $hash,
                    'suggestion_preview' => $this->previewText($text),
                ];
                $hashes[] = $hash;
            }

            if ($sanitizedOptions === []) {
                return null;
            }

            $fingerprint = $this->buildEventFingerprint(
                (int) $conversation->id,
                (int) $triggerMessage->id,
                $source,
                $hashes,
            );

            $existing = $this->findEventByFingerprint($fingerprint);
            if ($existing !== null) {
                return $existing;
            }

            $safetyFlags = $this->detectSafetyFlags(
                implode("\n", array_column($sanitizedOptions, 'text'))
            );

            $lineage = is_array($context['lineage'] ?? null) ? $context['lineage'] : [];

            $aiRequestId = (string) ($context['ai_request_id'] ?? $fingerprint);

            $event = new SupportAiLearningEvent;
            $event->conversation_id = (int) $conversation->id;
            $event->message_id = (int) $triggerMessage->id;
            $event->ai_request_id = $this->truncate($aiRequestId, 64);
            $event->intent = $this->truncate((string) ($context['intent'] ?? 'unknown_context'), 64);
            $event->conversation_stage = $this->truncate((string) ($context['conversation_stage'] ?? 'unknown'), 64);
            $event->language = $this->truncate((string) ($draftResult['language'] ?? $context['language'] ?? 'en'), 16);
            $event->suggestions = $sanitizedOptions;
            $event->suggestion_hashes = $hashes;
            $event->safety_flags = $safetyFlags !== [] ? $safetyFlags : null;
            $metadata = [
                'source' => $source,
                'confidence' => (string) ($draftResult['confidence'] ?? 'medium'),
                'operator_confidence' => (string) ($draftResult['operator_confidence'] ?? $draftResult['confidence'] ?? 'medium'),
                'choices' => (int) ($draftResult['choices'] ?? count($sanitizedOptions)),
                'event_fingerprint' => $fingerprint,
                'conversation_id' => (int) $conversation->id,
                'visitor_message_id' => (int) $triggerMessage->id,
                'generated_at' => now()->toIso8601String(),
                'telegram_ai_message_id' => isset($lineage['telegram_ai_message_id'])
                    ? (int) $lineage['telegram_ai_message_id']
                    : null,
                'telegram_reply_to_message_id' => isset($lineage['telegram_reply_to_message_id'])
                    ? (int) $lineage['telegram_reply_to_message_id']
                    : null,
                'lineage' => [
                    'suggestion_hashes' => $hashes,
                    'suggestion_previews' => array_values(array_map(
                        static fn (array $row): string => (string) ($row['suggestion_preview'] ?? ''),
                        $sanitizedOptions,
                    )),
                ],
            ];

            if (is_array($draftResult['ux'] ?? null) && $draftResult['ux'] !== []) {
                $metadata['ux'] = $draftResult['ux'];
            }

            $overlay = $draftResult['learning_overlay'] ?? $context['learning_overlay'] ?? null;
            if (is_array($overlay) && ! empty($overlay['overlay_enabled'])) {
                $metadata['overlay_enabled'] = true;
                $metadata['overlay_candidate_ids'] = is_array($overlay['overlay_candidate_ids'] ?? null)
                    ? array_values(array_map(static fn ($id): int => (int) $id, $overlay['overlay_candidate_ids']))
                    : [];
                $metadata['overlay_candidate_count'] = (int) ($overlay['overlay_candidate_count'] ?? count($metadata['overlay_candidate_ids']));
            }

            $event->metadata = $metadata;
            $event->save();

            return $event;
        } catch (Throwable $e) {
            Log::warning('support-chat ai:learning_record_failed', [
                'stage' => 'ai_suggestions',
                'support_conversation_id' => $conversation->id,
                'support_message_id' => $triggerMessage->id,
                'exception' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);

            return null;
        }
    }

    public function recordOperatorReply(
        SupportConversation $conversation,
        SupportMessage $operatorMessage,
        ?SupportMessage $visitorAnchor = null,
    ): ?SupportAiLearningEvent {
        if (! $this->isEnabled()) {
            return null;
        }

        if ($operatorMessage->sender_type !== SupportMessage::SENDER_OPERATOR) {
            return null;
        }

        try {
            $replyText = $this->sanitizeLearningText((string) $operatorMessage->body);
            if ($replyText === '') {
                return null;
            }

            $event = $this->findLearningEventForOperatorReply(
                $conversation,
                $operatorMessage,
                $visitorAnchor,
            );

            if ($event === null) {
                return null;
            }

            $suggestions = is_array($event->suggestions) ? $event->suggestions : [];
            $match = $this->detectOperatorUsedSuggestion($suggestions, $replyText);

            $event->operator_reply = $replyText;
            $event->operator_reply_hash = $this->hashSuggestion($replyText);
            $event->selected_suggestion_index = $match['selected_index'];
            $event->edit_distance_ratio = $match['best_ratio'];
            $event->operator_edited = $match['operator_edited'];
            $event->outcome = $match['outcome'];
            $event->quality_score = $this->scoreOperatorOutcome($match, $event);
            $event->safety_flags = array_values(array_unique(array_merge(
                is_array($event->safety_flags) ? $event->safety_flags : [],
                $this->detectSafetyFlags($replyText),
            )));
            $event->metadata = array_merge(is_array($event->metadata) ? $event->metadata : [], [
                'operator_message_id' => (int) $operatorMessage->id,
                'match_type' => $match['match_type'],
            ]);
            $event->save();

            return $event;
        } catch (Throwable $e) {
            Log::warning('support-chat ai:learning_record_failed', [
                'stage' => 'operator_reply',
                'support_conversation_id' => $conversation->id,
                'support_message_id' => $operatorMessage->id,
                'exception' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);

            return null;
        }
    }

    public function sanitizeLearningText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $text = preg_replace('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/', '[email-redacted]', $text) ?? $text;
        $text = preg_replace('/\b(?:seed phrase|mnemonic|private key|secret phrase|recovery phrase)\b[^\n]*/iu', '[secret-redacted]', $text) ?? $text;
        $text = preg_replace('/\bsk-[a-zA-Z0-9]{10,}\b/', '[api-key-redacted]', $text) ?? $text;
        $text = preg_replace('/\b\d{8,10}:[A-Za-z0-9_-]{30,}\b/', '[bot-token-redacted]', $text) ?? $text;
        $text = preg_replace('/\b(?:DB_PASSWORD|APP_KEY|AWS_SECRET)[^\s]*/i', '[secret-redacted]', $text) ?? $text;

        if (mb_strlen($text, 'UTF-8') > 4000) {
            $text = mb_substr($text, 0, 3999, 'UTF-8').'…';
        }

        return trim($text);
    }

    public function hashSuggestion(string $text): string
    {
        return hash('sha256', $this->normalizeForMatch($text));
    }

    public function computeEditDistanceRatio(string $a, string $b): float
    {
        $na = $this->normalizeForMatch($a);
        $nb = $this->normalizeForMatch($b);

        if ($na === '' && $nb === '') {
            return 1.0;
        }

        if ($na === '' || $nb === '') {
            return 0.0;
        }

        if ($na === $nb) {
            return 1.0;
        }

        $maxLen = max(mb_strlen($na, 'UTF-8'), mb_strlen($nb, 'UTF-8'));
        if ($maxLen === 0) {
            return 1.0;
        }

        $distance = levenshtein(
            $this->asciiFold($na),
            $this->asciiFold($nb),
        );

        return max(0.0, min(1.0, 1.0 - ($distance / $maxLen)));
    }

    /**
     * @param  list<array<string, mixed>>  $suggestions
     * @return array{
     *     selected_index: int|null,
     *     best_ratio: float|null,
     *     operator_edited: bool,
     *     outcome: string,
     *     match_type: string
     * }
     */
    public function detectOperatorUsedSuggestion(array $suggestions, string $operatorReply): array
    {
        $replyNorm = $this->normalizeForMatch($operatorReply);
        if ($replyNorm === '') {
            return [
                'selected_index' => null,
                'best_ratio' => null,
                'operator_edited' => false,
                'outcome' => 'empty_reply',
                'match_type' => 'none',
            ];
        }

        $bestIndex = null;
        $bestRatio = 0.0;

        foreach ($suggestions as $suggestion) {
            $text = (string) ($suggestion['text'] ?? '');
            if ($text === '') {
                continue;
            }

            $ratio = $this->computeEditDistanceRatio($text, $operatorReply);
            $index = isset($suggestion['index']) ? (int) $suggestion['index'] : null;

            if ($ratio > $bestRatio) {
                $bestRatio = $ratio;
                $bestIndex = $index;
            }
        }

        if ($bestRatio >= 0.98) {
            return [
                'selected_index' => $bestIndex,
                'best_ratio' => round($bestRatio, 4),
                'operator_edited' => false,
                'outcome' => 'used_exact',
                'match_type' => 'exact',
            ];
        }

        if ($bestRatio >= 0.82) {
            return [
                'selected_index' => $bestIndex,
                'best_ratio' => round($bestRatio, 4),
                'operator_edited' => true,
                'outcome' => 'used_near',
                'match_type' => 'near',
            ];
        }

        if ($bestRatio >= 0.55) {
            return [
                'selected_index' => $bestIndex,
                'best_ratio' => round($bestRatio, 4),
                'operator_edited' => true,
                'outcome' => 'rewritten',
                'match_type' => 'partial',
            ];
        }

        return [
            'selected_index' => null,
            'best_ratio' => $bestRatio > 0 ? round($bestRatio, 4) : null,
            'operator_edited' => true,
            'outcome' => 'ignored',
            'match_type' => 'none',
        ];
    }

    /**
     * @return list<string>
     */
    public function detectSafetyFlags(string $text): array
    {
        $flags = [];
        foreach (self::SAFETY_FLAG_PATTERNS as $flag => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $flags[] = $flag;
            }
        }

        return $flags;
    }

    /**
     * @param  array<string, mixed>  $draftResult
     * @return list<array{label: string, style: string, text: string, index?: int}>
     */
    private function extractSuggestionTexts(array $draftResult): array
    {
        $options = is_array($draftResult['options'] ?? null) ? $draftResult['options'] : [];
        if ($options !== []) {
            $out = [];
            foreach (array_values($options) as $i => $option) {
                if (! is_array($option)) {
                    continue;
                }
                $out[] = [
                    'index' => $i,
                    'label' => (string) ($option['label'] ?? ''),
                    'style' => (string) ($option['style'] ?? ''),
                    'text' => (string) ($option['text'] ?? ''),
                ];
            }

            return $out;
        }

        $draft = trim((string) ($draftResult['draft'] ?? ''));
        if ($draft === '') {
            return [];
        }

        return [[
            'index' => 0,
            'label' => 'Draft',
            'style' => 'draft',
            'text' => $draft,
        ]];
    }

    /**
     * @param  list<string>  $hashes
     */
    public function buildEventFingerprint(
        int $conversationId,
        int $visitorMessageId,
        string $source,
        array $hashes,
    ): string {
        $sorted = $hashes;
        sort($sorted);

        return hash('sha256', implode(':', [
            (string) $conversationId,
            (string) $visitorMessageId,
            trim($source),
            implode(',', $sorted),
        ]));
    }

    public function findEventByFingerprint(string $fingerprint): ?SupportAiLearningEvent
    {
        if ($fingerprint === '') {
            return null;
        }

        return SupportAiLearningEvent::query()
            ->where('metadata->event_fingerprint', $fingerprint)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $lineage
     */
    public function enrichEventLineage(int $eventId, array $lineage): ?SupportAiLearningEvent
    {
        if (! $this->isEnabled() || $eventId < 1) {
            return null;
        }

        try {
            $event = SupportAiLearningEvent::query()->find($eventId);
            if ($event === null) {
                return null;
            }

            $metadata = is_array($event->metadata) ? $event->metadata : [];
            foreach (['telegram_ai_message_id', 'telegram_reply_to_message_id'] as $key) {
                if (array_key_exists($key, $lineage) && $lineage[$key] !== null) {
                    $metadata[$key] = (int) $lineage[$key];
                }
            }

            if (isset($lineage['lineage']) && is_array($lineage['lineage'])) {
                $existingLineage = is_array($metadata['lineage'] ?? null) ? $metadata['lineage'] : [];
                $metadata['lineage'] = array_merge($existingLineage, $lineage['lineage']);
            }

            $event->metadata = $metadata;
            $event->save();

            return $event;
        } catch (Throwable $e) {
            Log::warning('support-chat ai:learning_lineage_enrich_failed', [
                'learning_event_id' => $eventId,
                'exception' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);

            return null;
        }
    }

    public function findEventByTelegramAiMessageId(int $conversationId, int $telegramMessageId): ?SupportAiLearningEvent
    {
        if ($conversationId < 1 || $telegramMessageId < 1) {
            return null;
        }

        return SupportAiLearningEvent::query()
            ->where('conversation_id', $conversationId)
            ->where('metadata->telegram_ai_message_id', $telegramMessageId)
            ->orderByDesc('id')
            ->first();
    }

    public function findEventByVisitorMessageId(int $conversationId, int $visitorMessageId): ?SupportAiLearningEvent
    {
        if ($conversationId < 1 || $visitorMessageId < 1) {
            return null;
        }

        return SupportAiLearningEvent::query()
            ->where('conversation_id', $conversationId)
            ->where('message_id', $visitorMessageId)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  list<SupportAiLearningEvent>  $events
     * @return array{event: SupportAiLearningEvent, suggestion_index: int, suggestion_text: string, similarity_score: float}|null
     */
    public function pickBestEventByTextSimilarity(array $events, string $operatorText): ?array
    {
        $bestEvent = null;
        $bestSuggestion = null;
        $bestScore = -1.0;

        foreach ($events as $event) {
            $suggestions = is_array($event->suggestions) ? $event->suggestions : [];
            foreach ($suggestions as $index => $suggestion) {
                if (! is_array($suggestion)) {
                    continue;
                }
                $text = trim((string) ($suggestion['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $score = $this->computeEditDistanceRatio($text, $operatorText);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestEvent = $event;
                    $bestSuggestion = [
                        'index' => isset($suggestion['index']) ? (int) $suggestion['index'] : $index,
                        'text' => $text,
                        'score' => $score,
                    ];
                }
            }
        }

        if ($bestEvent === null || $bestSuggestion === null) {
            return null;
        }

        return [
            'event' => $bestEvent,
            'suggestion_index' => $bestSuggestion['index'],
            'suggestion_text' => $bestSuggestion['text'],
            'similarity_score' => round((float) $bestSuggestion['score'], 4),
        ];
    }

    private function previewText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text, 'UTF-8') <= 160) {
            return $text;
        }

        return mb_substr($text, 0, 157, 'UTF-8').'…';
    }

    private function findLearningEventForOperatorReply(
        SupportConversation $conversation,
        SupportMessage $operatorMessage,
        ?SupportMessage $visitorAnchor,
    ): ?SupportAiLearningEvent {
        $conversationId = (int) $conversation->id;

        $replyToTelegramId = (int) ($operatorMessage->telegram_reply_to_message_id ?? 0);
        if ($replyToTelegramId > 0) {
            $byAiTelegram = $this->findEventByTelegramAiMessageId($conversationId, $replyToTelegramId);
            if ($byAiTelegram !== null && $byAiTelegram->operator_reply === null) {
                return $byAiTelegram;
            }
        }

        $anchorId = $visitorAnchor?->id;
        if ($anchorId === null) {
            $meta = is_array($operatorMessage->metadata) ? $operatorMessage->metadata : [];
            $tele = is_array($meta['telegram'] ?? null) ? $meta['telegram'] : [];
            $anchorId = isset($tele['reply_anchor_support_message_id'])
                ? (int) $tele['reply_anchor_support_message_id']
                : null;
        }

        if ($anchorId !== null && $anchorId > 0) {
            $byVisitor = $this->findEventByVisitorMessageId($conversationId, $anchorId);
            if ($byVisitor !== null && $byVisitor->operator_reply === null) {
                return $byVisitor;
            }
        }

        $byOperator = SupportAiLearningEvent::query()
            ->where('conversation_id', $conversationId)
            ->where('metadata->operator_message_id', (int) $operatorMessage->id)
            ->orderByDesc('id')
            ->first();
        if ($byOperator !== null) {
            return $byOperator;
        }

        $windowMinutes = max(1, min(120, (int) config('support_chat.ai.acceptance_tracking.recent_window_minutes', 15)));
        $operatorAt = $operatorMessage->created_at ?? now();
        $since = $operatorAt->copy()->subMinutes($windowMinutes);

        return SupportAiLearningEvent::query()
            ->where('conversation_id', $conversationId)
            ->whereNull('operator_reply')
            ->whereNotNull('suggestions')
            ->where('created_at', '<=', $operatorAt)
            ->where('created_at', '>=', $since)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array{selected_index: int|null, best_ratio: float|null, operator_edited: bool, outcome: string, match_type: string}  $match
     */
    private function scoreOperatorOutcome(array $match, SupportAiLearningEvent $event): ?float
    {
        $base = match ($match['outcome']) {
            'used_exact' => 90.0,
            'used_near' => 75.0,
            'rewritten' => 55.0,
            'ignored' => 35.0,
            default => 40.0,
        };

        $flags = is_array($event->safety_flags) ? count($event->safety_flags) : 0;
        $base -= min(30.0, $flags * 8.0);

        return max(0.0, min(100.0, round($base, 2)));
    }

    private function normalizeForMatch(string $text): string
    {
        $text = mb_strtolower($this->sanitizeLearningText($text), 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s#]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function asciiFold(string $text): string
    {
        $ascii = Str::ascii($text);

        return $ascii !== '' ? $ascii : $text;
    }

    private function truncate(string $value, int $max): string
    {
        if (mb_strlen($value, 'UTF-8') <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max, 'UTF-8');
    }
}
