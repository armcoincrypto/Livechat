<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAiKnowledgeRule;
use iEXPackages\SupportChat\Data\SupportAiPolicyIntentPatterns;
use Illuminate\Support\Collection;

/**
 * Deterministic business-knowledge retrieval for AI draft context (read-only, no embeddings).
 */
final class SupportAiKnowledgeService
{
    public function isEnabled(): bool
    {
        return filter_var(config('support_chat.ai.knowledge.enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    public function detectKnowledgeIntent(string $text, array $context = []): array
    {
        return SupportAiPolicyIntentPatterns::matchKnowledgeIntents($text, $context);
    }

    public function isPolicyOnlyQuestion(string $visitorMessage, array $context = []): bool
    {
        $intents = $this->detectKnowledgeIntent($visitorMessage, $context);
        $policyHit = array_intersect($intents, SupportAiPolicyIntentPatterns::policyIntents()) !== [];

        if (! $policyHit) {
            return false;
        }

        $hasOrderId = (bool) ($context['has_order_id'] ?? false);
        $hasTx = (bool) ($context['has_tx_hash'] ?? false);

        if ($hasOrderId || $hasTx) {
            return false;
        }

        if (preg_match('/\b\d{10,}\b/u', $visitorMessage) === 1) {
            return false;
        }

        if (preg_match('#/order/\d+#i', $visitorMessage) === 1) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<SupportAiKnowledgeRule>
     */
    public function findRelevantRules(string $visitorMessage, array $context = []): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $intents = $this->detectKnowledgeIntent($visitorMessage, $context);
        $language = isset($context['language']) ? (string) $context['language'] : null;
        $includeUnvalidated = filter_var(
            config('support_chat.ai.knowledge.include_unvalidated', false),
            FILTER_VALIDATE_BOOLEAN
        );

        /** @var Collection<int, SupportAiKnowledgeRule> $rules */
        $rules = SupportAiKnowledgeRule::query()
            ->active()
            ->forLanguage($language)
            ->get();

        if (! $includeUnvalidated) {
            $rules = $rules->filter(static function (SupportAiKnowledgeRule $rule): bool {
                if (! $rule->requires_validation) {
                    return true;
                }

                return (bool) ($rule->metadata['soft_guidance'] ?? false);
            })->values();
        }

        if ($rules->isEmpty()) {
            return [];
        }

        $normalized = mb_strtolower(trim($visitorMessage), 'UTF-8');
        $scored = [];

        foreach ($rules as $rule) {
            $score = 0;

            if ($rule->intent !== null && in_array($rule->intent, $intents, true)) {
                $score += 10;
            }

            foreach ($rule->question_patterns ?? [] as $pattern) {
                if ($pattern === '') {
                    continue;
                }
                $regex = $this->patternToRegex($pattern);
                if ($regex !== null && @preg_match($regex, $normalized) === 1) {
                    $score += 8;
                    break;
                }
            }

            if ($score === 0) {
                continue;
            }

            if ($rule->confidence === SupportAiKnowledgeRule::CONFIDENCE_HIGH) {
                $score += 3;
            } elseif ($rule->confidence === SupportAiKnowledgeRule::CONFIDENCE_MEDIUM) {
                $score += 1;
            }

            if ($rule->requires_validation) {
                $score -= 2;
            }

            $scored[] = ['rule' => $rule, 'score' => $score];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $maxRules = max(1, min(10, (int) config('support_chat.ai.knowledge.max_rules', 5)));
        $selected = array_slice($scored, 0, $maxRules);

        return array_map(static fn (array $row): SupportAiKnowledgeRule => $row['rule'], $selected);
    }

    /**
     * @param  list<SupportAiKnowledgeRule>  $rules
     */
    public function buildKnowledgeContext(array $rules, bool $policyOnlyQuestion = false): string
    {
        if ($rules === []) {
            return '';
        }

        $maxChars = max(500, min(5000, (int) config('support_chat.ai.knowledge.max_chars', 2500)));
        $lines = [
            'BUSINESS KNOWLEDGE CONTEXT (operator-derived — use only if relevant to visitor question):',
            '- Do not present conditional rules as guarantees.',
            '- If a rule is marked [SOFT GUIDANCE], phrase carefully and note operator will verify.',
            '- Safety rules override business knowledge.',
            '- Never invent rates, ETAs, or guarantee single payment unless rule explicitly allows soft wording.',
        ];

        if ($policyOnlyQuestion) {
            $lines[] = '- POLICY QUESTION detected: do NOT ask for order ID or TX hash unless visitor referenced a specific order.';
            $lines[] = '- Answer the business/policy question directly using safe phrasing below.';
        }

        $lines[] = '';

        foreach ($rules as $rule) {
            $prefix = $rule->requires_validation ? '[SOFT GUIDANCE] ' : '';
            $phrasing = trim((string) ($rule->safe_phrasing ?: $rule->answer_template ?: $rule->rule_text));
            $phrasing = $this->sanitizeKnowledgeText($phrasing);
            $lines[] = sprintf(
                '%s[%s] %s: %s',
                $prefix,
                $rule->rule_code,
                $this->sanitizeKnowledgeText((string) $rule->title),
                $phrasing
            );
        }

        $block = implode("\n", $lines);

        return mb_strlen($block, 'UTF-8') > $maxChars
            ? mb_substr($block, 0, $maxChars, 'UTF-8').'…'
            : $block;
    }

    public function sanitizeKnowledgeText(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($this->maskSensitiveValues($text));
    }

    public function maskSensitiveValues(string $text): string
    {
        $text = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/u', '[email-redacted]', $text) ?? $text;
        $text = preg_replace('/\b\d{12,19}\b/u', '[card-redacted]', $text) ?? $text;

        return $text;
    }

    public static function promptSafetyBlock(): string
    {
        return <<<'BLOCK'
BUSINESS KNOWLEDGE RULES (mandatory when Business knowledge context is present):
- Use business knowledge ONLY if relevant to the visitor's actual question.
- Do not present conditional/soft rules as guarantees.
- If rule requires validation ([SOFT GUIDANCE]), phrase carefully — operator will verify.
- Safety rules override business knowledge — never guarantee payment timing, single payment, or exact rates.
- For policy/pre-order questions WITHOUT order ID in thread: answer the policy question; do NOT ask for order ID.
BLOCK;
    }

    private function mapDraftIntentToKnowledge(string $draftIntent): ?string
    {
        return SupportAiPolicyIntentPatterns::mapDraftIntentToKnowledge($draftIntent);
    }

    private function patternToRegex(string $pattern): ?string
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return null;
        }

        if (@preg_match('/'.$pattern.'/iu', '') !== false) {
            return '/'.$pattern.'/iu';
        }

        return '/'.preg_quote($pattern, '/').'/iu';
    }
}
