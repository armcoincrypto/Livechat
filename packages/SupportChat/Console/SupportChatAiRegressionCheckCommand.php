<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use iEXPackages\SupportChat\Services\SupportAiDraftService;
use Illuminate\Console\Command;
use ReflectionClass;

final class SupportChatAiRegressionCheckCommand extends Command
{
    protected $signature = 'support-chat:ai-regression-check
                            {--json : Output machine-readable JSON}
                            {--fail-on-warning : Exit non-zero when any check is WARN}';

    protected $description = 'Run static regression guards for Support Chat AI prompt/playbook quality (no OpenAI call).';

    private string $draftServicePath;

    private string $playbookPath;

    private string $draftServiceSource = '';

    private string $playbookSource = '';

    private string $uxServiceSource = '';

    public function handle(SupportAiDraftService $aiDraft): int
    {
        $this->draftServicePath = base_path('packages/SupportChat/Services/SupportAiDraftService.php');
        $this->playbookPath = base_path('packages/SupportChat/Services/SupportAiReplyPlaybook.php');
        $uxServicePath = base_path('packages/SupportChat/Services/SupportAiSuggestionUxService.php');

        if (! is_readable($this->draftServicePath) || ! is_readable($this->playbookPath) || ! is_readable($uxServicePath)) {
            $this->error('Support AI service files are not readable.');

            return self::FAILURE;
        }

        $this->draftServiceSource = (string) file_get_contents($this->draftServicePath);
        $this->playbookSource = (string) file_get_contents($this->playbookPath);
        $this->uxServiceSource = (string) file_get_contents($uxServicePath);

        $checks = [
            $this->checkTelegramCodeBlocks(),
            $this->checkNoFooterRule(),
            $this->checkGreetingGating(),
            $this->checkKnownDataExtraction(),
            $this->checkPregMatchAllMultiMatchSafety(),
            $this->checkFollowUpDetection(),
            $this->checkOperatorActionAwareness(),
            $this->checkEdgeCasePlaybookRules(),
            $this->checkSafetyForbiddenClaimGuardrails(),
            $this->checkSampleTelegramFormat($aiDraft),
            $this->checkKnownDataDuplicateOrderIdRuntime($aiDraft),
        ];

        $overall = $this->resolveOverallStatus($checks);
        $asJson = (bool) $this->option('json');
        $failOnWarning = (bool) $this->option('fail-on-warning');

        if ($asJson) {
            $this->line(json_encode([
                'status' => $overall,
                'checks' => array_map(static fn (array $c): array => [
                    'name' => $c['name'],
                    'status' => $c['status'],
                    'message' => $c['message'] ?? '',
                ], $checks),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line('AI Support Regression Check');
            $this->line('');
            foreach ($checks as $check) {
                $this->line($this->formatHumanLine($check));
            }
            $this->line('');
            $this->line('Result: '.$overall);
        }

        if ($overall === 'FAIL') {
            return self::FAILURE;
        }

        if ($failOnWarning && $overall === 'PASS_WITH_WARNINGS') {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function checkTelegramCodeBlocks(): array
    {
        $ok = str_contains($this->draftServiceSource, 'formatTelegramSeparateMessage')
            && str_contains($this->draftServiceSource, 'formatAiAssistantTelegramMessage')
            && str_contains($this->uxServiceSource, '<code>')
            && str_contains($this->uxServiceSource, 'formatSingleSuggestionHtml');

        return $this->result(
            'telegram_code_blocks',
            'Telegram code blocks rule',
            $ok,
            $ok ? '' : 'Missing Telegram <code> compact rendering hooks.',
        );
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function checkNoFooterRule(): array
    {
        $compactOk = str_contains($this->draftServiceSource, 'formatAiAssistantTelegramMessage')
            && str_contains($this->uxServiceSource, 'AI assistant');

        $legacyFooterOnlyInLegacyPreview = str_contains($this->draftServiceSource, 'Reply manually in this topic')
            && str_contains($this->draftServiceSource, 'buildTelegramPreview');

        $compactHasFooter = preg_match(
            '/function formatAiAssistantTelegramMessage\([^)]*\)[^{]*\{[^}]*Reply manually/iu',
            $this->uxServiceSource
        ) === 1;

        $ok = $compactOk && $legacyFooterOnlyInLegacyPreview && ! $compactHasFooter;

        return $this->result(
            'no_footer',
            'No-footer rule',
            $ok,
            $ok ? '' : 'Compact Telegram separate message must not include operator footer.',
        );
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function checkGreetingGating(): array
    {
        $ok = str_contains($this->draftServiceSource, 'shouldUseGreeting')
            && str_contains($this->draftServiceSource, 'verifyExcessiveGreetings')
            && str_contains($this->playbookSource, 'greetingRules')
            && str_contains($this->playbookSource, 'Do NOT greet in every option');

        return $this->result(
            'greeting_gating',
            'Greeting gating rule',
            $ok,
            $ok ? '' : 'Missing greeting gating or excessive-greeting guard.',
        );
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function checkKnownDataExtraction(): array
    {
        $ok = str_contains($this->draftServiceSource, 'extractKnownConversationData')
            && str_contains($this->draftServiceSource, 'summarizeProvidedData')
            && str_contains($this->draftServiceSource, 'Do NOT ask again for');

        return $this->result(
            'known_data_extraction',
            'Known-data extraction',
            $ok,
            $ok ? '' : 'Missing known-data extraction or do-not-ask guard.',
        );
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function checkPregMatchAllMultiMatchSafety(): array
    {
        $badEqualsOne = preg_match('/preg_match_all\s*\([^)]+\)\s*===\s*1/u', $this->draftServiceSource) === 1;
        $hasGreaterThanZero = str_contains($this->draftServiceSource, 'preg_match_all')
            && preg_match('/preg_match_all\s*\([^)]+\)\s*>\s*0/u', $this->draftServiceSource) === 1;

        $ok = ! $badEqualsOne && $hasGreaterThanZero;

        return $this->result(
            'preg_match_all_multi_match_safety',
            'preg_match_all multi-match safety',
            $ok,
            $badEqualsOne
                ? 'Found preg_match_all(...) === 1 — breaks duplicate identifier extraction.'
                : ($hasGreaterThanZero ? '' : 'Expected preg_match_all(...) > 0 pattern not found.'),
        );
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function checkFollowUpDetection(): array
    {
        $ok = str_contains($this->draftServiceSource, 'detectRepeatedFollowUp')
            && str_contains($this->draftServiceSource, 'repeated_follow_up')
            && str_contains($this->playbookSource, 'follow_up');

        return $this->result(
            'follow_up_detection',
            'Follow-up detection',
            $ok,
            $ok ? '' : 'Missing follow-up detection logic or playbook guidance.',
        );
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function checkOperatorActionAwareness(): array
    {
        $ok = str_contains($this->draftServiceSource, 'detectLastOperatorAction')
            && str_contains($this->draftServiceSource, 'buildOperatorActionAwarenessBlock')
            && str_contains($this->draftServiceSource, 'detectVisitorProvidedDataAfterRequest')
            && str_contains($this->playbookSource, 'operatorActionAwarenessRules');

        return $this->result(
            'operator_action_awareness',
            'Operator action awareness',
            $ok,
            $ok ? '' : 'Missing operator action awareness hooks.',
        );
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function checkEdgeCasePlaybookRules(): array
    {
        $ok = str_contains($this->playbookSource, 'edgeCaseRules')
            && str_contains($this->playbookSource, 'eta_request')
            && str_contains($this->playbookSource, 'funds_safety_question')
            && str_contains($this->playbookSource, 'payment_proof_only');

        return $this->result(
            'edge_case_playbook_rules',
            'Edge-case playbook rules',
            $ok,
            $ok ? '' : 'Missing edge-case playbook rules or intents.',
        );
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function checkSafetyForbiddenClaimGuardrails(): array
    {
        $required = [
            'completed',
            'confirmed',
            'guaranteed',
            'soon',
            'funds are safe',
            'payment received',
            'уже получен',
            'гарантируем',
        ];

        $missing = [];
        foreach ($required as $phrase) {
            if (! str_contains($this->draftServiceSource, $phrase)) {
                $missing[] = $phrase;
            }
        }

        $ok = str_contains($this->draftServiceSource, 'postProcessWarnings') && $missing === [];

        return $this->result(
            'safety_forbidden_claim_guardrails',
            'Safety forbidden-claim guardrails',
            $ok,
            $ok ? '' : 'postProcessWarnings missing phrases: '.implode(', ', $missing),
        );
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function checkSampleTelegramFormat(SupportAiDraftService $aiDraft): array
    {
        $highSample = [
            'language' => 'ru',
            'confidence' => 'high',
            'operator_confidence' => 'high',
            'ux' => ['intent' => 'single_payment', 'policy_protected' => true],
            'options' => [
                ['label' => 'Short professional', 'style' => 'short_professional', 'text' => 'Выплата может зависеть от суммы, направления и банка. Если для вас важен один платёж, сообщите заранее.'],
            ],
        ];

        $highHtml = $aiDraft->formatTelegramSeparateMessage($highSample);
        if ($highHtml === null || $highHtml === '') {
            return $this->result(
                'sample_telegram_format',
                'Sample Telegram format',
                false,
                'High-confidence formatTelegramSeparateMessage returned empty output.',
            );
        }

        $highCodeCount = substr_count($highHtml, '<code>');
        $highHasFooter = str_contains($highHtml, 'Reply manually')
            || str_contains($highHtml, 'Operator review')
            || str_contains($highHtml, '━━━━━━━━━━━━')
            || str_contains($highHtml, 'high confidence');
        $highHasHeader = str_contains($highHtml, 'AI assistant')
            && ! str_contains($highHtml, 'Intent:')
            && ! str_contains($highHtml, 'Suggested reply:');

        $lowSample = [
            'language' => 'ru',
            'confidence' => 'low',
            'operator_confidence' => 'low',
            'ux' => ['intent' => 'unknown_context'],
            'options' => [
                ['label' => 'Short professional', 'style' => 'short_professional', 'text' => 'Проверим заявку №1780070862386 по системе.'],
                ['label' => 'Warm reassurance', 'style' => 'warm_reassurance', 'text' => 'Уточним статус заявки №1780070862386 и ответим здесь.'],
                ['label' => 'Detailed/checklist', 'style' => 'detailed_checklist', 'text' => 'Оператор проверит заявку №1780070862386 по системе.'],
                ['label' => 'Clarifying question / next step', 'style' => 'clarifying_next_step', 'text' => 'Проверим детали по заявке №1780070862386.'],
            ],
        ];

        $lowHtml = $aiDraft->formatTelegramSeparateMessage($lowSample);
        $lowCodeCount = $lowHtml !== null ? substr_count($lowHtml, '<code>') : 0;
        $lowHasFooter = $lowHtml !== null && (
            str_contains($lowHtml, 'Reply manually')
            || str_contains($lowHtml, 'Operator review')
            || str_contains($lowHtml, '━━━━━━━━━━━━')
        );

        $ok = $highCodeCount === 1
            && ! $highHasFooter
            && $highHasHeader
            && $lowCodeCount === 4
            && ! $lowHasFooter
            && str_contains((string) $lowHtml, 'AI assistant')
            && ! str_contains((string) $lowHtml, 'Review carefully.');

        return $this->result(
            'sample_telegram_format',
            'Sample Telegram format',
            $ok,
            $ok ? '' : sprintf(
                'Expected high=1 code + assistant header; low=4 codes; footer=no. got high=%d low=%d highHeader=%s',
                $highCodeCount,
                $lowCodeCount,
                $highHasHeader ? 'yes' : 'no',
            ),
        );
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function checkKnownDataDuplicateOrderIdRuntime(SupportAiDraftService $aiDraft): array
    {
        try {
            $ref = new ReflectionClass($aiDraft);
            $extract = $ref->getMethod('extractKnownConversationData');
            $extract->setAccessible(true);
            $protect = $ref->getMethod('extractProtectedIdentifiers');
            $protect->setAccessible(true);

            $raw = "1780070862386\nПроверим\nStill waiting order 1780070862386";
            $protected = $protect->invoke($aiDraft, $raw);
            /** @var array{order_ids: list<string>} $known */
            $known = $extract->invoke($aiDraft, $raw, $protected);

            $ok = $known['order_ids'] !== [] && in_array('1780070862386', $known['order_ids'], true);

            return $this->result(
                'known_data_duplicate_order_id_runtime',
                'Known-data duplicate order ID runtime',
                $ok,
                $ok ? '' : 'Duplicate order ID in thread was not extracted (regression on preg_match_all).',
            );
        } catch (\Throwable $e) {
            return $this->result(
                'known_data_duplicate_order_id_runtime',
                'Known-data duplicate order ID runtime',
                false,
                'Runtime check failed: '.$e->getMessage(),
            );
        }
    }

    /**
     * @param  list<array{name: string, label: string, status: string, message?: string}>  $checks
     */
    private function resolveOverallStatus(array $checks): string
    {
        $hasFail = false;
        $hasWarn = false;

        foreach ($checks as $check) {
            if ($check['status'] === 'FAIL') {
                $hasFail = true;
            }
            if ($check['status'] === 'WARN') {
                $hasWarn = true;
            }
        }

        if ($hasFail) {
            return 'FAIL';
        }

        if ($hasWarn) {
            return 'PASS_WITH_WARNINGS';
        }

        return 'PASS';
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function result(string $name, string $label, bool $ok, string $detail = '', bool $warn = false): array
    {
        if ($ok) {
            return [
                'name' => $name,
                'label' => $label,
                'status' => 'PASS',
            ];
        }

        if ($warn) {
            return [
                'name' => $name,
                'label' => $label,
                'status' => 'WARN',
                'message' => $detail,
            ];
        }

        return [
            'name' => $name,
            'label' => $label,
            'status' => 'FAIL',
            'message' => $detail,
        ];
    }

    /**
     * @param  array{name: string, label: string, status: string, message?: string}  $check
     */
    private function formatHumanLine(array $check): string
    {
        $line = $check['status'].' '.$check['label'];
        if (! empty($check['message'])) {
            $line .= ' — '.$check['message'];
        }

        return $line;
    }
}
