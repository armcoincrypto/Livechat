<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use iEXPackages\SupportChat\Services\SupportAiKnowledgeService;
use iEXPackages\SupportChat\Services\SupportAiTemplateService;
use Illuminate\Console\Command;

final class SupportChatTemplateSearchCommand extends Command
{
    protected $signature = 'support-chat:template-search
                            {query : Visitor message to match against operator templates}
                            {--json : Output JSON}
                            {--language= : Language hint (ru, en)}';

    protected $description = 'Search operator reply templates for a visitor message.';

    public function handle(SupportAiTemplateService $templates, SupportAiKnowledgeService $knowledge): int
    {
        $query = (string) $this->argument('query');
        $language = $this->option('language');
        $language = is_string($language) && $language !== '' ? $language : null;

        $context = [
            'language' => $language,
            'has_order_id' => false,
            'has_tx_hash' => false,
        ];

        $intents = $knowledge->detectKnowledgeIntent($query, $context);
        $matched = $templates->findRelevantTemplates($query, $context);
        $policyOnly = $knowledge->isPolicyOnlyQuestion($query, $context);

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'query' => $query,
                'detected_intents' => $intents,
                'policy_only_question' => $policyOnly,
                'matched_templates' => array_map(static fn ($tpl): array => [
                    'template_code' => $tpl->template_code,
                    'title' => $tpl->title,
                    'intent' => $tpl->intent,
                    'template_type' => $tpl->template_type,
                    'frequency' => $tpl->frequency,
                    'confidence' => $tpl->confidence,
                    'requires_validation' => $tpl->requires_validation,
                    'template_text' => $tpl->template_text,
                ], $matched),
                'context_preview' => $templates->buildTemplateContext($matched, $policyOnly),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->line('Query: '.$query);
        $this->line('Detected intents: '.($intents === [] ? '—' : implode(', ', $intents)));
        $this->line('Policy-only question: '.($policyOnly ? 'yes' : 'no'));
        $this->line('');
        $this->line('Matched templates:');

        if ($matched === []) {
            $this->line('  (none)');

            return self::SUCCESS;
        }

        foreach ($matched as $tpl) {
            $flag = $tpl->requires_validation ? ' [needs review]' : '';
            $this->line(sprintf(
                '  - %s%s — %s (freq=%d, type=%s)',
                $tpl->template_code,
                $flag,
                $tpl->title,
                $tpl->frequency,
                $tpl->template_type
            ));
        }

        $contextPreview = $templates->buildTemplateContext($matched, $policyOnly);
        if ($contextPreview !== '') {
            $this->line('');
            $this->line('Context preview:');
            $this->line($contextPreview);
        }

        return self::SUCCESS;
    }
}
