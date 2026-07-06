<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use iEXPackages\SupportChat\Data\SupportAiPolicyIntentPatterns;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Operator-assist only: drafts replies for human review. Never auto-sends to visitors.
 */
final class SupportAiDraftService
{
    private const TELEGRAM_DRAFT_MAX = 2800;

    private const TELEGRAM_COPY_BLOCK_MAX = 280;

    /** Max recent messages loaded into AI context (hard cap). */
    private const MAX_RECENT_MESSAGES = 20;

    /** Max chars per message body in AI prompt context. */
    private const MAX_MESSAGE_CHARS = 1200;

    /** Max chars for conversation memory block. */
    private const MAX_MEMORY_CHARS = 6000;

    /** Max chars for full user prompt context block. */
    private const MAX_PROMPT_CONTEXT_CHARS = 10000;

    /** @var list<string> */
    private const TELEGRAM_OPTION_TITLES = [
        '1️⃣ Short',
        '2️⃣ Warm',
        '3️⃣ Detailed',
        '4️⃣ Clarify',
    ];

    /** @var list<string> */
    private const TELEGRAM_COMPACT_LABELS = [
        '1️⃣',
        '2️⃣',
        '3️⃣',
        '4️⃣',
    ];

    /** @var list<string> */
    private const OPTION_LABELS = [
        'Short professional',
        'Warm reassurance',
        'Detailed/checklist',
        'Clarifying question / next step',
    ];

    /** @var list<string> */
    private const OPTION_STYLES = [
        'short_professional',
        'warm_reassurance',
        'detailed_checklist',
        'clarifying_next_step',
    ];

    public function __construct(
        private readonly SupportAiLearningOverlayService $learningOverlay,
        private readonly SupportAiKnowledgeService $knowledge,
        private readonly SupportAiTemplateService $templates,
        private readonly SupportAiSuggestionUxService $ux,
        private readonly SupportAiOrderContextService $orderContext,
        private readonly SupportAiDirectionContextService $directionContext,
        private readonly SupportAiOperatorUsageService $operatorUsage,
    ) {}

