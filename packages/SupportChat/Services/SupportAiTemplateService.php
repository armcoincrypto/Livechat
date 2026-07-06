<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAiTemplate;
use Illuminate\Support\Collection;

/**
 * Deterministic operator-template retrieval for AI draft context (read-only, no embeddings).
 */
final class SupportAiTemplateService
{
    public function __construct(
        private readonly SupportAiKnowledgeService $knowledge,
    ) {}

    public function isEnabled(): bool
    {
        return filter_var(config('support_chat.ai.templates.enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<SupportAiTemplate>
     */
    public function findRelevantTemplates(string $visitorMessage, array $context = []): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $language = isset($context['language']) ? (string) $context['language'] : null;
        $includeUnvalidated = filter_var(
            config('support_chat.ai.templates.include_unvalidated', false),
            FILTER_VALIDATE_BOOLEAN
        );

        /** @var Collection<int, SupportAiTemplate> $templates */
        $templates = SupportAiTemplate::query()
            ->active()
            ->forLanguage($language)
            ->get();

        if (! $includeUnvalidated) {
            $templates = $templates->filter(static fn (SupportAiTemplate $t): bool => ! $t->requires_validation)->values();
        }

        if ($templates->isEmpty()) {
            return [];
        }

        return $this->matchTemplates($visitorMessage, $templates->all(), $context);
    }

    /**
     * @param  list<SupportAiTemplate>  $templates
     * @param  array<string, mixed>  $context
     * @return list<SupportAiTemplate>
     */
    public function matchTemplates(string $visitorMessage, array $templates, array $context = []): array
    {
        $intents = $this->knowledge->detectKnowledgeIntent($visitorMessage, $context);
        $normalized = mb_strtolower(trim($visitorMessage), 'UTF-8');
        $policyOnly = $this->knowledge->isPolicyOnlyQuestion($visitorMessage, $context);
        $scored = [];

        foreach ($templates as $template) {
            if ($policyOnly && in_array($template->intent, ['status_question', 'refund'], true)) {
                continue;
            }

            $score = 0;

            if (in_array($template->intent, $intents, true)) {
                $score += 10;
            }

            $patterns = is_array($template->metadata['question_patterns'] ?? null)
                ? $template->metadata['question_patterns']
                : [];

            foreach ($patterns as $pattern) {
                if (! is_string($pattern) || $pattern === '') {
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

            $score += min(10, max(0, (int) $template->frequency));

            if ($template->confidence === 'high') {
                $score += 3;
            } elseif ($template->confidence === 'medium') {
                $score += 1;
            }

            if ($template->requires_validation) {
                $score -= 2;
            }

            $scored[] = ['template' => $template, 'score' => $score];
        }

        usort($scored, static function (array $a, array $b): int {
            $cmp = $b['score'] <=> $a['score'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return ($b['template']->frequency ?? 0) <=> ($a['template']->frequency ?? 0);
        });

        $maxTemplates = max(1, min(10, (int) config('support_chat.ai.templates.max', 3)));
        $selected = array_slice($scored, 0, $maxTemplates);

        return array_map(static fn (array $row): SupportAiTemplate => $row['template'], $selected);
    }

    /**
     * @param  list<SupportAiTemplate>  $templates
     */
    public function buildTemplateContext(array $templates, bool $policyOnlyQuestion = false): string
    {
        if ($templates === []) {
            return '';
        }

        $maxChars = max(300, min(5000, (int) config('support_chat.ai.templates.max_chars', 1500)));
        $lines = [
            'Operator templates:',
            '',
            'These are real operator response patterns frequently used in production.',
            'Use them as guidance.',
            'Do not copy blindly.',
            'Adapt naturally.',
            'Safety rules override templates.',
        ];

        if ($policyOnlyQuestion) {
            $lines[] = 'POLICY QUESTION: prefer policy wording below; do not default to asking for order ID.';
        }

        $lines[] = '';

        foreach ($templates as $template) {
            $text = $this->sanitizeTemplates((string) $template->template_text);
            $lines[] = sprintf(
                '[%s] %s (%s): %s',
                $template->template_code,
                $this->sanitizeTemplates((string) $template->title),
                $template->template_type,
                $text
            );
        }

        $block = implode("\n", $lines);

        return mb_strlen($block, 'UTF-8') > $maxChars
            ? mb_substr($block, 0, $maxChars, 'UTF-8').'…'
            : $block;
    }

    public function sanitizeTemplates(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($this->knowledge->maskSensitiveValues($text));
    }

    public static function promptSafetyBlock(): string
    {
        return <<<'BLOCK'
OPERATOR TEMPLATE RULES (mandatory when Operator templates context is present):
- Templates show how operators phrase answers — adapt naturally; do not paste verbatim unless appropriate.
- Do not copy unsafe guarantees from templates — safety and knowledge rules override templates.
- For policy/pre-order questions: use template-style policy answers, not generic order-ID requests.
BLOCK;
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
