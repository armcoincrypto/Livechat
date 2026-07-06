<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAiKnowledgeRule;
use App\Models\SupportAiTemplate;
use iEXPackages\SupportChat\Data\SupportAiPolicyIntentPatterns;

/**
 * Operator-facing suggestion UX: dynamic counts, deduplication, confidence, explainability.
 */
final class SupportAiSuggestionUxService
{
    public function isEnabled(): bool
    {
        return filter_var(config('support_chat.ai.ux.enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function isDynamicChoicesEnabled(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return filter_var(config('support_chat.ai.ux.dynamic_choices', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function isDedupEnabled(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return filter_var(config('support_chat.ai.ux.dedup_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function isPolicyProtectionEnabled(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return filter_var(config('support_chat.ai.ux.policy_protection', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array{
     *     intent: string,
     *     stage: string,
     *     language: string,
     *     policy_only: bool,
     *     has_order_id: bool,
     *     has_tx_hash: bool,
     *     knowledge_rules: list<SupportAiKnowledgeRule>,
     *     matched_templates: list<SupportAiTemplate>,
     * }  $context
     * @return array{
     *     intent: string,
     *     stage: string,
     *     language: string,
     *     policy_only: bool,
     *     has_order_id: bool,
     *     has_tx_hash: bool,
     *     knowledge_count: int,
     *     template_count: int,
     *     knowledge_codes: list<string>,
     *     template_codes: list<string>,
     *     operator_confidence: string,
     *     suggestion_count: int,
     *     policy_protected: bool,
     * }
     */
    public function buildContext(array $context): array
    {
        /** @var list<SupportAiKnowledgeRule> $knowledgeRules */
        $knowledgeRules = $context['knowledge_rules'] ?? [];
        /** @var list<SupportAiTemplate> $matchedTemplates */
        $matchedTemplates = $context['matched_templates'] ?? [];

        $knowledgeCodes = array_values(array_map(
            static fn (SupportAiKnowledgeRule $rule): string => (string) $rule->rule_code,
            $knowledgeRules,
        ));
        $templateCodes = array_values(array_map(
            static fn (SupportAiTemplate $template): string => (string) $template->template_code,
            $matchedTemplates,
        ));

        $policyProtected = ($context['policy_only'] ?? false)
            && count($knowledgeRules) >= 1
            && count($matchedTemplates) >= 1;

        $visitorBody = (string) ($context['visitor_body'] ?? '');
        $knowledgeIntents = is_array($context['knowledge_intents'] ?? null)
            ? $context['knowledge_intents']
            : [];

        $operatorConfidence = $this->computeOperatorConfidence(
            (string) ($context['intent'] ?? 'unknown_context'),
            (string) ($context['stage'] ?? 'first_message_general'),
            (bool) ($context['policy_only'] ?? false),
            (bool) ($context['has_order_id'] ?? false),
            (bool) ($context['has_tx_hash'] ?? false),
            count($knowledgeRules),
            count($matchedTemplates),
            $policyProtected,
            $visitorBody,
            $knowledgeIntents,
        );

        $suggestionCount = $this->resolveSuggestionCount($operatorConfidence, $policyProtected);

        return [
            'intent' => (string) ($context['intent'] ?? 'unknown_context'),
            'stage' => (string) ($context['stage'] ?? 'first_message_general'),
            'language' => (string) ($context['language'] ?? 'en'),
            'policy_only' => (bool) ($context['policy_only'] ?? false),
            'has_order_id' => (bool) ($context['has_order_id'] ?? false),
            'has_tx_hash' => (bool) ($context['has_tx_hash'] ?? false),
            'knowledge_count' => count($knowledgeRules),
            'template_count' => count($matchedTemplates),
            'knowledge_codes' => $knowledgeCodes,
            'template_codes' => $templateCodes,
            'operator_confidence' => $operatorConfidence,
            'suggestion_count' => $suggestionCount,
            'policy_protected' => $policyProtected,
        ];
    }

    public function resolveSuggestionCount(string $operatorConfidence, bool $policyProtected = false): int
    {
        if ($policyProtected && $this->isPolicyProtectionEnabled()) {
            return 1;
        }

        if (! $this->isDynamicChoicesEnabled()) {
            return max(1, min(4, (int) config('support_chat.ai.telegram_choices', 4)));
        }

        return match ($operatorConfidence) {
            'high' => 1,
            'medium' => 2,
            default => 4,
        };
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $uxContext
     * @return array<string, mixed>
     */
    public function finalizeResult(
        array $result,
        array $uxContext,
        int $requestedCount,
        bool $applyDynamicCount = true,
    ): array {
        if (! $this->isEnabled()) {
            return $result;
        }

        $targetCount = $applyDynamicCount
            ? (int) ($uxContext['suggestion_count'] ?? $requestedCount)
            : $requestedCount;
        $targetCount = max(1, min(4, $targetCount));

        $options = is_array($result['options'] ?? null) ? $result['options'] : [];

        if ($this->isPolicyProtectionEnabled() && ($uxContext['policy_protected'] ?? false)) {
            $options = $this->filterPolicyViolatingOptions($options);
        }

        if ($this->isDedupEnabled()) {
            $options = $this->deduplicateOptions($options);
        }

        $options = array_slice($options, 0, $targetCount);

        if ($options === [] && isset($result['draft']) && is_string($result['draft']) && trim($result['draft']) !== '') {
            $options = [[
                'label' => 'Short professional',
                'style' => 'short_professional',
                'text' => trim($result['draft']),
            ]];
        }

        $operatorConfidence = (string) ($uxContext['operator_confidence'] ?? 'medium');
        $modelConfidence = (string) ($result['confidence'] ?? 'medium');
        $mergedConfidence = $this->mergeConfidence($operatorConfidence, $modelConfidence);

        $result['options'] = $options;
        $result['draft'] = $options[0]['text'] ?? ($result['draft'] ?? null);
        $result['choices'] = count($options) > 0 ? count($options) : $targetCount;
        $result['confidence'] = $mergedConfidence;
        $result['operator_confidence'] = $operatorConfidence;
        $result['ux'] = [
            'intent' => (string) ($uxContext['intent'] ?? 'unknown_context'),
            'operator_confidence' => $operatorConfidence,
            'suggestion_count' => $targetCount,
            'policy_protected' => (bool) ($uxContext['policy_protected'] ?? false),
            'knowledge_matched' => $uxContext['knowledge_codes'] ?? [],
            'templates_matched' => $uxContext['template_codes'] ?? [],
        ];

        return $result;
    }

    public function formatConfidenceLabel(string $confidence): string
    {
        return match (strtolower(trim($confidence))) {
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
            default => 'Medium',
        };
    }

    public function isTelegramDebugEnabled(): bool
    {
        if (filter_var(config('support_chat.ai.telegram_actions.show_debug', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return filter_var(config('support_chat.ai.debug', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function isTelegramCollapseLongEnabled(): bool
    {
        return filter_var(config('support_chat.ai.telegram_actions.collapse_long', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function telegramCollapseChars(): int
    {
        return max(200, min(1200, (int) config('support_chat.ai.telegram_actions.collapse_chars', 420)));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function resolveTelegramCollapseState(array $result, array $options): array
    {
        $telegramUx = is_array($result['telegram_ux'] ?? null) ? $result['telegram_ux'] : [];
        if (($telegramUx['expanded'] ?? false) === true) {
            return ['collapsed' => false, 'expanded' => true];
        }

        if (! $this->isTelegramCollapseLongEnabled()) {
            return ['collapsed' => false, 'expanded' => false];
        }

        $confidence = strtolower((string) ($result['operator_confidence'] ?? $result['confidence'] ?? 'medium'));
        if ($confidence !== 'high' || count($options) !== 1) {
            return ['collapsed' => false, 'expanded' => false];
        }

        $fullText = trim((string) ($options[0]['text'] ?? ''));
        $collapsed = mb_strlen($fullText, 'UTF-8') > $this->telegramCollapseChars();

        return ['collapsed' => $collapsed, 'expanded' => false];
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<array{label: string, style: string, text: string}>  $options
     */
    public function formatAiAssistantTelegramMessage(
        array $result,
        array $options,
        int $optionCount,
        string $cliNote = '',
        ?callable $prepareOptionText = null,
    ): string {
        $confidence = strtolower((string) ($result['operator_confidence'] ?? $result['confidence'] ?? 'medium'));
        $slice = array_slice($options, 0, max(1, $optionCount));
        $prepare = $prepareOptionText ?? fn (string $text): string => $this->prepareTelegramCodeText(
            $text,
            $confidence,
            count($slice),
        );

        $body = $this->resolveTelegramOptionsBody($slice, $confidence, $prepare);
        $header = "🤖 <b>AI assistant</b>\n\n";
        $debug = $this->formatDebugMetadataBlock($result);
        $noteEsc = $cliNote !== ''
            ? "\n\n".'<i>'.htmlspecialchars($cliNote, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</i>'
            : '';

        return $header.$body.$debug.$noteEsc;
    }

    /**
     * @param  array<string, mixed>  $ux
     */
    public function formatConfidenceFooter(string $confidence, array $ux = []): string
    {
        $level = strtolower(trim($confidence));
        $isPolicy = (bool) ($ux['policy_protected'] ?? false)
            || in_array((string) ($ux['intent'] ?? ''), [
                'single_payment', 'payout_split', 'card_rub', 'rate_question',
                'verified_accounts', 'large_amount', 'otc',
            ], true);

        return match ($level) {
            'high' => $isPolicy
                ? '🟢 high confidence · policy answer'
                : '🟢 high confidence',
            'medium' => '🟡 medium confidence',
            default => '🔴 low confidence · review carefully',
        };
    }

    public function formatSingleSuggestionHtml(string $text): string
    {
        return '<code>'.$this->escapeTelegramCode($text).'</code>';
    }

    /**
     * @param  list<array{label: string, style: string, text: string}>  $options
     */
    public function formatMediumSuggestionsHtml(array $options, callable $prepareOptionText): string
    {
        $parts = [];
        if (isset($options[0])) {
            $parts[] = "Main:\n".'<code>'.$this->escapeTelegramCode($prepareOptionText((string) $options[0]['text'])).'</code>';
        }
        if (isset($options[1])) {
            $parts[] = "Alternative:\n".'<code>'.$this->escapeTelegramCode($prepareOptionText((string) $options[1]['text'])).'</code>';
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param  list<array{label: string, style: string, text: string}>  $options
     */
    public function formatLowSuggestionsHtml(array $options, callable $prepareOptionText): string
    {
        $labels = ['1️⃣', '2️⃣', '3️⃣', '4️⃣'];
        $parts = [];
        foreach (array_slice($options, 0, 4) as $index => $option) {
            $label = $labels[$index] ?? (($index + 1).'️⃣');
            $parts[] = $label.' <code>'.$this->escapeTelegramCode($prepareOptionText((string) $option['text'])).'</code>';
        }

        return implode("\n\n", $parts);
    }

    public function prepareTelegramCodeText(
        string $text,
        string $confidence,
        int $optionCount,
        bool $collapsePreview = false,
    ): string {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($optionCount === 1 && strtolower($confidence) === 'high') {
            if ($collapsePreview && $this->isTelegramCollapseLongEnabled()) {
                return $this->truncateTelegramPreview($text, $this->telegramCollapseChars());
            }

            return $text;
        }

        $text = preg_replace('/^\s*[-•*]\s+/m', '', $text) ?? $text;
        $text = preg_replace('/\s*\n+\s*/', ' ', $text) ?? $text;
        $text = preg_replace('/\s{2,}/u', ' ', $text) ?? $text;

        if (preg_match_all('/[^.!?…]+[.!?…]+/u', $text, $matches) >= 1 && ! empty($matches[0])) {
            $sentences = array_slice($matches[0], 0, 2);
            $text = trim(implode(' ', array_map(static fn (string $s): string => trim($s), $sentences)));
        }

        $max = 280;
        if (mb_strlen($text, 'UTF-8') > $max) {
            $text = mb_substr($text, 0, max(0, $max - 1), 'UTF-8').'…';
        }

        return trim($text);
    }

    private function truncateTelegramPreview(string $text, int $maxChars): string
    {
        if (mb_strlen($text, 'UTF-8') <= $maxChars) {
            return $text;
        }

        $cut = mb_substr($text, 0, max(0, $maxChars - 1), 'UTF-8');
        if (! str_ends_with($cut, '…')) {
            $cut .= '…';
        }

        return $cut;
    }

    public function escapeTelegramCode(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param  list<array{label: string, style: string, text: string}>  $options
     */
    private function resolveTelegramOptionsBody(
        array $options,
        string $confidence,
        callable $prepareOptionText,
    ): string {
        $count = count($options);
        if ($count === 0) {
            return '';
        }

        if ($count === 1 && $confidence === 'high') {
            $prepare = fn (string $text): string => $prepareOptionText($text);

            return $this->formatSingleSuggestionHtml($prepare((string) $options[0]['text']));
        }

        if ($count === 2 && in_array($confidence, ['high', 'medium'], true)) {
            return $this->formatMediumSuggestionsHtml($options, $prepareOptionText);
        }

        return $this->formatLowSuggestionsHtml($options, $prepareOptionText);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatDebugMetadataBlock(array $result): string
    {
        if (! $this->isTelegramDebugEnabled()) {
            return '';
        }

        $ux = is_array($result['ux'] ?? null) ? $result['ux'] : [];
        $lines = [];
        if (($ux['intent'] ?? '') !== '') {
            $lines[] = 'Intent: '.htmlspecialchars((string) $ux['intent'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $knowledge = is_array($ux['knowledge_matched'] ?? null) ? $ux['knowledge_matched'] : [];
        if ($knowledge !== []) {
            $lines[] = 'Knowledge: '.htmlspecialchars(implode(', ', $knowledge), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $templates = is_array($ux['templates_matched'] ?? null) ? $ux['templates_matched'] : [];
        if ($templates !== []) {
            $lines[] = 'Template: '.htmlspecialchars(implode(', ', $templates), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if ($lines === []) {
            return '';
        }

        return "\n\n".'<i>'.implode("\n", $lines).'</i>';
    }

    private function computeOperatorConfidence(
        string $intent,
        string $stage,
        bool $policyOnly,
        bool $hasOrderId,
        bool $hasTxHash,
        int $knowledgeCount,
        int $templateCount,
        bool $policyProtected,
        string $visitorBody = '',
        array $knowledgeIntents = [],
    ): string {
        if ($policyProtected) {
            return 'high';
        }

        $score = 0;

        if ($intent !== 'unknown_context' && $intent !== 'general_question' && $intent !== 'general_support') {
            $score += 2;
        }

        $score += min(3, $knowledgeCount) * 2;
        $score += min(2, $templateCount) * 2;

        if ($policyOnly) {
            $score += 2;
        }

        if ($hasOrderId || $hasTxHash) {
            $score += 1;
        }

        if ($stage !== 'first_message_general' && $stage !== 'unknown') {
            $score += 1;
        }

        if ($knowledgeCount >= 1 && $templateCount >= 1) {
            $score += 2;
        }

        if ($visitorBody !== '') {
            $intents = $knowledgeIntents !== []
                ? $knowledgeIntents
                : SupportAiPolicyIntentPatterns::matchKnowledgeIntents($visitorBody, ['draft_intent' => $intent]);
            $policyStrength = SupportAiPolicyIntentPatterns::classifyPolicyStrength($visitorBody, $intents);
            if ($policyStrength === 'high') {
                $score += 3;
            } elseif ($policyStrength === 'medium') {
                $score += 1;
            }
        }

        if ($score >= 8) {
            return 'high';
        }

        if ($score >= 4) {
            return 'medium';
        }

        return 'low';
    }

    private function mergeConfidence(string $operatorConfidence, string $modelConfidence): string
    {
        $rank = ['low' => 0, 'medium' => 1, 'high' => 2];
        $op = $rank[$operatorConfidence] ?? 1;
        $model = $rank[$modelConfidence] ?? 1;

        $merged = min($op, $model);

        return array_search($merged, $rank, true) ?: 'medium';
    }

    /**
     * @param  list<array{label: string, style: string, text: string}>  $options
     * @return list<array{label: string, style: string, text: string}>
     */
    public function deduplicateOptions(array $options): array
    {
        if ($options === []) {
            return [];
        }

        $unique = [];
        $signatures = [];

        foreach ($options as $option) {
            $text = trim((string) ($option['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $signature = $this->semanticSignature($text);
            $duplicate = false;

            foreach ($signatures as $existing) {
                if ($this->areSemanticallyDuplicate($signature, $existing)) {
                    $duplicate = true;
                    break;
                }
            }

            if ($duplicate) {
                continue;
            }

            $signatures[] = $signature;
            $unique[] = $option;
        }

        return $unique;
    }

    /**
     * @param  list<array{label: string, style: string, text: string}>  $options
     * @return list<array{label: string, style: string, text: string}>
     */
    private function filterPolicyViolatingOptions(array $options): array
    {
        $filtered = array_values(array_filter(
            $options,
            fn (array $option): bool => ! $this->isPrimaryOrderIdRequest((string) ($option['text'] ?? '')),
        ));

        return $filtered !== [] ? $filtered : $options;
    }

    public function isPrimaryOrderIdRequest(string $text): bool
    {
        $normalized = mb_strtolower(trim($text), 'UTF-8');
        if ($normalized === '') {
            return false;
        }

        $asksOrderId = preg_match(
            '/(номер\s+заявк|номер\s+заказ|order\s*id|order\/|пришлите.*заяв|отправьте.*заяв|укажите.*заяв|нам\s+нужен.*заяв|для\s+проверки.*заяв|пришлите\s+ссылк)/iu',
            $normalized,
        ) === 1;

        if (! $asksOrderId) {
            return false;
        }

        $hasPolicyAnswer = preg_match(
            '/(платеж|выплат|курс|направлен|банк|проверен|реквизит|1.?3\s+плат|одним\s+плат|card\s*rub|cartrub|объ[её]м)/iu',
            $normalized,
        ) === 1;

        return ! $hasPolicyAnswer;
    }

    private function semanticSignature(string $text): string
    {
        if ($this->isPrimaryOrderIdRequest($text)) {
            return '@order_id_request@';
        }

        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/\d{6,}/u', '#', $text) ?? $text;
        $text = preg_replace(
            '/(номер\s+заявк\w*|order\s*id|номер\s+заказ\w*|пришлите\s+заяв\w*|отправьте\s+заяв\w*|укажите\s+заяв\w*)/iu',
            '@order_id@',
            $text,
        ) ?? $text;
        $text = preg_replace('/[^\p{L}\p{N}\s@#]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function areSemanticallyDuplicate(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        if (str_contains($a, $b) || str_contains($b, $a)) {
            $shorter = min(mb_strlen($a, 'UTF-8'), mb_strlen($b, 'UTF-8'));
            if ($shorter >= 20) {
                return true;
            }
        }

        similar_text($a, $b, $percent);

        return $percent >= 78.0;
    }
}