    public function isEnabled(): bool
    {
        if (! filter_var(config('support_chat.ai.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (! filter_var(config('support_chat.ai.operator_assist_only', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $apiKey = trim((string) config('support_chat.ai.openai_api_key', ''));

        return $apiKey !== '';
    }

    public function isTelegramPreviewEnabled(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return filter_var(
            config('support_chat.ai.telegram_preview_enabled', config('support_chat.ai.enabled', false)),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function telegramChoices(): int
    {
        return max(1, min(4, (int) config('support_chat.ai.telegram_choices', 4)));
    }

    public function isTelegramSeparateMessageEnabled(): bool
    {
        if (! $this->isTelegramPreviewEnabled()) {
            return false;
        }

        return filter_var(
            config('support_chat.ai.telegram_separate_message', config('support_chat.ai.enabled', false)),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Standalone copy-friendly AI suggestions message for Telegram (HTML parse mode).
     */
    public function formatTelegramSeparateMessage(?array $result, int $maxChars = 4096): ?string
    {
        if ($result === null) {
            return null;
        }

        $options = $this->resolveDisplayOptions($result);
        if ($options === []) {
            return null;
        }

        $operatorConfidence = (string) ($result['operator_confidence'] ?? $result['confidence'] ?? 'medium');
        $cliNote = '';

        $optionCount = count($options);
        while ($optionCount >= 1) {
            $prepare = fn (string $text): string => $this->ux->prepareTelegramCodeText(
                $text,
                $operatorConfidence,
                $optionCount,
            );
            $full = $this->ux->formatAiAssistantTelegramMessage($result, $options, $optionCount, $cliNote, $prepare);
            if (mb_strlen($full, 'UTF-8') <= $maxChars) {
                return $full;
            }
            $optionCount--;
        }

        $cliNote = 'More options available via CLI.';
        $optionCount = min(2, count($options));
        $prepare = fn (string $text): string => $this->ux->prepareTelegramCodeText(
            $text,
            $operatorConfidence,
            $optionCount,
            false,
        );
        $full = $this->ux->formatAiAssistantTelegramMessage(
            $result,
            array_slice($options, 0, $optionCount),
            $optionCount,
            $cliNote,
            $prepare,
        );

        if (mb_strlen($full, 'UTF-8') > $maxChars) {
            $full = $this->truncateUtf8($full, $maxChars);
        }

        return $full;
    }

    private function compactTelegramOptionText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $text = preg_replace('/^\s*[-•*]\s+/m', '', $text) ?? $text;
        $text = preg_replace('/\s*\n+\s*/', ' ', $text) ?? $text;
        $text = preg_replace('/\s{2,}/u', ' ', $text) ?? $text;

        return $this->truncateToMaxSentences(trim($text), 2);
    }

    private function truncateToMaxSentences(string $text, int $maxSentences = 2): string
    {
        if ($text === '') {
            return '';
        }

        if (preg_match_all('/[^.!?…]+[.!?…]+/u', $text, $matches) >= 1 && ! empty($matches[0])) {
            $sentences = array_slice($matches[0], 0, $maxSentences);
            $text = trim(implode(' ', array_map(static fn (string $s): string => trim($s), $sentences)));
        }

        if (mb_strlen($text, 'UTF-8') > self::TELEGRAM_COPY_BLOCK_MAX) {
            return mb_substr($text, 0, max(0, self::TELEGRAM_COPY_BLOCK_MAX - 1), 'UTF-8').'…';
        }

        return $text;
    }

    /**
     * @return array{
     *     draft: string|null,
     *     options: list<array{label: string, style: string, text: string}>,
     *     choices: int,
     *     confidence: string,
     *     warnings: list<string>,
     *     language: string,
     *     error: string|null,
     *     protected_identifiers: list<string>
     * }
     */
    public function draftForConversation(
        SupportConversation $conversation,
        ?SupportMessage $triggerMessage = null,
        ?int $choices = null,
        bool $telegramCompact = false,
    ): array {
        $language = $this->resolveLanguage($conversation, $triggerMessage);
        $fallbackChoices = max(1, min(4, (int) config('support_chat.ai.telegram_choices', 4)));

        if (! $this->isEnabled()) {
            $result = $this->emptyResult($language, $choices ?? $fallbackChoices, 'ai_disabled');
            $this->emitDraftDiagnostics($conversation, null, $result);

            return $result;
        }

        $triggerMessage ??= $this->latestVisitorMessage($conversation);
        if ($triggerMessage === null) {
            $result = $this->emptyResult($language, $choices ?? $fallbackChoices, 'no_visitor_message', [
                'No visitor message found for drafting.',
            ]);
            $this->emitDraftDiagnostics($conversation, null, $result);

            return $result;
        }

        try {
            $result = $this->requestDraft($conversation, $triggerMessage, $language, $choices, $telegramCompact);
            $this->emitDraftDiagnostics($conversation, $triggerMessage, $result, $result['_draft_diag'] ?? []);
            unset($result['_draft_diag']);

            return $result;
        } catch (Throwable $e) {
            Log::warning('support-chat ai: draft_failed', [
                'support_conversation_id' => $conversation->id,
                'support_message_id' => $triggerMessage->id,
                'exception' => $this->safeLogMessage($e->getMessage()),
            ]);

            $choices = $choices ?? $fallbackChoices;
            $result = $this->emptyResult($language, $choices, 'exception');
            $this->emitDraftDiagnostics($conversation, $triggerMessage, $result);

            return $result;
        }
    }

    /**
     * Resolve intent/stage/language for learning telemetry without calling OpenAI.
     *
     * @return array{intent: string, conversation_stage: string, language: string, ai_request_id: string}
     */
    public function resolveDraftContext(
        SupportConversation $conversation,
        SupportMessage $triggerMessage,
    ): array {
        $language = $this->resolveLanguage($conversation, $triggerMessage);
        $messages = $this->loadContextMessages($conversation);
        $rawContext = $this->collectRawContextText($conversation, $messages, $triggerMessage);
        $protected = $this->extractProtectedIdentifiers($rawContext);
        $knownData = $this->extractKnownConversationData($rawContext, $protected);
        $isFollowUp = $this->detectRepeatedFollowUp($triggerMessage, $messages, $knownData);
        $operatorContext = $this->detectLastOperatorAction($messages, $triggerMessage);
        $providedAfterRequest = $this->detectVisitorProvidedDataAfterRequest(
            $messages,
            $triggerMessage,
            $knownData,
            $operatorContext,
        );
        $intent = $this->detectConversationIntent($triggerMessage, $messages, $protected, $isFollowUp);
        $directionLookup = $this->directionContext->lookupForDraft((string) $triggerMessage->body, $language);
        $intent = $this->refineIntentWithDirectionContext($intent, (string) $triggerMessage->body, $directionLookup);
        $stage = $this->detectConversationStage(
            $messages,
            $knownData,
            $isFollowUp,
            $intent,
            $operatorContext,
            $providedAfterRequest,
        );

        return [
            'intent' => $intent,
            'conversation_stage' => $stage,
            'language' => $language,
            'ai_request_id' => hash('sha256', $conversation->id.':'.$triggerMessage->id.':'.now()->timestamp),
        ];
    }

    /**
     * Plain-text block for Telegram follow-up notifications (never sent to visitor).
     */
    public function formatTelegramAppend(?array $result, ?int $maxChars = null): string
    {
        $formatted = $this->buildTelegramPreview($result, $maxChars ?? self::TELEGRAM_DRAFT_MAX, false);

        return $formatted['text'];
    }

    /**
     * HTML block for first Telegram notification (never sent to visitor).
     */
    public function formatTelegramAppendHtml(?array $result, ?int $maxChars = null): string
    {
        $formatted = $this->buildTelegramPreview($result, $maxChars ?? self::TELEGRAM_DRAFT_MAX, true);

        return $formatted['text'];
    }

    /**
     * @return array{text: string, truncated: bool}
     */
    private function buildTelegramPreview(?array $result, int $maxChars, bool $html): array
    {
        if ($result === null) {
            return ['text' => '', 'truncated' => false];
        }

        $options = $this->resolveDisplayOptions($result);
        if ($options === []) {
            return ['text' => '', 'truncated' => false];
        }

        $confidence = (string) ($result['operator_confidence'] ?? $result['confidence'] ?? 'medium');
        $truncated = false;

        $footerPlain = "━━━━━━━━━━━━\nReply manually in this topic. AI does not send messages to visitors.";

        $optionCount = count($options);
        while ($optionCount >= 1) {
            $shortenedNote = '';
            $prepare = fn (string $text): string => $this->ux->prepareTelegramCodeText($text, $confidence, $optionCount);
            if ($html) {
                $core = $this->ux->formatAiAssistantTelegramMessage($result, $options, $optionCount, $shortenedNote, $prepare);
                $full = "\n<pre>━━━━━━━━━━━━</pre>\n".$core."\n<pre>━━━━━━━━━━━━</pre>\n<i>Reply manually in this topic. AI does not send messages to visitors.</i>";
            } else {
                $core = $this->ux->formatAiAssistantTelegramMessage($result, $options, $optionCount, $shortenedNote, $prepare);
                $full = strip_tags(str_replace(['<code>', '</code>'], ['', ''], $core))."\n\n".$footerPlain;
            }

            if (mb_strlen($full, 'UTF-8') <= $maxChars) {
                return ['text' => $full, 'truncated' => $truncated];
            }

            $optionCount--;
            $truncated = true;
        }

        $shortenedNote = 'AI suggestions shortened for Telegram.';
        $prepare = fn (string $text): string => $this->ux->prepareTelegramCodeText($text, $confidence, 1);
        $core = $this->ux->formatAiAssistantTelegramMessage(
            $result,
            array_slice($options, 0, 1),
            1,
            $shortenedNote,
            $prepare,
        );
        if ($html) {
            $full = "\n<pre>━━━━━━━━━━━━</pre>\n".$core."\n<pre>━━━━━━━━━━━━</pre>\n<i>Reply manually in this topic. AI does not send messages to visitors.</i>";
        } else {
            $full = strip_tags(str_replace(['<code>', '</code>'], ['', ''], $core))."\n\n".$footerPlain;
        }
        $full = $this->truncateUtf8($full, $maxChars);

        return ['text' => $full, 'truncated' => true];
    }

    /**
     * @return list<array{label: string, style: string, text: string}>
     */
    public function resolveDisplayOptions(array $result): array
    {
        $options = is_array($result['options'] ?? null) ? $result['options'] : [];
        if ($options !== []) {
            return array_slice($options, 0, 4);
        }

        $draft = trim((string) ($result['draft'] ?? ''));
        if ($draft === '') {
            return [];
        }

        return [[
            'label' => self::OPTION_LABELS[0],
            'style' => self::OPTION_STYLES[0],
            'text' => $draft,
        ]];
    }

    private function truncateOptionLines(string $text, int $maxLines): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $lines = explode("\n", $text);
        if (count($lines) <= $maxLines) {
            return $text;
        }

        $kept = array_slice($lines, 0, $maxLines);
        $kept[$maxLines - 1] = rtrim((string) $kept[$maxLines - 1]).'…';

        return implode("\n", $kept);
    }

    /**
     * @param  list<string>  $extraWarnings
     * @return array{
     *     draft: string|null,
     *     options: list<array{label: string, style: string, text: string}>,
     *     choices: int,
     *     confidence: string,
     *     warnings: list<string>,
     *     language: string,
     *     error: string|null,
     *     protected_identifiers: list<string>
     * }
     */
    private function emptyResult(string $language, int $choices, string $error, array $extraWarnings = []): array
    {
        return [
            'draft' => null,
            'options' => [],
            'choices' => $choices,
            'confidence' => 'none',
            'warnings' => $extraWarnings,
            'language' => $language,
            'error' => $error,
            'protected_identifiers' => [],
        ];
    }

    /**
     * @return array{
     *     draft: string|null,
     *     options: list<array{label: string, style: string, text: string}>,
     *     choices: int,
     *     confidence: string,
     *     warnings: list<string>,
     *     language: string,
     *     error: string|null,
     *     protected_identifiers: list<string>
     * }
     */
    private function requestDraft(
        SupportConversation $conversation,
        SupportMessage $triggerMessage,
        string $language,
        ?int $choices,
        bool $telegramCompact = false,
    ): array {
        $apiKey = trim((string) config('support_chat.ai.openai_api_key', ''));
        $model = trim((string) config('support_chat.ai.model', 'gpt-4o-mini'));
        $timeout = max(5, min(60, (int) config('support_chat.ai.timeout_seconds', 15)));
        $fallbackChoices = max(1, min(4, (int) config('support_chat.ai.telegram_choices', 4)));

        $messages = $this->loadContextMessages($conversation);
        $rawContext = $this->collectRawContextText($conversation, $messages, $triggerMessage);
        $protected = $this->extractProtectedIdentifiers($rawContext);
        $knownData = $this->extractKnownConversationData($rawContext, $protected);
        $isFollowUp = $this->detectRepeatedFollowUp($triggerMessage, $messages, $knownData);
        $operatorContext = $this->detectLastOperatorAction($messages, $triggerMessage);
        $providedAfterRequest = $this->detectVisitorProvidedDataAfterRequest(
            $messages,
            $triggerMessage,
            $knownData,
            $operatorContext,
        );
        $intent = $this->detectConversationIntent($triggerMessage, $messages, $protected, $isFollowUp);
        $primaryOrderId = $knownData['order_ids'][0] ?? null;
        $visitorBody = (string) $triggerMessage->body;
        $directionLookup = $this->directionContext->lookupForDraft($visitorBody, $language);
        $intent = $this->refineIntentWithDirectionContext($intent, $visitorBody, $directionLookup);
        $stage = $this->detectConversationStage(
            $messages,
            $knownData,
            $isFollowUp,
            $intent,
            $operatorContext,
            $providedAfterRequest,
        );
        $orderLookup = $this->orderContext->lookupForDraft(
            is_string($primaryOrderId) ? $primaryOrderId : null,
            $language,
        );
        $greetingRecommended = $this->shouldUseGreeting($messages, $triggerMessage);
        $conversationMemory = $this->buildConversationMemory($messages, $triggerMessage, $knownData, $isFollowUp, $intent, $stage);
        $operatorAwareness = $this->buildOperatorActionAwarenessBlock(
            $operatorContext,
            $providedAfterRequest,
            $knownData,
            $isFollowUp,
            $stage,
        );
        $contextBlock = $this->buildContextBlock(
            $conversation,
            $messages,
            $triggerMessage,
            $protected,
            $knownData,
            $intent,
            $greetingRecommended,
            $conversationMemory,
            $operatorAwareness,
            $stage,
            $isFollowUp,
            $orderLookup,
            $directionLookup,
        );

        $overlayMeta = [];
        try {
            $overlayPackage = $this->learningOverlay->buildOverlayPackage($intent, $language);
            $overlayContext = trim((string) ($overlayPackage['context'] ?? ''));
            if ($overlayContext !== '') {
                $contextBlock .= "\n\n".$overlayContext;
            }
            $overlayMeta = is_array($overlayPackage['meta'] ?? null) ? $overlayPackage['meta'] : [];
        } catch (Throwable $e) {
            Log::warning('support-chat ai:learning_overlay_failed', [
                'stage' => 'draft_context',
                'support_conversation_id' => $conversation->id,
                'exception' => $this->safeLogMessage($e->getMessage()),
            ]);
        }

        $enrichmentContext = [
            'language' => $language,
            'draft_intent' => $intent,
            'has_order_id' => $knownData['order_ids'] !== [],
            'has_tx_hash' => $knownData['tx_hashes'] !== [],
            'has_verified_order_status' => ! empty($orderLookup['verified_order_status']),
            'has_direction_availability' => ! empty($directionLookup['direction_lookup_found']),
            'direction_availability_status' => $directionLookup['availability_status'] ?? null,
        ];
        $knowledgeRules = [];
        $matchedTemplates = [];
        $policyOnly = false;

        try {
            if ($this->knowledge->isEnabled()) {
                $knowledgeRules = $this->knowledge->findRelevantRules($visitorBody, $enrichmentContext);
                $policyOnly = $this->knowledge->isPolicyOnlyQuestion($visitorBody, $enrichmentContext);
                $knowledgeBlock = $this->knowledge->buildKnowledgeContext($knowledgeRules, $policyOnly);
                if ($knowledgeBlock !== '') {
                    $contextBlock .= "\n\n".$knowledgeBlock;
                }
            }
        } catch (Throwable $e) {
            Log::warning('support-chat ai:knowledge_context_failed', [
                'stage' => 'draft_context',
                'support_conversation_id' => $conversation->id,
                'exception' => $this->safeLogMessage($e->getMessage()),
            ]);
        }

        try {
            if ($this->templates->isEnabled()) {
                if (! $policyOnly) {
                    $policyOnly = $this->knowledge->isPolicyOnlyQuestion($visitorBody, $enrichmentContext);
                }
                $matchedTemplates = $this->templates->findRelevantTemplates($visitorBody, $enrichmentContext);
                $templateBlock = $this->templates->buildTemplateContext($matchedTemplates, $policyOnly);
                if ($templateBlock !== '') {
                    $contextBlock .= "\n\n".$templateBlock;
                }
            }
        } catch (Throwable $e) {
            Log::warning('support-chat ai:template_context_failed', [
                'stage' => 'draft_context',
                'support_conversation_id' => $conversation->id,
                'exception' => $this->safeLogMessage($e->getMessage()),
            ]);
        }

        $uxContext = $this->ux->buildContext([
            'intent' => $intent,
            'stage' => $stage,
            'language' => $language,
            'policy_only' => $policyOnly,
            'has_order_id' => $knownData['order_ids'] !== [],
            'has_tx_hash' => $knownData['tx_hashes'] !== [],
            'knowledge_rules' => $knowledgeRules,
            'matched_templates' => $matchedTemplates,
            'visitor_body' => $visitorBody,
            'knowledge_intents' => SupportAiPolicyIntentPatterns::matchKnowledgeIntents($visitorBody, [
                'draft_intent' => $intent,
            ]),
        ]);

        $useDynamicUxCount = $choices === null;
        $effectiveChoices = $useDynamicUxCount
            ? (int) $uxContext['suggestion_count']
            : max(1, min(4, $choices));

        $contextBlock = $this->truncateUtf8($contextBlock, self::MAX_PROMPT_CONTEXT_CHARS);
        $systemPrompt = $this->systemPrompt($language, $effectiveChoices, $telegramCompact, $intent, $stage, $orderLookup, $directionLookup);
        $userPrompt = $this->userPrompt($contextBlock, $language, $effectiveChoices, $telegramCompact, $intent, $stage);

        $maxTokens = $telegramCompact ? 700 : ($effectiveChoices > 1 ? 1600 : 500);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->withToken($apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.25,
                'max_tokens' => $maxTokens,
            ]);

        if ($response->status() === 429) {
            Log::warning('support-chat ai: rate_limited', [
                'support_conversation_id' => $conversation->id,
            ]);

            return $this->emptyDraftResultWithDiagnostics(
                $conversation,
                $triggerMessage,
                $language,
                $effectiveChoices,
                'rate_limit',
                $intent,
                $knownData,
            );
        }

        if (! $response->successful()) {
            Log::warning('support-chat ai: http_error', [
                'support_conversation_id' => $conversation->id,
                'status' => $response->status(),
            ]);

            return $this->emptyDraftResultWithDiagnostics(
                $conversation,
                $triggerMessage,
                $language,
                $effectiveChoices,
                'http_'.$response->status(),
                $intent,
                $knownData,
            );
        }

        $payload = $response->json();
        $content = $payload['choices'][0]['message']['content'] ?? null;
        if (! is_string($content) || trim($content) === '') {
            return $this->emptyDraftResultWithDiagnostics(
                $conversation,
                $triggerMessage,
                $language,
                $effectiveChoices,
                'empty_response',
                $intent,
                $knownData,
            );
        }

        $result = $this->parseModelOutput(
            trim($content),
            $language,
            $effectiveChoices,
            $protected,
            $greetingRecommended,
            $knownData,
            $stage,
            $operatorContext,
            $policyOnly,
            (bool) ($uxContext['policy_protected'] ?? false),
            $orderLookup,
        );

        if ($overlayMeta !== []) {
            $result['learning_overlay'] = $overlayMeta;
        }

        $result = $this->ux->finalizeResult($result, $uxContext, $effectiveChoices, $useDynamicUxCount);
        $result['_draft_diag'] = array_merge([
            'intent' => $intent,
            'has_order_id' => $knownData['order_ids'] !== [],
        ], array_merge(
            $this->orderLookupDiagnostics($orderLookup),
            $this->directionLookupDiagnostics($directionLookup),
        ));

        return $result;
    }

    /**
     * @param  array{order_ids: list<string>, tx_hashes: list<string>}  $knownData
     * @return array<string, mixed>
     */
    private function emptyDraftResultWithDiagnostics(
        SupportConversation $conversation,
        SupportMessage $triggerMessage,
        string $language,
        int $choices,
        string $error,
        string $intent,
        array $knownData,
    ): array {
        $result = $this->emptyResult($language, $choices, $error);
        $orderLookup = $this->orderContext->lookupForDraft($knownData['order_ids'][0] ?? null, $language);
        $this->emitDraftDiagnostics($conversation, $triggerMessage, $result, array_merge([
            'intent' => $intent,
            'has_order_id' => $knownData['order_ids'] !== [],
        ], $this->orderLookupDiagnostics($orderLookup)));

        return $result;
    }

    /**
     * Production-safe draft telemetry (no message bodies, tokens, or PII).
     *
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $meta
     */
    private function emitDraftDiagnostics(
        SupportConversation $conversation,
        ?SupportMessage $triggerMessage,
        array $result,
        array $meta = [],
    ): void {
        try {
            $intent = trim((string) ($meta['intent'] ?? ''));
            if ($intent === '' && $triggerMessage !== null) {
                $intent = $this->resolveDraftContext($conversation, $triggerMessage)['intent'];
            }

            $hasOrderId = (bool) ($meta['has_order_id'] ?? false);
            if (! $hasOrderId && $triggerMessage !== null) {
                $messages = $this->loadContextMessages($conversation);
                $rawContext = $this->collectRawContextText($conversation, $messages, $triggerMessage);
                $protected = $this->extractProtectedIdentifiers($rawContext);
                $knownData = $this->extractKnownConversationData($rawContext, $protected);
                $hasOrderId = $knownData['order_ids'] !== [];
            }

            SupportChatDiagnosticsLog::aiDraftGenerated([
                'support_conversation_id' => (int) $conversation->id,
                'public_support_id' => $conversation->public_support_id,
                'telegram_forum_topic_id' => $conversation->telegram_forum_topic_id,
                'trigger_message_id' => $triggerMessage?->id,
                'intent' => $intent !== '' ? $intent : null,
                'language' => (string) ($result['language'] ?? ''),
                'has_order_id' => $hasOrderId,
                'has_verified_order_status' => (bool) ($meta['has_verified_order_status'] ?? false),
                'order_lookup_attempted' => (bool) ($meta['order_lookup_attempted'] ?? false),
                'order_lookup_found' => (bool) ($meta['order_lookup_found'] ?? false),
                'order_status_code' => isset($meta['order_status_code']) ? (int) $meta['order_status_code'] : null,
                'order_context_error' => isset($meta['order_context_error']) ? (string) $meta['order_context_error'] : null,
                'direction_lookup_attempted' => (bool) ($meta['direction_lookup_attempted'] ?? false),
                'direction_lookup_found' => (bool) ($meta['direction_lookup_found'] ?? false),
                'direction_status' => isset($meta['direction_status']) ? (string) $meta['direction_status'] : null,
                'direction_normalized' => isset($meta['direction_normalized']) ? (string) $meta['direction_normalized'] : null,
                'direction_context_error' => isset($meta['direction_context_error']) ? (string) $meta['direction_context_error'] : null,
                'is_fallback_or_deferral' => $this->isDeferralOrFallbackDraft(
                    $result,
                    (bool) ($meta['has_verified_order_status'] ?? false),
                ),
                'draft_error' => isset($result['error']) ? (string) $result['error'] : null,
                'option_count' => count($result['options'] ?? []),
                'confidence' => (string) ($result['confidence'] ?? ''),
            ]);

            if ($triggerMessage !== null) {
                $usageMeta = array_merge($meta, ['intent' => $intent !== '' ? $intent : null]);
                $this->operatorUsage->recordDraftGenerated($conversation, $triggerMessage, $result, $usageMeta);
            }
        } catch (Throwable) {
            // Diagnostics must never break operator-assist drafting.
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function directionLookupDiagnostics(array $directionLookup): array
    {
        return [
            'direction_lookup_attempted' => (bool) ($directionLookup['direction_lookup_attempted'] ?? false),
            'direction_lookup_found' => (bool) ($directionLookup['direction_lookup_found'] ?? false),
            'direction_status' => isset($directionLookup['availability_status']) ? (string) $directionLookup['availability_status'] : null,
            'direction_normalized' => isset($directionLookup['direction_normalized']) ? (string) $directionLookup['direction_normalized'] : null,
            'direction_context_error' => isset($directionLookup['direction_context_error']) ? (string) $directionLookup['direction_context_error'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $directionLookup
     */
    private function verifiedDirectionDraftRulesBlock(array $directionLookup): string
    {
        if (empty($directionLookup['direction_lookup_found'])) {
            return '';
        }

        $status = (string) ($directionLookup['availability_status'] ?? '');
        $summary = (string) ($directionLookup['safe_summary'] ?? '');
        $normalized = (string) ($directionLookup['direction_normalized'] ?? '');

        return <<<RULES

VERIFIED DIRECTION AVAILABILITY (mandatory — direction_lookup_found=true):
- LEAD with direction availability before wallet/requisites/order steps.
- Verified pair: {$normalized}. availability_status={$status}. Summary: {$summary}
- Do NOT open with "Уточните номер кошелька", "Проверим возможность обмена", or empty deferral when availability is known.
- Do NOT ask for wallet/requisites when availability_status is unsupported, paused, or manual_review_required.

RULES;
    }

    /**
     * @param  array<string, mixed>  $orderLookup
     */
    private function verifiedOrderDraftRulesBlock(array $orderLookup): string
    {
        if (empty($orderLookup['verified_order_status'])) {
            return '';
        }

        $statusHuman = (string) ($orderLookup['order_status_human'] ?? '');
        $summary = (string) ($orderLookup['order_safe_summary'] ?? '');
        $orderId = (string) ($orderLookup['order_public_id'] ?? '');

        return <<<RULES

VERIFIED ORDER STATUS (mandatory — verified_order_status=true in conversation context):
- LEAD each option with the verified status — do NOT open with "Проверим заявку", "Проверяем заявку", "We will check the order", or similar empty deferral.
- Structure: (1) state verified status in plain visitor language, (2) explain what it means, (3) give the next useful action.
- Verified facts: order_public_id={$orderId}, order_status_human={$statusHuman}. Summary: {$summary}
- Do NOT say "по системе и ответим с актуальным статусом" when status is already verified.
- Do NOT claim payout completed unless payout_completed=true in context.
- Do NOT say "we will check" unless lookup failed or status is ambiguous.

RULES;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $orderLookup
     * @return array<string, mixed>
     */
    private function applyVerifiedOrderDraftPolish(array $result, array $orderLookup): array
    {
        if (empty($orderLookup['verified_order_status'])) {
            return $result;
        }

        foreach ($result['options'] as $index => $option) {
            if (! isset($option['text']) || ! is_string($option['text'])) {
                continue;
            }
            $result['options'][$index]['text'] = $this->polishVerifiedOrderDraftText($option['text'], $orderLookup);
        }

        if (isset($result['draft']) && is_string($result['draft']) && $result['draft'] !== '') {
            $result['draft'] = $this->polishVerifiedOrderDraftText($result['draft'], $orderLookup);
            if (isset($result['options'][0]['text'])) {
                $result['options'][0]['text'] = $result['draft'];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $orderLookup
     */
    private function polishVerifiedOrderDraftText(string $text, array $orderLookup): string
    {
        $orderId = trim((string) ($orderLookup['order_public_id'] ?? ''));
        $trimmed = trim($text);
        if ($trimmed === '') {
            return $trimmed;
        }

        if ($orderId !== '') {
            $quotedId = preg_quote($orderId, '/');
            $replacements = [
                '/^(?:Проверим|Проверяем)\s+заявку\s*(?:№\s*)?'.$quotedId.'\s*[:\-—–]+\s*/u' => 'Заявка №'.$orderId.' ',
                '/^(?:Проверим|Проверяем)\s+заявку\s*(?:№\s*)?'.$quotedId.'\s+/u' => 'Заявка №'.$orderId.' ',
                '/^(?:We will check|Checking)\s+(?:order\s*(?:#|№)?\s*)?'.$quotedId.'\s*[:\-—–]+\s*/iu' => 'Order #'.$orderId.' ',
            ];
            foreach ($replacements as $pattern => $replacement) {
                $polished = preg_replace($pattern, $replacement, $trimmed);
                if (is_string($polished) && $polished !== $trimmed) {
                    $trimmed = $polished;
                    break;
                }
            }
        }

        if (preg_match('/^(?:Проверим|Проверяем)\s+заявку/u', $trimmed) === 1) {
            $prefix = $orderId !== '' ? 'Заявка №'.$orderId : 'Заявка';
            $trimmed = preg_replace('/^(?:Проверим|Проверяем)\s+заявку/u', $prefix, $trimmed) ?? $trimmed;
        }

        return trim(preg_replace('/\s{2,}/u', ' ', $trimmed) ?? $trimmed);
    }

    private function startsWithDeferralOpener(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return false;
        }

        return preg_match('/^(?:Проверим|Проверяем)\s+заявку/u', $trimmed) === 1
            || preg_match('/^(?:We will check|Checking)\s+(?:order|the order)\b/iu', $trimmed) === 1;
    }

    /**
     * @param  list<string>  $drafts
     * @return list<string>
     */
    private function verifyDeferralOpenersWhenVerified(array $drafts, bool $hasVerifiedStatus): array
    {
        if (! $hasVerifiedStatus) {
            return [];
        }

        foreach ($drafts as $draft) {
            if ($this->startsWithDeferralOpener($draft)) {
                return ['Draft opens with deferral wording despite verified order status — lead with verified status instead.'];
            }
        }

        return [];
    }

    private function isDeferralOrFallbackDraft(array $result, bool $hasVerifiedOrderStatus = false): bool
    {
        if (($result['error'] ?? null) !== null) {
            return true;
        }

        if ($hasVerifiedOrderStatus) {
            foreach ($result['options'] ?? [] as $option) {
                $text = trim((string) ($option['text'] ?? ''));
                if ($text !== '' && $this->startsWithDeferralOpener($text)) {
                    return true;
                }
            }

            $draft = trim((string) ($result['draft'] ?? ''));
            if ($draft !== '' && $this->startsWithDeferralOpener($draft)) {
                return true;
            }

            return false;
        }

        $patterns = [
            'Проверим заявку',
            'We will check order',
            'check order',
            'по системе',
        ];

        foreach ($result['options'] ?? [] as $option) {
            $text = (string) ($option['text'] ?? '');
            foreach ($patterns as $pattern) {
                if ($text !== '' && stripos($text, $pattern) !== false) {
                    return true;
                }
            }
        }

        $draft = (string) ($result['draft'] ?? '');
        if ($draft !== '') {
            foreach ($patterns as $pattern) {
                if (stripos($draft, $pattern) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $orderLookup
     * @return array<string, mixed>
     */
    private function orderLookupDiagnostics(array $orderLookup): array
    {
        return [
            'order_lookup_attempted' => (bool) ($orderLookup['order_lookup_attempted'] ?? false),
            'order_lookup_found' => (bool) ($orderLookup['order_lookup_found'] ?? false),
            'has_verified_order_status' => (bool) ($orderLookup['verified_order_status'] ?? false),
            'order_status_code' => isset($orderLookup['order_status_code']) ? (int) $orderLookup['order_status_code'] : null,
            'order_context_error' => isset($orderLookup['order_context_error']) ? (string) $orderLookup['order_context_error'] : null,
        ];
    }

    /**
     * @return Collection<int, SupportMessage>
     */
    private function loadContextMessages(SupportConversation $conversation): Collection
    {
        $limit = max(10, min(self::MAX_RECENT_MESSAGES, (int) config('support_chat.ai.max_context_messages', 8)));

        return SupportMessage::query()
            ->where('support_conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * @param  Collection<int, SupportMessage>  $messages
     */
    private function collectRawContextText(
        SupportConversation $conversation,
        Collection $messages,
        SupportMessage $triggerMessage,
    ): string {
        $parts = [
            (string) $conversation->page_url,
            (string) $triggerMessage->body,
        ];
        foreach ($messages as $msg) {
            $parts[] = (string) $msg->body;
        }

        return implode("\n", $parts);
    }

    /**
     * @param  list<string>  $protected
     * @param  array{
     *     order_ids: list<string>,
     *     tx_hashes: list<string>,
     *     amounts: list<string>,
     *     networks: list<string>,
     *     wallets: list<string>,
     *     directions: list<string>,
     *     payment_methods: list<string>,
     *     has_payment_proof: bool,
     * }  $knownData
     * @param  Collection<int, SupportMessage>  $messages
     */
    private function buildContextBlock(
        SupportConversation $conversation,
        Collection $messages,
        SupportMessage $triggerMessage,
        array $protected,
        array $knownData,
        string $intent,
        bool $greetingRecommended,
        string $conversationMemory,
        string $operatorAwareness,
        string $stage,
        bool $isFollowUp,
        array $orderLookup = [],
        array $directionLookup = [],
    ): string {
        $lines = [];
        $lines[] = 'Support ID: '.($conversation->public_support_id ?? 'unknown');
        $lines[] = 'Locale: '.($conversation->locale ?? 'unknown');
        $lines[] = 'Detected intent: '.$intent;
        $lines[] = 'Conversation stage: '.$stage;
        $lines[] = 'Repeated follow-up: '.($isFollowUp ? 'YES' : 'NO');
        $lines[] = 'Intent guidance: '.SupportAiReplyPlaybook::intentGuidance($intent);
        $lines[] = 'Answer selection: '.SupportAiReplyPlaybook::answerSelectionGuidance($stage);
        $lines[] = 'Greeting recommended: '.($greetingRecommended ? 'YES (at most one option may greet briefly)' : 'NO — start directly with action, no Здравствуйте/Hello/Hi in every option)');

        if (! empty($orderLookup['verified_order_status'])) {
            $lines[] = 'Order/payment status in admin: VERIFIED (read-only lookup — use verified facts below; do not defer with generic "we will check").';
        } elseif (! empty($orderLookup['order_lookup_attempted']) && empty($orderLookup['order_lookup_found'])) {
            $lines[] = 'Order/payment status in admin: NOT FOUND for provided order number — ask visitor to verify the number; do not invent status.';
        } else {
            $lines[] = 'Order/payment status in admin: UNKNOWN (not verified — do not claim processing/sent/confirmed/completed).';
        }

        if (! empty($directionLookup['direction_lookup_found'])) {
            $lines[] = 'Exchange direction availability: VERIFIED (read-only lookup — lead with availability facts below).';
        } elseif (! empty($directionLookup['direction_lookup_attempted'])) {
            $lines[] = 'Exchange direction availability: UNKNOWN from message — do not claim direction is supported or unsupported without verification.';
        }

        $lines[] = 'Page URL (path preserved): '.$this->pageUrlForContext($conversation->page_url);
        $lines[] = 'Visitor display name: '.$this->sanitizeTextForAiContext((string) $conversation->visitor_name);
        $lines[] = 'Latest visitor message id: '.$triggerMessage->id;

        $lines[] = '';
        $lines[] = $conversationMemory;

        $lines[] = '';
        $lines[] = $operatorAwareness;

        $provided = $this->summarizeProvidedData($knownData);
        if ($provided['has'] !== []) {
            $lines[] = 'Visitor already provided: '.implode(', ', $provided['has']);
        }
        if ($provided['do_not_ask'] !== []) {
            $lines[] = 'Do NOT ask again for: '.implode(', ', $provided['do_not_ask']);
        }
        if ($provided['missing'] !== []) {
            $lines[] = 'May still need (only if relevant to intent and not in thread): '.implode(', ', $provided['missing']);
        }

        if ($protected !== []) {
            $lines[] = 'PROTECTED IDENTIFIERS (copy EXACTLY in every draft — do not shorten, normalize, round, or modify):';
            foreach ($protected as $id) {
                $lines[] = '- '.$id;
            }
        }

        $orderPrompt = $this->orderContext->buildPromptBlock($orderLookup);
        if ($orderPrompt !== '') {
            $lines[] = '';
            $lines[] = $orderPrompt;
        }

        $directionPrompt = $this->directionContext->buildPromptBlock($directionLookup);
        if ($directionPrompt !== '') {
            $lines[] = '';
            $lines[] = $directionPrompt;
        }

        $lines[] = '--- recent messages ---';
        $lines[] = $this->formatRecentConversationForPrompt($messages);

        return $this->truncateUtf8(implode("\n", $lines), self::MAX_PROMPT_CONTEXT_CHARS);
    }

    /**
     * @param  list<string>  $protected
     */
    private function detectConversationIntent(
        SupportMessage $triggerMessage,
        Collection $messages,
        array $protected,
        bool $isFollowUp = false,
    ): string {
        $sample = (string) $triggerMessage->body;
        $text = mb_strtolower($sample, 'UTF-8');

        $hasOrderId = $this->conversationHasOrderId($messages, $protected, $sample);
        $hasTx = $this->conversationHasTxHash($messages, $protected, $sample);

        $hardAnger = preg_match(
            '/\b(?:unacceptable|outrageous|terrible|scam|fraud|обман|ужас|кошмар|безобразие|верните деньги|where is my money|где деньги)\b/iu',
            $text
        ) === 1;

        if ($isFollowUp && $hasOrderId && ! $hardAnger) {
            return 'repeated_follow_up';
        }

        $strongAnger = $hardAnger
            || (preg_match('/(?:!!!+|‼️)/u', $text) === 1 && preg_match('/\b(?:money|деньги|scam|обман|unacceptable|верните)\b/iu', $text) === 1);

        if ($strongAnger || preg_match('/\b(очень долго|безобразие)\b/iu', $text) === 1) {
            return 'complaint_or_angry_customer';
        }
        if (preg_match('/\b(возврат|refund|вернуть|chargeback)\b/iu', $text) === 1) {
            return 'refund_request';
        }
        if (preg_match('/\b(недоплат|переплат|partial payment|wrong amount|incorrect amount|не та сумма|неполн(?:ая|ую)?\s*(?:сумм|оплат))\b/iu', $text) === 1) {
            return 'wrong_amount';
        }
        if (preg_match('/\b(instead of|вместо|wrong network|не ту сеть|не та сеть|ошиб.*?сет|sent on.*(?:trc|erc|bep))\b/iu', $text) === 1
            && preg_match('/\b(trc20|erc20|bep20|network|сеть|usdt|btc|eth)\b/iu', $text) === 1) {
            return 'wrong_network';
        }
        if (preg_match('/\b(?:funds safe|money safe|safe\?|guaranteed|гарантир|в безопасности|are my funds|средства в безопасности)\b/iu', $text) === 1) {
            return 'funds_safety_question';
        }
        if (preg_match('/\b(?:how long|how soon|сколько ждать|сколько ещё|сколько еще|exact time|exactly when|ETA|when will|через сколько|когда будет|completion time|точное время)\b/iu', $text) === 1) {
            return 'eta_request';
        }
        if (preg_match('/\b(курс|rate|лимит|limit|комисси|fee)\b/iu', $text) === 1) {
            return 'rate_question';
        }
        if (preg_match('/\b(otc|крупн|large amount|exchange\s+\d|\d[\d\s,]{3,}\s*(?:usdt|usd|btc|eth|eur|rub))\b/iu', $text) === 1) {
            return 'large_otc_exchange';
        }
        if (preg_match('/\b(карт|card|bank|банк|sepa|swift|сбп)\b/iu', $text) === 1) {
            return 'card_or_bank_transfer';
        }
        if (preg_match('/\b(screenshot|скрин|photo|фото|proof attached|квитан|payment proof)\b/iu', $text) === 1 && ! $hasOrderId && ! $hasTx) {
            return 'payment_proof_only';
        }
        if ($isFollowUp) {
            return $hasOrderId ? 'repeated_follow_up' : 'general_support';
        }
        if ($hasTx && preg_match('/\b(оплат|paid|payment|отправил|sent|перев[её]л)\b/iu', $text) === 1) {
            return 'payment_delay';
        }
        if ($hasTx) {
            return 'payment_delay';
        }
        if (preg_match('/\b(не поступ|not received|не пришл|не зачисл|still not)\b/iu', $text) === 1) {
            return $hasOrderId ? 'payment_delay' : 'missing_order_id';
        }
        if (preg_match('/\b(?:where is|где)\b/iu', $text) === 1) {
            if (preg_match('/\b(order|заявк|withdraw|вывод|withdrawal)\b/iu', $text) === 1) {
                return $hasOrderId
                    ? (preg_match('/\b(withdraw|вывод|withdrawal|вывести)\b/iu', $text) === 1 ? 'payment_delay' : 'order_status')
                    : 'missing_order_id';
            }
            if (preg_match('/\b(money|деньги|payment|плат[её]ж|funds|средств)\b/iu', $text) === 1) {
                return $hasOrderId ? 'payment_delay' : 'missing_order_id';
            }
        }
        if (preg_match('/\b(вывод|withdraw|withdrawal|вывести)\b/iu', $text) === 1) {
            return $hasOrderId ? 'payment_delay' : 'missing_order_id';
        }
        if (preg_match('/\b(оплат|paid|payment|плат[её]ж|перев[её]л)\b/iu', $text) === 1) {
            return $hasOrderId
                ? ($hasTx ? 'payment_delay' : 'missing_tx_hash')
                : 'missing_order_id';
        }
        if (preg_match('/\b(статус|status|заявк|order|ожид|delay|сколько ждать)\b/iu', $text) === 1) {
            return $hasOrderId ? 'order_status' : 'missing_order_id';
        }
        if ($hasOrderId) {
            return 'order_status';
        }
        if ($this->directionContext->extractPairFromMessage($sample) !== null) {
            return 'exchange_direction';
        }
        if ($this->detectWalletCheckIntent($text)) {
            return 'wallet_check';
        }
        $policyDraftIntent = SupportAiPolicyIntentPatterns::resolvePrimaryDraftIntent($text);
        if ($policyDraftIntent !== null) {
            return $policyDraftIntent;
        }
        if (preg_match('/\b(как|how|what|можно|can i|help|помог)\b/iu', $text) === 1) {
            return 'general_support';
        }

        return 'unknown_context';
    }

    private function detectWalletCheckIntent(string $text): bool
    {
        return preg_match(
            '/\b(?:посмотреть|проверить|check|verify|validate|review)\b.{0,50}\b(?:кошел|wallet|адрес|address)\b|\b(?:кошел|wallet|адрес)\b.{0,50}\b(?:перед обмен|before exchange|провер|посмотр|check|verify)\b/iu',
            $text,
        ) === 1;
    }

    private function refineIntentWithDirectionContext(string $intent, string $visitorBody, array $directionLookup): string
    {
        if ($intent !== 'unknown_context' && $intent !== 'general_support') {
            return $intent;
        }

        if (! empty($directionLookup['direction_lookup_attempted']) && ! empty($directionLookup['direction_lookup_found'])) {
            return 'exchange_direction';
        }

        if ($this->directionContext->extractPairFromMessage($visitorBody) !== null) {
            return 'exchange_direction';
        }

        if ($this->detectWalletCheckIntent(mb_strtolower($visitorBody, 'UTF-8'))) {
            return 'wallet_check';
        }

        return $intent;
    }

    /**
     * @param  array<string, mixed>  $directionLookup
     */
    private function exchangeDirectionIntentRulesBlock(string $intent, array $directionLookup): string
    {
        if ($intent !== 'exchange_direction' || empty($directionLookup['direction_lookup_found'])) {
            return '';
        }

        $normalized = (string) ($directionLookup['direction_normalized'] ?? '');
        $status = (string) ($directionLookup['availability_status'] ?? '');
        $summary = (string) ($directionLookup['safe_summary'] ?? '');

        return <<<RULES

EXCHANGE DIRECTION INTENT (mandatory — intent=exchange_direction):
- LEAD with direction availability from verified lookup: {$normalized} (availability_status={$status}).
- Summary: {$summary}
- If visitor also asks to check wallet before exchange: say operator will advise whether the wallet address can be accepted — do NOT ask visitor to paste wallet as the opening move.
- Example shape (adapt, do not copy blindly): "Направление {$normalized} доступно. По проверке кошелька оператор подскажет, можно ли принять адрес перед обменом."
- Do NOT claim unsupported/paused/manual_review unless availability_status says so.

RULES;
    }

    /**
     * @param  list<string>  $protected
     */
    private function conversationHasOrderId(Collection $messages, array $protected, string $sample = ''): bool
    {
        if ($this->protectedHasOrderId($protected)) {
            return true;
        }

        if (preg_match('/\b\d{10,}\b/u', $sample) === 1 || preg_match('#/order/\d+#i', $sample) === 1) {
            return true;
        }

        foreach ($messages as $msg) {
            $body = (string) $msg->body;
            if (preg_match('/\b\d{10,}\b/u', $body) === 1 || preg_match('#/order/\d+#i', $body) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, SupportMessage>  $messages
     * @param  list<string>  $protected
     */
    private function conversationHasTxHash(Collection $messages, array $protected, string $sample = ''): bool
    {
        foreach ($protected as $id) {
            if (preg_match('/^0x[0-9a-fA-F]{40,}$/i', (string) $id) === 1 || preg_match('/^[0-9a-fA-F]{64}$/', (string) $id) === 1) {
                return true;
            }
        }

        $texts = [$sample];
        foreach ($messages as $msg) {
            $texts[] = (string) $msg->body;
        }

        foreach ($texts as $text) {
            if (preg_match('/\b0x[0-9a-fA-F]{40,}\b/i', $text) === 1
                || preg_match('/\b(?:txid|tx hash|tx:|hash:|\btx\b)/iu', $text) === 1
                || preg_match('/\b[0-9a-fA-F]{64}\b/', $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, SupportMessage>  $messages
     * @param  array{
     *     order_ids: list<string>,
     *     tx_hashes: list<string>,
     *     amounts: list<string>,
     *     networks: list<string>,
     *     wallets: list<string>,
     *     directions: list<string>,
     *     payment_methods: list<string>,
     *     has_payment_proof: bool,
     * }  $knownData
     */
    private function detectRepeatedFollowUp(
        SupportMessage $triggerMessage,
        Collection $messages,
        array $knownData = [],
    ): bool {
        $priorVisitorCount = $messages->filter(
            static fn (SupportMessage $m): bool => $m->sender_type === SupportMessage::SENDER_VISITOR
                && $m->id < $triggerMessage->id
        )->count();

        if ($priorVisitorCount === 0) {
            return false;
        }

        $body = mb_strtolower(trim((string) $triggerMessage->body), 'UTF-8');
        $isFollowUpPhrase = preg_match(
            '/\b(?:any news|still waiting|why no answer|where is my money|any update|still no|follow up|'
            .'есть новости|нет ответа|уже долго|что с моей заявкой|что там|когда уже|повторно|ну\?|ну\b|'
            .'жду ответ|нет обновлен|сколько ещё|сколько еще|hello\?|anyone there)\b/iu',
            $body
        ) === 1;

        if (! $isFollowUpPhrase) {
            $short = mb_strlen($body, 'UTF-8') <= 40;
            $isFollowUpPhrase = $short && preg_match('/\?(?:\s*)$/u', $body) === 1
                && ($knownData['order_ids'] !== [] || $this->conversationHasOrderId($messages, []));
        }

        return $isFollowUpPhrase;
    }

    /**
     * @param  Collection<int, SupportMessage>  $messages
     * @return array{action: string, snippet: string, message_id: int}
     */
    private function detectLastOperatorAction(Collection $messages, SupportMessage $triggerMessage): array
    {
        $last = null;
        foreach ($messages->reverse() as $msg) {
            if ($msg->id >= $triggerMessage->id) {
                continue;
            }
            if ($msg->sender_type !== SupportMessage::SENDER_OPERATOR) {
                continue;
            }
            $last = $msg;
            break;
        }

        if ($last === null) {
            return [
                'action' => 'unknown_operator_action',
                'snippet' => '',
                'message_id' => 0,
            ];
        }

        $body = (string) $last->body;

        return [
            'action' => $this->classifyOperatorAction($body),
            'snippet' => $this->truncateUtf8($this->sanitizeTextForAiContext($body), 120),
            'message_id' => $last->id,
        ];
    }

    private function classifyOperatorAction(string $body): string
    {
        $text = mb_strtolower($body, 'UTF-8');

        if (preg_match('/\b(?:пришлите|отправьте|укажите|send|provide|share).{0,50}(?:номер заявки|order (?:id|number|#)|номер обмена)\b/iu', $text) === 1) {
            return 'operator_requested_order_id';
        }
        if (preg_match('/\b(?:пришлите|отправьте|укажите|send|provide|share).{0,50}(?:tx hash|txid|transaction hash|хеш)\b/iu', $text) === 1) {
            return 'operator_requested_tx_hash';
        }
        if (preg_match('/\b(?:пришлите|отправьте|send|provide).{0,50}(?:screenshot|скрин|proof|receipt|квитан|подтвержден)\b/iu', $text) === 1) {
            return 'operator_requested_payment_proof';
        }
        if (preg_match('/\b(?:пришлите|укажите|send|provide).{0,50}(?:wallet|кошел|адрес|network|сеть|trc20|erc20|bep20)\b/iu', $text) === 1) {
            return 'operator_requested_wallet_or_network';
        }
        if (preg_match('/\b(?:эскала|escalat|передан|передано|админ|admin|старш|senior)\b/iu', $text) === 1) {
            return 'operator_escalated_to_admin';
        }
        if (preg_match('/\b(?:закрыт|resolved|closed|решено|выполнена|завершена)\b/iu', $text) === 1) {
            return 'operator_closed_or_resolved';
        }
        if (preg_match('/\b(?:статус|status|обработк|processing|принята|выполняется|waiting|этап)\b/iu', $text) === 1) {
            return 'operator_gave_status';
        }
        if (preg_match('/\b(?:вручную|manually|manual)\b/iu', $text) === 1) {
            return 'operator_said_manual_verification';
        }
        if (preg_match('/\b(?:задержк|delay|занимает|может занять|waiting time|дольше обычного)\b/iu', $text) === 1) {
            return 'operator_explained_delay';
        }
        if (preg_match('/\b(?:уточните|please clarify|which|какой|какая|what network)\b/iu', $text) === 1) {
            return 'operator_asked_clarifying_question';
        }
        if (preg_match('/\b(?:проверим|уточним|посмотрим|разбер|check|verify|look into|will review)\b/iu', $text) === 1) {
            return 'operator_promised_check';
        }

        return 'unknown_operator_action';
    }

    /**
     * @param  array{action: string, snippet: string, message_id: int}  $operatorContext
     * @param  array{
     *     order_ids: list<string>,
     *     tx_hashes: list<string>,
     *     amounts: list<string>,
     *     networks: list<string>,
     *     wallets: list<string>,
     *     directions: list<string>,
     *     payment_methods: list<string>,
     *     has_payment_proof: bool,
     * }  $knownData
     * @return array{
     *     provided_order_id: bool,
     *     provided_tx_hash: bool,
     *     provided_payment_proof: bool,
     *     provided_wallet_or_network: bool,
     * }
     */
    private function detectVisitorProvidedDataAfterRequest(
        Collection $messages,
        SupportMessage $triggerMessage,
        array $knownData,
        array $operatorContext,
    ): array {
        $provided = [
            'provided_order_id' => false,
            'provided_tx_hash' => false,
            'provided_payment_proof' => false,
            'provided_wallet_or_network' => false,
        ];

        $operatorMsgId = $operatorContext['message_id'];
        if ($operatorMsgId === 0) {
            return $provided;
        }

        $afterOperator = '';
        foreach ($messages as $msg) {
            if ($msg->id <= $operatorMsgId) {
                continue;
            }
            if ($msg->sender_type !== SupportMessage::SENDER_VISITOR) {
                continue;
            }
            $afterOperator .= ' '.((string) $msg->body);
        }

        $action = $operatorContext['action'];

        if ($action === 'operator_requested_order_id') {
            $provided['provided_order_id'] = preg_match('/\b\d{10,}\b/u', $afterOperator) === 1;
        }
        if ($action === 'operator_requested_tx_hash') {
            $provided['provided_tx_hash'] = preg_match('/\b0x[0-9a-fA-F]{40,}\b/i', $afterOperator) === 1
                || preg_match('/\b[0-9a-fA-F]{64}\b/', $afterOperator) === 1
                || preg_match('/\b(?:txid|tx hash|tx:)/iu', $afterOperator) === 1;
        }
        if ($action === 'operator_requested_payment_proof') {
            $provided['provided_payment_proof'] = preg_match(
                '/\b(?:screenshot|скрин|proof|receipt|квитан|фото|photo|txid|tx hash)\b/iu',
                $afterOperator
            ) === 1 || $knownData['has_payment_proof'];
        }
        if ($action === 'operator_requested_wallet_or_network') {
            $provided['provided_wallet_or_network'] = $knownData['networks'] !== []
                || $knownData['wallets'] !== []
                || preg_match('/\b(?:TRC20|ERC20|BEP20|wallet|кошел|адрес)\b/iu', $afterOperator) === 1;
        }

        return $provided;
    }

    /**
     * @param  array{action: string, snippet: string, message_id: int}  $operatorContext
     * @param  array{
     *     provided_order_id: bool,
     *     provided_tx_hash: bool,
     *     provided_payment_proof: bool,
     *     provided_wallet_or_network: bool,
     * }  $providedAfterRequest
     * @param  array{
     *     order_ids: list<string>,
     *     tx_hashes: list<string>,
     *     amounts: list<string>,
     *     networks: list<string>,
     *     wallets: list<string>,
     *     directions: list<string>,
     *     payment_methods: list<string>,
     *     has_payment_proof: bool,
     * }  $knownData
     */
    private function buildOperatorActionAwarenessBlock(
        array $operatorContext,
        array $providedAfterRequest,
        array $knownData,
        bool $isFollowUp,
        string $stage,
    ): string {
        $lines = ['Operator action awareness:'];
        $action = $operatorContext['action'];

        if ($operatorContext['message_id'] === 0) {
            $lines[] = '- No prior operator message in recent context.';
            $lines[] = '- Next best response: '.SupportAiReplyPlaybook::answerSelectionGuidance($stage);

            return implode("\n", $lines);
        }

        $lines[] = '- Last operator action: '.$action.'.';
        if ($operatorContext['snippet'] !== '') {
            $lines[] = '- Operator said: '.$operatorContext['snippet'];
        }

        if ($providedAfterRequest['provided_order_id']) {
            $lines[] = '- Visitor has now provided order ID.';
            $lines[] = '- Do not ask for order ID again.';
        }
        if ($providedAfterRequest['provided_tx_hash']) {
            $lines[] = '- Visitor has now provided TX hash.';
            $lines[] = '- Do not ask for TX hash again.';
        }
        if ($providedAfterRequest['provided_payment_proof']) {
            $lines[] = '- Visitor has now provided payment proof.';
            $lines[] = '- Do not ask for proof/screenshot again.';
        }
        if ($providedAfterRequest['provided_wallet_or_network']) {
            $lines[] = '- Visitor has now provided wallet/network details.';
            $lines[] = '- Do not ask for wallet/network again.';
        }

        if ($isFollowUp && in_array($action, ['operator_promised_check', 'operator_said_manual_verification'], true)) {
            $lines[] = '- Operator already promised to check; visitor is following up.';
            $lines[] = '- Avoid repeating only "we will check" — acknowledge delay and re-check / escalate safely.';
        }

        if ($action === 'operator_escalated_to_admin' && $isFollowUp) {
            $lines[] = '- Issue already escalated; do not say "we will pass to operator" again.';
        }

        if ($action === 'operator_gave_status' && $isFollowUp) {
            $lines[] = '- Operator already gave a status update; visitor asks again — clarify reason/status stage without fake promises.';
        }

        $lines[] = '- Next best response: '.SupportAiReplyPlaybook::operatorActionGuidance(
            $action,
            $providedAfterRequest,
            $knownData,
            $isFollowUp,
            $stage,
        );

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, SupportMessage>  $messages
     * @param  array{
     *     order_ids: list<string>,
     *     tx_hashes: list<string>,
     *     amounts: list<string>,
     *     networks: list<string>,
     *     wallets: list<string>,
     *     directions: list<string>,
     *     payment_methods: list<string>,
     *     has_payment_proof: bool,
     * }  $knownData
     * @param  array{action: string, snippet: string, message_id: int}  $operatorContext
     * @param  array{
     *     provided_order_id: bool,
     *     provided_tx_hash: bool,
     *     provided_payment_proof: bool,
     *     provided_wallet_or_network: bool,
     * }  $providedAfterRequest
     */
    private function detectConversationStage(
        Collection $messages,
        array $knownData,
        bool $isFollowUp,
        string $intent,
        array $operatorContext = ['action' => 'unknown_operator_action', 'snippet' => '', 'message_id' => 0],
        array $providedAfterRequest = [
            'provided_order_id' => false,
            'provided_tx_hash' => false,
            'provided_payment_proof' => false,
            'provided_wallet_or_network' => false,
        ],
    ): string {
        if ($providedAfterRequest['provided_order_id']) {
            return 'visitor_provided_order_after_request';
        }
        if ($providedAfterRequest['provided_tx_hash']) {
            return 'visitor_provided_tx_after_request';
        }
        if ($providedAfterRequest['provided_payment_proof']) {
            return 'visitor_provided_proof_after_request';
        }
        if ($providedAfterRequest['provided_wallet_or_network']) {
            return 'visitor_provided_wallet_after_request';
        }

        if (in_array($intent, ['eta_request', 'funds_safety_question'], true)) {
            return 'sensitive_expectation_question';
        }

        if ($intent === 'payment_proof_only') {
            return 'visitor_sent_proof_only';
        }

        $lastAction = $operatorContext['action'];

        if ($lastAction === 'operator_escalated_to_admin' && $isFollowUp) {
            return 'operator_escalated_follow_up';
        }

        if ($lastAction === 'operator_gave_status' && $isFollowUp) {
            return 'operator_gave_status_follow_up';
        }

        if ($isFollowUp || $intent === 'repeated_follow_up') {
            if ($knownData['tx_hashes'] !== []) {
                return 'follow_up_with_tx';
            }
            if ($knownData['order_ids'] !== []) {
                return 'follow_up_with_order';
            }

            return 'follow_up_general';
        }

        if (in_array($lastAction, ['operator_promised_check', 'operator_said_manual_verification'], true)) {
            if ($knownData['order_ids'] !== []) {
                return 'operator_promised_check_with_order';
            }

            return 'operator_promised_check';
        }

        if ($knownData['tx_hashes'] !== []) {
            return 'visitor_sent_tx';
        }

        if ($knownData['order_ids'] !== []) {
            return 'first_message_with_order';
        }

        if ($intent === 'missing_order_id') {
            return 'first_message_no_order';
        }

        if ($intent === 'large_otc_exchange') {
            return 'otc_inquiry';
        }

        if ($intent === 'complaint_or_angry_customer') {
            return 'angry_complaint';
        }

        if (in_array($intent, ['wrong_amount', 'wrong_network'], true)) {
            return 'issue_details_provided';
        }

        return 'first_message_general';
    }

    /**
     * @param  list<string>  $protected
     * @return array{
     *     order_ids: list<string>,
     *     tx_hashes: list<string>,
     *     amounts: list<string>,
     *     networks: list<string>,
     *     wallets: list<string>,
     *     directions: list<string>,
     *     payment_methods: list<string>,
     *     has_payment_proof: bool,
     * }
     */
    private function extractKnownConversationData(string $rawContext, array $protected): array
    {
        $data = [
            'order_ids' => [],
            'tx_hashes' => [],
            'amounts' => [],
            'networks' => [],
            'wallets' => [],
            'directions' => [],
            'payment_methods' => [],
            'has_payment_proof' => false,
        ];

        foreach ($protected as $id) {
            $id = (string) $id;
            if (preg_match('/^\d{10,}$/', $id) === 1) {
                $data['order_ids'][] = $id;
            } elseif (preg_match('/^0x[0-9a-fA-F]{40,}$/i', $id) === 1 || preg_match('/^[0-9a-fA-F]{64}$/', $id) === 1) {
                $data['tx_hashes'][] = $id;
            } elseif (preg_match('/\d+(?:[.,]\d+)?\s*(?:USDT|USD|BTC|ETH|RUB|EUR|GEL|UAH|TRX|TON|LTC|XRP)/iu', $id) === 1) {
                $data['amounts'][] = $id;
            } elseif (preg_match('/^(TRC20|ERC20|BEP20|TRON|Ethereum|Bitcoin|TON|SOL|Arbitrum|Polygon|BSC)$/iu', $id) === 1) {
                $data['networks'][] = $id;
            }
        }

        if (preg_match_all('/\b\d{10,}\b/u', $rawContext, $m) > 0) {
            foreach ($m[0] as $hit) {
                if (! in_array($hit, $data['order_ids'], true)) {
                    $data['order_ids'][] = $hit;
                }
            }
        }

        if (preg_match_all('/\b0x[0-9a-fA-F]{40,64}\b/i', $rawContext, $m2) > 0) {
            foreach ($m2[0] as $hit) {
                if (! in_array($hit, $data['tx_hashes'], true)) {
                    $data['tx_hashes'][] = $hit;
                }
            }
        }

        if (preg_match_all('/\b[0-9a-fA-F]{64}\b/', $rawContext, $m3) > 0) {
            foreach ($m3[0] as $hit) {
                if (! in_array($hit, $data['tx_hashes'], true)) {
                    $data['tx_hashes'][] = $hit;
                }
            }
        }

        if (preg_match_all('/\b\d+(?:[.,]\d+)?\s*(?:USDT|USD|BTC|ETH|RUB|EUR|GEL|UAH|TRX|TON|LTC|XRP)\b/iu', $rawContext, $m4) > 0) {
            foreach ($m4[0] as $hit) {
                $hit = trim($hit);
                if (! in_array($hit, $data['amounts'], true)) {
                    $data['amounts'][] = $hit;
                }
            }
        }

        if (preg_match_all('/\b(?:TRC20|ERC20|BEP20|TRON|Ethereum|Bitcoin|TON|SOL|Arbitrum|Polygon|BSC)\b/iu', $rawContext, $m5) > 0) {
            foreach ($m5[0] as $hit) {
                if (! in_array($hit, $data['networks'], true)) {
                    $data['networks'][] = $hit;
                }
            }
        }

        if (preg_match_all('/\bT[A-Za-z0-9]{33}\b/', $rawContext, $m6) > 0) {
            foreach ($m6[0] as $hit) {
                if (! in_array($hit, $data['wallets'], true)) {
                    $data['wallets'][] = $hit;
                }
            }
        }

        if (preg_match_all('/\b(?:bc1|[13])[a-zA-HJ-NP-Z0-9]{25,39}\b/', $rawContext, $m7) > 0) {
            foreach ($m7[0] as $hit) {
                if (! in_array($hit, $data['wallets'], true)) {
                    $data['wallets'][] = $hit;
                }
            }
        }

        if (preg_match_all('/\b(?:\w{2,10})\s*(?:to|->|→|на|в)\s*(?:\w{2,10})\b/iu', $rawContext, $m8) > 0) {
            foreach ($m8[0] as $hit) {
                $hit = trim($hit);
                if (! in_array($hit, $data['directions'], true)) {
                    $data['directions'][] = $hit;
                }
            }
        }

        if (preg_match('/\b(?:card|bank|sepa|swift|сбп|карт|банк|перевод)\b/iu', $rawContext) === 1) {
            if (preg_match_all('/\b(?:card|bank|sepa|swift|сбп|карт(?:а|ой)?|банк(?:овский)?)\b/iu', $rawContext, $m9) > 0) {
                foreach ($m9[0] as $hit) {
                    if (! in_array(mb_strtolower($hit, 'UTF-8'), $data['payment_methods'], true)) {
                        $data['payment_methods'][] = mb_strtolower($hit, 'UTF-8');
                    }
                }
            }
        }

        $data['has_payment_proof'] = preg_match(
            '/\b(?:screenshot|скрин|proof|receipt|квитан|attachment|вложен|фото|photo)\b/iu',
            $rawContext
        ) === 1;

        return $data;
    }

    /**
     * @param  Collection<int, SupportMessage>  $messages
     * @param  array{
     *     order_ids: list<string>,
     *     tx_hashes: list<string>,
     *     amounts: list<string>,
     *     networks: list<string>,
     *     wallets: list<string>,
     *     directions: list<string>,
     *     payment_methods: list<string>,
     *     has_payment_proof: bool,
     * }  $knownData
     */
    private function buildConversationMemory(
        Collection $messages,
        SupportMessage $triggerMessage,
        array $knownData,
        bool $isFollowUp,
        string $intent,
        string $stage,
    ): string {
        $lines = ['Conversation memory:'];

        $visitorTopics = [];
        $operatorActions = [];

        foreach ($messages as $msg) {
            if ($msg->id === $triggerMessage->id) {
                continue;
            }

            $body = $this->sanitizeTextForAiContext((string) $msg->body);
            $snippet = $this->truncateUtf8($body, 120);

            if ($msg->sender_type === SupportMessage::SENDER_VISITOR) {
                $visitorTopics[] = $snippet;
            } elseif ($msg->sender_type === SupportMessage::SENDER_OPERATOR) {
                $operatorActions[] = $snippet;
            }
        }

        if ($visitorTopics !== []) {
            $first = $visitorTopics[0];
            if ($knownData['order_ids'] !== []) {
                $lines[] = '- Visitor first asked about order #'.$knownData['order_ids'][0].' ('.$first.').';
            } else {
                $lines[] = '- Visitor first message: '.$first;
            }
        }

        foreach ($operatorActions as $action) {
            $lines[] = '- Operator said: '.$action;
        }

        if ($isFollowUp) {
            $lines[] = '- Visitor is following up: '.$this->truncateUtf8(
                $this->sanitizeTextForAiContext((string) $triggerMessage->body),
                120
            );
        }

        $knownParts = [];
        if ($knownData['order_ids'] !== []) {
            $knownParts[] = 'order_id='.implode(',', $knownData['order_ids']);
        }
        if ($knownData['tx_hashes'] !== []) {
            $knownParts[] = 'tx_hash='.implode(',', array_map(
                fn (string $h): string => $this->truncateUtf8($h, 16),
                $knownData['tx_hashes']
            ));
        }
        if ($knownData['amounts'] !== []) {
            $knownParts[] = 'amount='.implode(',', $knownData['amounts']);
        }
        if ($knownData['networks'] !== []) {
            $knownParts[] = 'network='.implode(',', $knownData['networks']);
        }
        if ($knownData['wallets'] !== []) {
            $knownParts[] = 'wallet='.count($knownData['wallets']).' address(es)';
        }
        if ($knownData['directions'] !== []) {
            $knownParts[] = 'direction='.implode(',', array_slice($knownData['directions'], 0, 2));
        }
        if ($knownData['payment_methods'] !== []) {
            $knownParts[] = 'payment_method='.implode(',', $knownData['payment_methods']);
        }
        if ($knownData['has_payment_proof']) {
            $knownParts[] = 'payment_proof=mentioned';
        }

        if ($knownParts !== []) {
            $lines[] = '- Known data: '.implode('; ', $knownParts).'.';
        }

        $provided = $this->summarizeProvidedData($knownData);
        if ($provided['do_not_ask'] !== []) {
            $lines[] = '- Do not ask for '.implode(', ', $provided['do_not_ask']).' again.';
        }

        $lines[] = '- Next best response: '.SupportAiReplyPlaybook::answerSelectionGuidance($stage);

        return $this->truncateUtf8(implode("\n", $lines), self::MAX_MEMORY_CHARS);
    }

    /**
     * @param  Collection<int, SupportMessage>  $messages
     */
    private function formatRecentConversationForPrompt(Collection $messages): string
    {
        $lines = [];
        foreach ($messages as $msg) {
            $role = match ($msg->sender_type) {
                SupportMessage::SENDER_OPERATOR => 'operator',
                SupportMessage::SENDER_SYSTEM => 'system',
                default => 'visitor',
            };
            $lines[] = sprintf(
                '[%s] %s',
                $role,
                $this->truncateUtf8($this->sanitizeTextForAiContext((string) $msg->body), self::MAX_MESSAGE_CHARS)
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $protected
     */
    private function protectedHasOrderId(array $protected): bool
    {
        foreach ($protected as $id) {
            if (preg_match('/^\d{10,}$/', (string) $id) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, SupportMessage>  $messages
     */
    private function shouldUseGreeting(Collection $messages, SupportMessage $triggerMessage): bool
    {
        $body = trim((string) $triggerMessage->body);
        $lower = mb_strtolower($body, 'UTF-8');

        $visitorGreeted = preg_match(
            '/^(?:\s)*(?:здравств|добр(?:ый|ого|ая|ое)?|привет|hello|hi\b|good morning|good afternoon|добрий|გამარჯ)/iu',
            $lower
        ) === 1;

        $priorVisitorCount = $messages->filter(
            static fn (SupportMessage $m): bool => $m->sender_type === SupportMessage::SENDER_VISITOR
                && $m->id < $triggerMessage->id
        )->count();

        $operatorReplied = $messages->contains(
            static fn (SupportMessage $m): bool => $m->sender_type === SupportMessage::SENDER_OPERATOR
        );

        if ($priorVisitorCount === 0 && ! $operatorReplied) {
            return $visitorGreeted;
        }

        return $visitorGreeted;
    }

    /**
     * @param  array{
     *     order_ids: list<string>,
     *     tx_hashes: list<string>,
     *     amounts: list<string>,
     *     networks: list<string>,
     *     wallets: list<string>,
     *     directions: list<string>,
     *     payment_methods: list<string>,
     *     has_payment_proof: bool,
     * }  $knownData
     * @return array{has: list<string>, missing: list<string>, do_not_ask: list<string>}
     */
    private function summarizeProvidedData(array $knownData): array
    {
        $has = [];
        $doNotAsk = [];

        foreach ($knownData['order_ids'] as $id) {
            $has[] = 'order ID '.$id;
            $doNotAsk[] = 'order ID';
        }
        foreach ($knownData['tx_hashes'] as $hash) {
            $has[] = 'TX hash '.$this->truncateUtf8($hash, 20);
            $doNotAsk[] = 'TX hash';
        }
        foreach ($knownData['amounts'] as $amount) {
            $has[] = 'amount '.$amount;
            $doNotAsk[] = 'amount';
        }
        foreach ($knownData['networks'] as $network) {
            $has[] = 'network '.$network;
            $doNotAsk[] = 'network';
        }
        foreach ($knownData['wallets'] as $wallet) {
            $has[] = 'wallet '.$this->truncateUtf8($wallet, 16);
            $doNotAsk[] = 'wallet address';
        }
        foreach ($knownData['directions'] as $direction) {
            $has[] = 'direction '.$direction;
            $doNotAsk[] = 'exchange direction';
        }
        foreach ($knownData['payment_methods'] as $method) {
            $has[] = 'payment method '.$method;
            $doNotAsk[] = 'payment method';
        }
        if ($knownData['has_payment_proof']) {
            $has[] = 'payment proof mention';
            $doNotAsk[] = 'payment proof/screenshot';
        }

        $missing = [];
        if ($knownData['order_ids'] === []) {
            $missing[] = 'order ID';
        }
        if ($knownData['tx_hashes'] === [] && $knownData['has_payment_proof'] === false) {
            $missing[] = 'TX hash (if payment-related)';
        }

        $doNotAsk = array_values(array_unique($doNotAsk));

        return ['has' => $has, 'missing' => $missing, 'do_not_ask' => $doNotAsk];
    }

    private function systemPrompt(
        string $language,
        int $choices,
        bool $telegramCompact = false,
        string $intent = 'unknown_context',
        string $stage = 'first_message_general',
        array $orderLookup = [],
        array $directionLookup = [],
    ): string {
        $playbook = SupportAiReplyPlaybook::categoryPlaybook();
        $styleExamples = SupportAiReplyPlaybook::promptBlock($language, $intent);
        $operatorTone = SupportAiReplyPlaybook::operatorToneRules();
        $varietyRules = SupportAiReplyPlaybook::varietyRules($choices);
        $greetingRules = SupportAiReplyPlaybook::greetingRules();
        $contextAware = SupportAiReplyPlaybook::contextAwareRules();
        $memoryRules = SupportAiReplyPlaybook::conversationMemoryRules();
        $answerSelection = SupportAiReplyPlaybook::answerSelectionRules();
        $actionAwareRules = SupportAiReplyPlaybook::operatorActionAwarenessRules();
        $edgeCaseRules = SupportAiReplyPlaybook::edgeCaseRules();
        $knowledgeRules = SupportAiKnowledgeService::promptSafetyBlock();
        $templateRules = SupportAiTemplateService::promptSafetyBlock();
        $hasVerifiedOrderStatus = ! empty($orderLookup['verified_order_status']);
        $verifiedOrderRules = $this->verifiedOrderDraftRulesBlock($orderLookup);
        $verifiedDirectionRules = $this->verifiedDirectionDraftRulesBlock($directionLookup);
        $exchangeDirectionRules = $this->exchangeDirectionIntentRulesBlock($intent, $directionLookup);
        $hasKnownDirectionAvailability = ! empty($directionLookup['direction_lookup_found']);
        $verifiedStatusContextRule = $hasVerifiedOrderStatus
            ? 'Verified order status IS in conversation context — use order_status_human and order_safe_summary as facts. LEAD with status; do NOT open with "Проверим заявку" deferral.'
            : 'Never claim funds were sent, payment confirmed, AML cleared, or order completed unless explicitly verified in context (context does NOT include verified order status — assume unverified).';
        $multiOptionStyleGuide = str_replace(
            '{$choices}',
            (string) $choices,
            ($hasVerifiedOrderStatus || $hasKnownDirectionAvailability)
                ? <<<'GUIDE'
Generate exactly {$choices} MEANINGFULLY DIFFERENT reply options (internal style keys only — never show style names to visitor):
1) short_professional — lead with verified status or direction availability, explain meaning in plain language, state next step
2) warm_reassurance — warmer human tone with verified facts first; no fake ETA or fake confirmation
3) detailed_checklist — verified status/availability plus what it means — concise, no fabricated facts
4) clarifying_next_step — verified facts plus only missing info OR safe expectation-setting (not wallet when direction unavailable)
GUIDE
                : <<<'GUIDE'
Generate exactly {$choices} MEANINGFULLY DIFFERENT reply options (internal style keys only — never show style names to visitor):
1) short_professional — direct, confident, checks status or states next step
2) warm_reassurance — warmer human tone WITHOUT "не переживайте" / fake ETA / fake confirmation
3) detailed_checklist — what operator will verify (status, payment, withdrawal stage) — concise, no fabricated facts
4) clarifying_next_step — ask only missing info (order ID, tx hash, network) OR safe expectation-setting
GUIDE,
        );

        $identifierRules = <<<'RULES'
IDENTIFIER RULES (mandatory):
- Copy all numeric identifiers EXACTLY as provided. Do not shorten, normalize, round, or modify order IDs, amounts, tx hashes, currencies, or networks.
- If context lists PROTECTED IDENTIFIERS, each must appear verbatim in relevant drafts (especially order/status replies).
- Never replace 1780070862386 with 178007086238 or similar truncations.
RULES;

        $safetyRules = <<<'SAFETY'
CORE SAFETY (mandatory):
- UNKNOWN STATUS must NEVER become "processing", "sent", "confirmed", "paid", "completed", or "soon".
- RU forbidden unless verified: "обрабатывается", "отправлено", "подтверждено", "завершено", "скоро", "гарантируем", "уже получен", "уже отправлен".
- EN forbidden unless verified: "processed", "being processed", "sent", "confirmed", "completed", "soon", "guaranteed", "already received".
- Never invent rates, fees, ETAs, blockchain times, or order statuses.
- Style examples are TONE ONLY — not source of truth for facts.
SAFETY;

        $languageRule = "Reply ONLY in {$language}. Do not mix languages unless the visitor clearly mixed them. Supported: ru, en, uk, ka.";

        if ($telegramCompact && $choices > 1) {
            return <<<PROMPT
You are an internal drafting assistant for Exswaping crypto exchange / OTC support operators.

TELEGRAM COMPACT MODE (mandatory):
- Generate exactly {$choices} copy-paste-ready reply options for a separate Telegram operator message.
- Each option: complete reply, 1–2 short sentences, one paragraph. No bullets. No line breaks inside option text.
- Telegram output uses emoji numbers + <code> blocks — do NOT include labels like Short/Warm/Detailed in the text.

{$contextAware}

{$memoryRules}

{$actionAwareRules}

{$edgeCaseRules}

{$knowledgeRules}

{$templateRules}

{$answerSelection}

{$greetingRules}

{$operatorTone}

{$varietyRules}

{$playbook}

{$styleExamples}

{$verifiedOrderRules}

{$verifiedDirectionRules}

{$exchangeDirectionRules}

CRITICAL RULES:
- OPERATOR-ASSIST ONLY. Never auto-send.
- Read Conversation memory and recent messages — do NOT repeat what operator already said verbatim; suggest the NEXT useful reply.
- {$verifiedStatusContextRule}
- {$languageRule}

{$safetyRules}

{$identifierRules}

Respond ONLY with valid JSON (no markdown fences):
{"confidence":"high|medium|low","warnings":["..."],"options":[{"style":"short_professional","text":"..."},{"style":"warm_reassurance","text":"..."},{"style":"detailed_checklist","text":"..."},{"style":"clarifying_next_step","text":"..."}]}
PROMPT;
        }

        if ($choices <= 1) {
            return <<<PROMPT
You are an internal drafting assistant for Exswaping crypto exchange / OTC support operators.

{$contextAware}

{$memoryRules}

{$actionAwareRules}

{$edgeCaseRules}

{$knowledgeRules}

{$templateRules}

{$answerSelection}

{$greetingRules}

{$operatorTone}

{$verifiedOrderRules}

{$verifiedDirectionRules}

{$exchangeDirectionRules}

CRITICAL RULES:
- Output is OPERATOR-ASSIST ONLY. A human operator must review, edit, and send manually.
- {$verifiedStatusContextRule}
- Never invent rates, fees, ETAs, blockchain confirmation times, or order statuses.
- Never provide private keys, seed phrases, wallet recovery, or security bypass instructions.
- Never give trading/investment advice or legal/AML guarantees.
- Never advise evading AML, sanctions, fraud checks, chargebacks, or laundering.
- If unsure, ask the operator to verify in admin systems before confirming anything to the visitor.
- {$languageRule}
- Length: 2–5 short sentences unless OTC intake needs a few bullet questions.

{$safetyRules}

{$identifierRules}

{$playbook}

{$styleExamples}

Respond ONLY with valid JSON (no markdown fences):
{"draft":"...","confidence":"high|medium|low","warnings":["..."]}
PROMPT;
        }

        return <<<PROMPT
You are an internal drafting assistant for Exswaping crypto exchange / OTC support operators.

{$contextAware}

{$memoryRules}

{$actionAwareRules}

{$edgeCaseRules}

{$knowledgeRules}

{$templateRules}

{$answerSelection}

{$greetingRules}

{$operatorTone}

{$varietyRules}

{$verifiedOrderRules}

{$verifiedDirectionRules}

{$exchangeDirectionRules}

CRITICAL RULES:
- Output is OPERATOR-ASSIST ONLY. A human operator must review, edit, and send manually.
- Read Conversation memory and recent messages — suggest the NEXT useful reply, not a repeat of prior operator text.
- {$verifiedStatusContextRule}
- Never invent rates, fees, ETAs, blockchain confirmation times, or order statuses.
- Never provide private keys, seed phrases, wallet recovery, or security bypass instructions.
- Never give trading/investment advice or legal/AML guarantees.
- {$languageRule}

{$safetyRules}

{$identifierRules}

{$playbook}

{$styleExamples}

{$multiOptionStyleGuide}

Each option must be copy-ready and structurally different. No fake ETA or payment confirmation.

Respond ONLY with valid JSON (no markdown fences):
{"confidence":"high|medium|low","warnings":["..."],"options":[{"style":"short_professional","text":"..."},{"style":"warm_reassurance","text":"..."},{"style":"detailed_checklist","text":"..."},{"style":"clarifying_next_step","text":"..."}]}
PROMPT;
    }

    private function userPrompt(
        string $contextBlock,
        string $language,
        int $choices,
        bool $telegramCompact = false,
        string $intent = 'unknown_context',
        string $stage = 'first_message_general',
    ): string {
        if ($telegramCompact && $choices > 1) {
            return <<<PROMPT
Draft {$choices} context-aware Telegram operator replies for intent "{$intent}" at stage "{$stage}" (complete, 1–2 sentences, meaningfully different). Language: {$language}. Use conversation memory and operator action awareness — suggest the NEXT useful reply. Do NOT ask for data listed under "Do NOT ask again for". Follow greeting recommendation in context.

Conversation context:
{$contextBlock}
PROMPT;
        }

        if ($choices <= 1) {
            return <<<PROMPT
Draft a suggested operator reply for intent "{$intent}" at stage "{$stage}". Language: {$language}. Use conversation memory and operator action awareness — suggest the NEXT useful reply.

Conversation context:
{$contextBlock}
PROMPT;
        }

        return <<<PROMPT
Draft {$choices} context-aware operator reply options for intent "{$intent}" at stage "{$stage}". Language: {$language}. Use conversation memory and operator action awareness — suggest the NEXT useful reply. Do NOT ask for data already in thread.

Conversation context:
{$contextBlock}
PROMPT;
    }

    /**
     * @param  list<string>  $protected
     * @return array{
     *     draft: string|null,
     *     options: list<array{label: string, style: string, text: string}>,
     *     choices: int,
     *     confidence: string,
     *     warnings: list<string>,
     *     language: string,
     *     error: string|null,
     *     protected_identifiers: list<string>
     * }
     */
    private function parseModelOutput(
        string $content,
        string $language,
        int $choices,
        array $protected,
        bool $greetingRecommended = false,
        array $knownData = [],
        string $stage = 'first_message_general',
        array $operatorContext = ['action' => 'unknown_operator_action', 'snippet' => '', 'message_id' => 0],
        bool $policyOnly = false,
        bool $policyProtected = false,
        array $orderLookup = [],
    ): array {
        $hasVerifiedStatus = ! empty($orderLookup['verified_order_status']);
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return [
                'draft' => $content,
                'options' => [],
                'choices' => $choices,
                'confidence' => 'low',
                'warnings' => ['Model returned non-JSON; review carefully.'],
                'language' => $language,
                'error' => null,
                'protected_identifiers' => $protected,
            ];
        }

        $confidence = in_array($decoded['confidence'] ?? '', ['high', 'medium', 'low'], true)
            ? (string) $decoded['confidence']
            : 'medium';

        $warnings = $this->parseWarningsArray($decoded['warnings'] ?? []);

        if ($choices > 1 && isset($decoded['options']) && is_array($decoded['options'])) {
            $options = $this->normalizeOptions($decoded['options'], $choices);
            if ($this->ux->isDedupEnabled()) {
                $options = $this->ux->deduplicateOptions($options);
            }
            if ($policyProtected && $this->ux->isPolicyProtectionEnabled()) {
                $options = array_values(array_filter(
                    $options,
                    fn (array $option): bool => ! $this->ux->isPrimaryOrderIdRequest((string) ($option['text'] ?? '')),
                ));
            }
            $options = array_slice($options, 0, $choices);
            $result = [
                'draft' => $options[0]['text'] ?? null,
                'options' => $options,
                'choices' => $choices,
                'confidence' => $confidence,
                'warnings' => $warnings,
                'language' => $language,
                'error' => null,
                'protected_identifiers' => $protected,
            ];
            $result = $this->applyVerifiedOrderDraftPolish($result, $orderLookup);
            $texts = array_map(static fn (array $o) => $o['text'], $result['options']);
            $warnings = array_merge(
                $warnings,
                $this->postProcessWarnings($texts, $hasVerifiedStatus, $orderLookup),
                $this->verifyIdentifierPreservation($protected, $texts),
                $this->verifyOptionVariety($texts),
                $this->verifyExcessiveGreetings($texts, $greetingRecommended),
                $this->verifyRedundantDataRequests($texts, $knownData, $stage),
                $this->verifyRepeatedOperatorPromise($texts, $operatorContext, $stage),
                $this->verifyDeferralOpenersWhenVerified($texts, $hasVerifiedStatus),
            );
            if ($policyOnly && $policyProtected) {
                foreach ($texts as $text) {
                    if ($this->ux->isPrimaryOrderIdRequest($text)) {
                        $warnings[] = 'Policy question: suggestion asks for order ID despite knowledge/template match.';
                        break;
                    }
                }
            }
            $warnings = array_values(array_unique($warnings));
            $result['warnings'] = $warnings;

            return $result;
        }

        if (isset($decoded['draft']) && is_string($decoded['draft'])) {
            $draft = trim($decoded['draft']);
            $result = [
                'draft' => $draft !== '' ? $draft : null,
                'options' => $draft !== '' ? [[
                    'label' => self::OPTION_LABELS[0],
                    'style' => self::OPTION_STYLES[0],
                    'text' => $draft,
                ]] : [],
                'choices' => 1,
                'confidence' => $confidence,
                'warnings' => $warnings,
                'language' => $language,
                'error' => null,
                'protected_identifiers' => $protected,
            ];
            $result = $this->applyVerifiedOrderDraftPolish($result, $orderLookup);
            $draftText = (string) ($result['draft'] ?? '');
            $warnings = array_merge(
                $warnings,
                $this->postProcessWarnings($draftText !== '' ? [$draftText] : [], $hasVerifiedStatus, $orderLookup),
                $this->verifyIdentifierPreservation($protected, $draftText !== '' ? [$draftText] : []),
                $this->verifyDeferralOpenersWhenVerified($draftText !== '' ? [$draftText] : [], $hasVerifiedStatus),
            );
            $warnings = array_values(array_unique($warnings));
            $result['warnings'] = $warnings;

            return $result;
        }

        return [
            'draft' => null,
            'options' => [],
            'choices' => $choices,
            'confidence' => 'low',
            'warnings' => array_merge(['Unexpected model JSON shape; review carefully.'], $warnings),
            'language' => $language,
            'error' => 'invalid_json_shape',
            'protected_identifiers' => $protected,
        ];
    }

    /**
     * @param  list<mixed>  $raw
     * @return list<array{label: string, style: string, text: string}>
     */
    private function normalizeOptions(array $raw, int $choices): array
    {
        $options = [];
        $count = min($choices, count($raw));

        for ($i = 0; $i < $count; $i++) {
            $item = $raw[$i];
            if (! is_array($item) || ! isset($item['text']) || ! is_string($item['text'])) {
                continue;
            }
            $text = trim($item['text']);
            if ($text === '') {
                continue;
            }
            $style = is_string($item['style'] ?? null) ? (string) $item['style'] : (self::OPTION_STYLES[$i] ?? 'option_'.($i + 1));
            $options[] = [
                'label' => self::OPTION_LABELS[$i] ?? ('Option '.($i + 1)),
                'style' => $style,
                'text' => $text,
            ];
        }

        return $options;
    }

    /**
     * @param  list<mixed>  $raw
     * @return list<string>
     */
    private function parseWarningsArray(array $raw): array
    {
        $warnings = [];
        foreach ($raw as $w) {
            if (is_string($w) && trim($w) !== '') {
                $warnings[] = trim($w);
            }
        }

        return $warnings;
    }

    /**
     * @param  list<string>  $drafts
     * @return list<string>
     */
    private function postProcessWarnings(array $drafts, bool $hasVerifiedStatus = false, array $orderLookup = []): array
    {
        $warnings = [];
        $paymentVerified = ! empty($orderLookup['payment_received']);
        $skipWhenVerified = [
            'находится в обработке',
            'в обработке',
            'being processed',
        ];
        if ($paymentVerified) {
            $skipWhenVerified[] = 'already received';
            $skipWhenVerified[] = 'уже получен';
        }

        $patterns = [
            'order (?:is )?completed' => 'Draft claims order completed — verify in admin before sending.',
            'payment confirmed' => 'Draft claims payment confirmed — verify in admin before sending.',
            'funds (?:have been |were )?sent' => 'Draft claims funds sent — verify in admin before sending.',
            'funds sent' => 'Draft claims funds sent — verify in admin before sending.',
            'guaranteed' => 'Draft may overpromise (guaranteed) — verify before sending.',
            'гарантируем' => 'Draft may overpromise (гарантируем) — verify before sending.',
            'заявка завершена' => 'Draft claims order completed — verify in admin before sending.',
            'заявка обработана' => 'Draft claims order processed — verify in admin before sending.',
            'средства отправлены' => 'Draft claims funds sent — verify in admin before sending.',
            'плат[её]ж подтвержден' => 'Draft claims payment confirmed — verify in admin before sending.',
            'находится в обработке' => 'Draft states order is in processing — use "Проверим статус" instead.',
            'в обработке' => 'Draft states order is in processing — use "Проверим статус" instead.',
            'being processed' => 'Draft claims order is being processed — use "we will check status" instead.',
            'will arrive in \d+' => 'Draft contains exact ETA — verify before sending.',
            'within \d+ (?:minutes|hours|days)' => 'Draft contains exact ETA — verify before sending.',
            'через \d+ (?:минут|час|дн)' => 'Draft contains exact ETA — verify before sending.',
            'скоро поступ' => 'Draft promises funds arrival — verify before sending.',
            'в ближайшее время' => 'Draft uses vague ETA — avoid timing promises unless verified.',
            'как только получим' => 'Draft uses template timing phrase — prefer concrete check wording.',
            'не переживайте' => 'Draft uses generic reassurance — prefer specific next step.',
            'ваши средства в безопасности' => 'Draft claims funds are safe — do not guarantee safety without verification.',
            'funds are safe' => 'Draft claims funds are safe — do not guarantee safety without verification.',
            'восстановим средства' => 'Draft promises fund recovery — verify before sending.',
            'we will recover' => 'Draft promises fund recovery — verify before sending.',
            'обязательно восстанов' => 'Draft overpromises recovery — verify before sending.',
            'will be completed' => 'Draft claims order will complete — verify before sending.',
            'будет выполнена' => 'Draft claims order will complete — verify before sending.',
            'скоро' => 'Draft uses "скоро" — avoid timing promises unless verified.',
            'already received' => 'Draft claims payment received — verify in admin before sending.',
            'уже получен' => 'Draft claims payment received — verify in admin before sending.',
            'уже отправлен' => 'Draft claims funds sent — verify in admin before sending.',
        ];

        foreach ($drafts as $draft) {
            $lower = mb_strtolower($draft, 'UTF-8');
            foreach ($patterns as $phrase => $warning) {
                if ($hasVerifiedStatus && in_array($phrase, $skipWhenVerified, true)) {
                    continue;
                }
                if (preg_match('/'.$phrase.'/iu', $lower) === 1) {
                    if (! in_array($warning, $warnings, true)) {
                        $warnings[] = $warning;
                    }
                }
            }
        }

        return $warnings;
    }

    /**
     * @param  list<string>  $drafts
     * @return list<string>
     */
    private function verifyOptionVariety(array $drafts): array
    {
        if (count($drafts) < 2) {
            return [];
        }

        $normalized = [];
        foreach ($drafts as $draft) {
            $n = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $draft) ?? $draft), 'UTF-8');
            $normalized[] = $n;
        }

        if (count(array_unique($normalized)) < count($normalized)) {
            return ['Some draft options are nearly identical; review before sending.'];
        }

        $openings = [];
        foreach ($normalized as $text) {
            $openings[] = mb_substr($text, 0, 30, 'UTF-8');
        }

        if (count(array_unique($openings)) === 1) {
            return ['Draft options open with the same wording; vary structure for operator choice.'];
        }

        return [];
    }

    /**
     * @param  list<string>  $drafts
     * @return list<string>
     */
    private function verifyExcessiveGreetings(array $drafts, bool $greetingRecommended): array
    {
        if ($greetingRecommended || $drafts === []) {
            return [];
        }

        $greetingCount = 0;
        foreach ($drafts as $draft) {
            $lower = mb_strtolower(trim($draft), 'UTF-8');
            if (preg_match('/^(?:\s)*(?:здравств|добр|привет|hello|hi\b|good morning|good afternoon|добрий)/iu', $lower) === 1) {
                $greetingCount++;
            }
        }

        if ($greetingCount >= 2) {
            return ['Multiple options start with a greeting; ongoing reply should start directly with action.'];
        }

        return [];
    }

    /**
     * @param  list<string>  $drafts
     * @param  array{
     *     order_ids: list<string>,
     *     tx_hashes: list<string>,
     *     amounts: list<string>,
     *     networks: list<string>,
     *     wallets: list<string>,
     *     directions: list<string>,
     *     payment_methods: list<string>,
     *     has_payment_proof: bool,
     * }  $knownData
     * @return list<string>
     */
    private function verifyRedundantDataRequests(array $drafts, array $knownData, string $stage): array
    {
        if ($drafts === []) {
            return [];
        }

        $warnings = [];
        $checks = [];

        if ($knownData['order_ids'] !== [] || in_array($stage, [
            'follow_up_with_order',
            'first_message_with_order',
            'operator_promised_check_with_order',
            'visitor_sent_tx',
            'visitor_provided_order_after_request',
            'operator_gave_status_follow_up',
            'operator_escalated_follow_up',
        ], true)) {
            $checks[] = [
                'pattern' => '/\b(?:пришлите|отправьте|укажите|send|provide|share).{0,40}(?:номер заявки|order (?:id|number|#)|номер обмена)\b/iu',
                'warning' => 'Draft asks for order ID but it is already in conversation — use re-check wording instead.',
            ];
        }

        if ($knownData['tx_hashes'] !== [] || in_array($stage, ['visitor_sent_tx', 'visitor_provided_tx_after_request', 'follow_up_with_tx'], true)) {
            $checks[] = [
                'pattern' => '/\b(?:пришлите|отправьте|укажите|send|provide|share).{0,40}(?:tx hash|txid|transaction hash|хеш)\b/iu',
                'warning' => 'Draft asks for TX hash but it is already in conversation.',
            ];
        }

        if (in_array($stage, ['visitor_provided_proof_after_request'], true)) {
            $checks[] = [
                'pattern' => '/\b(?:пришлите|отправьте|send|provide).{0,40}(?:screenshot|скрин|proof|receipt|квитан)\b/iu',
                'warning' => 'Draft asks for payment proof but visitor already provided it.',
            ];
        }

        if (in_array($stage, ['visitor_provided_wallet_after_request'], true)) {
            $checks[] = [
                'pattern' => '/\b(?:пришлите|укажите|send|provide).{0,40}(?:wallet|кошел|адрес|network|сеть)\b/iu',
                'warning' => 'Draft asks for wallet/network but visitor already provided it.',
            ];
        }

        if ($stage === 'operator_escalated_follow_up') {
            $checks[] = [
                'pattern' => '/\b(?:передадим|передать|pass to|forward to).{0,30}(?:оператор|admin|админ)\b/iu',
                'warning' => 'Draft says issue will be escalated but operator already escalated — reference existing escalation.',
            ];
        }

        foreach ($drafts as $draft) {
            foreach ($checks as $check) {
                if (preg_match($check['pattern'], $draft) === 1 && ! in_array($check['warning'], $warnings, true)) {
                    $warnings[] = $check['warning'];
                }
            }
        }

        return $warnings;
    }

    /**
     * @param  list<string>  $drafts
     * @param  array{action: string, snippet: string, message_id: int}  $operatorContext
     * @return list<string>
     */
    private function verifyRepeatedOperatorPromise(array $drafts, array $operatorContext, string $stage): array
    {
        if ($drafts === []) {
            return [];
        }

        $repeatStages = [
            'follow_up_with_order',
            'operator_promised_check_with_order',
            'operator_escalated_follow_up',
            'operator_gave_status_follow_up',
        ];

        if (! in_array($stage, $repeatStages, true)) {
            return [];
        }

        $lastAction = $operatorContext['action'];
        if (! in_array($lastAction, ['operator_promised_check', 'operator_said_manual_verification', 'operator_escalated_to_admin'], true)) {
            return [];
        }

        $emptyPromiseCount = 0;
        foreach ($drafts as $draft) {
            $lower = mb_strtolower($draft, 'UTF-8');
            if (preg_match('/\b(?:проверим|уточним|check|verify)\b/iu', $lower) === 1
                && ! preg_match('/\b(?:повторно|re-check|затянул|ожидан|escalat|эскала|конкретн|фактическ)\b/iu', $lower)) {
                $emptyPromiseCount++;
            }
        }

        if ($emptyPromiseCount >= 2) {
            return ['Multiple options repeat empty "we will check" — operator already promised; acknowledge follow-up and re-check substantively.'];
        }

        return [];
    }

    /**
     * @param  list<string>  $protected
     * @param  list<string>  $drafts
     * @return list<string>
     */
    private function verifyIdentifierPreservation(array $protected, array $drafts): array
    {
        if ($protected === [] || $drafts === []) {
            return [];
        }

        $warnings = [];
        foreach ($protected as $id) {
            $id = (string) $id;
            if (strlen($id) < 8) {
                continue;
            }

            $exactInAny = false;
            foreach ($drafts as $draft) {
                if (str_contains($draft, $id)) {
                    $exactInAny = true;
                    break;
                }
            }

            if ($exactInAny) {
                continue;
            }

            $truncatedDetected = false;
            for ($trim = 1; $trim <= 3 && strlen($id) - $trim >= 8; $trim++) {
                $partial = substr($id, 0, -$trim);
                foreach ($drafts as $draft) {
                    if (str_contains($draft, $partial) && ! str_contains($draft, $id)) {
                        $truncatedDetected = true;
                        $warnings[] = "Identifier {$id} may have been shortened (found {$partial} without full value).";
                        break 2;
                    }
                }
            }

            if (! $truncatedDetected) {
                $warnings[] = "Protected identifier {$id} missing from draft(s); include exact value before sending.";
            }
        }

        return $warnings;
    }

    private function latestVisitorMessage(SupportConversation $conversation): ?SupportMessage
    {
        return SupportMessage::query()
            ->where('support_conversation_id', $conversation->id)
            ->where('sender_type', SupportMessage::SENDER_VISITOR)
            ->orderByDesc('id')
            ->first();
    }

    private function resolveLanguage(SupportConversation $conversation, ?SupportMessage $triggerMessage): string
    {
        $triggerMessage ??= $this->latestVisitorMessage($conversation);
        $sample = trim((string) ($triggerMessage?->body ?? ''));

        if ($sample !== '') {
            if (preg_match('/[а-яё]/iu', $sample) === 1) {
                return preg_match('/[іїєґ]/iu', $sample) === 1 ? 'uk' : 'ru';
            }
            if (preg_match('/[ა-ჰ]/u', $sample) === 1) {
                return 'ka';
            }
            if (preg_match('/\b[a-z]{3,}\b/i', $sample) === 1) {
                return 'en';
            }
        }

        $locale = strtolower(trim((string) ($conversation->locale ?? '')));
        if ($locale !== '') {
            $base = explode('-', $locale)[0];
            if (in_array($base, ['ru', 'en', 'uk', 'ka'], true)) {
                return $base;
            }
        }

        return 'en';
    }

    /**
     * Lighter sanitization: preserve order IDs, amounts, tx hashes, currencies, networks.
     */
    private function sanitizeTextForAiContext(string $text): string
    {
        $text = preg_replace('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/', '[email-redacted]', $text) ?? $text;
        $text = preg_replace('/\b(?:seed phrase|mnemonic|private key|secret phrase|recovery phrase)\b[^\n]*/iu', '[secret-redacted]', $text) ?? $text;
        $text = preg_replace('/\bsk-[a-zA-Z0-9]{10,}\b/', '[api-key-redacted]', $text) ?? $text;
        $text = preg_replace('/\b\d{8,10}:[A-Za-z0-9_-]{30,}\b/', '[bot-token-redacted]', $text) ?? $text;

        return trim($text);
    }

    private function safeLogMessage(?string $message): string
    {
        $sanitized = SupportChatDiagnosticsLog::sanitizeError($message);

        return $sanitized ?? 'unknown_error';
    }

    private function pageUrlForContext(?string $pageUrl): string
    {
        if ($pageUrl === null || trim($pageUrl) === '') {
            return 'unknown';
        }

        $parts = parse_url(trim($pageUrl));
        if ($parts === false) {
            return trim($pageUrl);
        }

        $path = (string) ($parts['path'] ?? '/');

        return $this->truncateUtf8($path, 200);
    }

    /**
     * @return list<string>
     */
    private function extractProtectedIdentifiers(string $text): array
    {
        $ids = [];

        if (preg_match_all('/\b\d{10,}\b/u', $text, $m) > 0) {
            foreach ($m[0] as $hit) {
                $ids[] = $hit;
            }
        }

        if (preg_match_all('/\bS-\d{4,12}\b/i', $text, $m2) > 0) {
            foreach ($m2[0] as $hit) {
                $ids[] = strtoupper($hit);
            }
        }

        if (preg_match_all('#/order/(\d{4,20})#i', $text, $m3) > 0) {
            foreach ($m3[1] as $hit) {
                $ids[] = $hit;
            }
        }

        if (preg_match_all('/\b0x[0-9a-fA-F]{40,64}\b/', $text, $m4) > 0) {
            foreach ($m4[0] as $hit) {
                $ids[] = $hit;
            }
        }

        if (preg_match_all('/\b[0-9a-fA-F]{64}\b/', $text, $m5) > 0) {
            foreach ($m5[0] as $hit) {
                $ids[] = $hit;
            }
        }

        if (preg_match_all('/\b\d+(?:[.,]\d+)?\s*(?:USDT|USD|BTC|ETH|RUB|EUR|GEL|UAH|TRX|TON|LTC|XRP)\b/iu', $text, $m6) > 0) {
            foreach ($m6[0] as $hit) {
                $ids[] = trim($hit);
            }
        }

        if (preg_match_all('/\b(?:TRC20|ERC20|BEP20|TRON|Ethereum|Bitcoin|TON|SOL|Arbitrum|Polygon|BSC)\b/iu', $text, $m7) > 0) {
            foreach ($m7[0] as $hit) {
                $ids[] = $hit;
            }
        }

        $unique = [];
        foreach ($ids as $id) {
            $id = (string) $id;
            if ($id !== '' && ! in_array($id, $unique, true)) {
                $unique[] = $id;
            }
        }

        return $unique;
    }

    private function truncateUtf8(string $value, int $maxChars): string
    {
        if (mb_strlen($value, 'UTF-8') <= $maxChars) {
            return $value;
        }

        return mb_substr($value, 0, max(0, $maxChars - 1), 'UTF-8').'…';
    }
}
