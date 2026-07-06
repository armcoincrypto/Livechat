<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use iEXPackages\SupportChat\Data\SupportAiPolicyIntentPatterns;
use iEXPackages\SupportChat\Services\SupportAiKnowledgeService;
use iEXPackages\SupportChat\Services\SupportAiSuggestionUxService;
use iEXPackages\SupportChat\Services\SupportAiTemplateService;
use Illuminate\Console\Command;

final class SupportChatIntentDiagnosticsCommand extends Command
{
    protected $signature = 'support-chat:intent-diagnostics
                            {--json : Output JSON}';

    protected $description = 'Multilingual policy-intent detection self-test (no OpenAI, no Telegram).';

    /** @var list<array{name: string, query: string, expect_intents: list<string>, expect_confidence: string}> */
    private const CASES = [
        [
            'name' => 'single_payment_en_rub',
            'query' => 'Hello can you send rub in one transaction?',
            'expect_intents' => ['single_payment'],
            'expect_confidence' => 'high',
        ],
        [
            'name' => 'single_payment_en_short',
            'query' => 'can you send rub in one payment?',
            'expect_intents' => ['single_payment'],
            'expect_confidence' => 'high',
        ],
        [
            'name' => 'single_payment_en_transfer',
            'query' => 'will it be one transfer?',
            'expect_intents' => ['single_payment'],
            'expect_confidence' => 'medium',
        ],
        [
            'name' => 'single_payment_ru',
            'query' => 'Оплата в 1 платеж будет?',
            'expect_intents' => ['single_payment'],
            'expect_confidence' => 'medium',
        ],
        [
            'name' => 'single_payment_ru_transfer',
            'query' => 'Можно одним переводом?',
            'expect_intents' => ['single_payment'],
            'expect_confidence' => 'medium',
        ],
        [
            'name' => 'rate_question_ru',
            'query' => 'а по нормальному курсу перевод не сможете сделать?',
            'expect_intents' => ['rate_question'],
            'expect_confidence' => 'medium',
        ],
        [
            'name' => 'verified_accounts_ru',
            'query' => 'Вы переводите со своих проверенных счетов?',
            'expect_intents' => ['verified_accounts', 'bank_transfer'],
            'expect_confidence' => 'medium',
        ],
        [
            'name' => 'otc_limit_ru',
            'query' => 'Какой лимит на обмен?',
            'expect_intents' => ['large_amount', 'otc'],
            'expect_confidence' => 'medium',
        ],
    ];

    public function handle(
        SupportAiKnowledgeService $knowledge,
        SupportAiTemplateService $templates,
        SupportAiSuggestionUxService $ux,
    ): int {
        $results = [];
        $failed = 0;

        foreach (self::CASES as $case) {
            $query = $case['query'];
            $context = ['has_order_id' => false, 'has_tx_hash' => false];

            $knowledgeIntents = $knowledge->detectKnowledgeIntent($query, $context);
            $draftIntent = SupportAiPolicyIntentPatterns::resolvePrimaryDraftIntent($query) ?? 'unknown_context';
            $policyOnly = $knowledge->isPolicyOnlyQuestion($query, $context);
            $knowledgeRules = $knowledge->findRelevantRules($query, array_merge($context, ['draft_intent' => $draftIntent]));
            $matchedTemplates = $templates->findRelevantTemplates($query, array_merge($context, ['draft_intent' => $draftIntent]));
            $policyStrength = SupportAiPolicyIntentPatterns::classifyPolicyStrength($query, $knowledgeIntents);

            $uxContext = $ux->buildContext([
                'intent' => $draftIntent,
                'stage' => 'first_message_general',
                'language' => 'en',
                'policy_only' => $policyOnly,
                'has_order_id' => false,
                'has_tx_hash' => false,
                'knowledge_rules' => $knowledgeRules,
                'matched_templates' => $matchedTemplates,
                'visitor_body' => $query,
                'knowledge_intents' => $knowledgeIntents,
            ]);

            $intentOk = array_intersect($case['expect_intents'], $knowledgeIntents) !== [];
            $confidenceOk = ($uxContext['operator_confidence'] ?? 'low') === $case['expect_confidence']
                || ($case['expect_confidence'] === 'high' && ($uxContext['operator_confidence'] ?? '') === 'high')
                || ($case['expect_confidence'] === 'medium' && in_array($uxContext['operator_confidence'] ?? '', ['medium', 'high'], true));

            $pass = $intentOk && $confidenceOk;
            if (! $pass) {
                $failed++;
            }

            $results[] = [
                'name' => $case['name'],
                'query' => $query,
                'pass' => $pass,
                'draft_intent' => $draftIntent,
                'knowledge_intents' => $knowledgeIntents,
                'policy_only' => $policyOnly,
                'policy_strength' => $policyStrength,
                'operator_confidence' => $uxContext['operator_confidence'] ?? null,
                'suggestion_count' => $uxContext['suggestion_count'] ?? null,
                'knowledge_codes' => $uxContext['knowledge_codes'] ?? [],
                'template_codes' => $uxContext['template_codes'] ?? [],
                'expected_intents' => $case['expect_intents'],
                'expected_confidence' => $case['expect_confidence'],
            ];
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'passed' => count(self::CASES) - $failed,
                'failed' => $failed,
                'cases' => $results,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $failed === 0 ? self::SUCCESS : self::FAILURE;
        }

        $this->line('Support AI Intent Diagnostics');
        $this->line('==============================');
        foreach ($results as $row) {
            $status = $row['pass'] ? 'PASS' : 'FAIL';
            $this->line(sprintf(
                '%s %s — draft=%s confidence=%s intents=[%s] kb=[%s] tpl=[%s]',
                $status,
                $row['name'],
                $row['draft_intent'],
                $row['operator_confidence'],
                implode(', ', $row['knowledge_intents']),
                implode(', ', $row['knowledge_codes']),
                implode(', ', $row['template_codes']),
            ));
        }
        $this->line('');
        $this->line('Summary: '.(count(self::CASES) - $failed).'/'.count(self::CASES).' passed');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
