<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use iEXPackages\SupportChat\Services\SupportAiKnowledgeService;
use Illuminate\Console\Command;

final class SupportChatKnowledgeSearchCommand extends Command
{
    protected $signature = 'support-chat:knowledge-search
                            {query : Visitor message to match against knowledge rules}
                            {--json : Output JSON}
                            {--language= : Language hint (ru, en)}';

    protected $description = 'Search operator-derived support knowledge rules for a visitor message.';

    public function handle(SupportAiKnowledgeService $knowledge): int
    {
        $query = (string) $this->argument('query');
        $language = $this->option('language');
        $language = is_string($language) && $language !== '' ? $language : null;

        $intents = $knowledge->detectKnowledgeIntent($query);
        $rules = $knowledge->findRelevantRules($query, [
            'language' => $language,
        ]);
        $policyOnly = $knowledge->isPolicyOnlyQuestion($query, [
            'has_order_id' => false,
            'has_tx_hash' => false,
        ]);

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'query' => $query,
                'detected_intents' => $intents,
                'policy_only_question' => $policyOnly,
                'matched_rules' => array_map(static fn ($rule): array => [
                    'rule_code' => $rule->rule_code,
                    'title' => $rule->title,
                    'intent' => $rule->intent,
                    'confidence' => $rule->confidence,
                    'requires_validation' => $rule->requires_validation,
                    'safe_phrasing' => $rule->safe_phrasing,
                ], $rules),
                'context_preview' => $knowledge->buildKnowledgeContext($rules, $policyOnly),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->line('Query: '.$query);
        $this->line('Detected intents: '.($intents === [] ? '—' : implode(', ', $intents)));
        $this->line('Policy-only question: '.($policyOnly ? 'yes' : 'no'));
        $this->line('');
        $this->line('Matched rules:');

        if ($rules === []) {
            $this->line('  (none)');

            return self::SUCCESS;
        }

        foreach ($rules as $rule) {
            $flag = $rule->requires_validation ? ' [soft]' : '';
            $this->line(sprintf('  - %s%s — %s', $rule->rule_code, $flag, $rule->title));
        }

        $context = $knowledge->buildKnowledgeContext($rules, $policyOnly);
        if ($context !== '') {
            $this->line('');
            $this->line('Context preview:');
            $this->line($context);
        }

        return self::SUCCESS;
    }
}
