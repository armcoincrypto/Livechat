<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

final class SupportChatProdReadinessAuditCommand extends Command
{
    protected $signature = 'support-chat:prod-readiness-audit
                            {--json : Output machine-readable JSON}';

    protected $description = 'Local production-readiness checks for Support Chat AI (no OpenAI, no Telegram, no DB writes).';

    private string $draftServicePath;

    private string $telegramOutboundPath;

    private string $draftServiceSource = '';

    private string $telegramOutboundSource = '';

    public function handle(): int
    {
        $this->draftServicePath = base_path('packages/SupportChat/Services/SupportAiDraftService.php');
        $this->telegramOutboundPath = base_path('packages/SupportChat/Services/SupportTelegramOutboundService.php');

        if (! is_readable($this->draftServicePath) || ! is_readable($this->telegramOutboundPath)) {
            $this->error('Support Chat service files are not readable.');

            return self::FAILURE;
        }

        $this->draftServiceSource = (string) file_get_contents($this->draftServicePath);
        $this->telegramOutboundSource = (string) file_get_contents($this->telegramOutboundPath);

        $checks = [
            $this->checkRegressionGuard(),
            $this->checkConfigPresence(),
            $this->checkPromptBoundConstants(),
            $this->checkOpenAiFailureSafe(),
            $this->checkTelegramFailureIsolation(),
            $this->checkSensitiveMasking(),
        ];

        $overall = $this->resolveOverallStatus($checks);

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'status' => $overall,
                'checks' => array_map(static fn (array $c): array => [
                    'name' => $c['name'],
                    'status' => $c['status'],
                    'message' => $c['message'] ?? '',
                ], $checks),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line('Support Chat Production Readiness Audit (local checks only)');
            $this->line('');
            foreach ($checks as $check) {
                $this->line($this->formatHumanLine($check));
            }
            $this->line('');
            $this->line('Result: '.$overall);
        }

        return $overall === 'FAIL' ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function checkRegressionGuard(): array
    {
        $exitCode = Artisan::call('support-chat:ai-regression-check', ['--json' => true]);
        $output = trim(Artisan::output());
        $decoded = json_decode($output, true);
        $status = is_array($decoded) ? (string) ($decoded['status'] ?? '') : '';

        $ok = $exitCode === 0 && $status === 'PASS';

        return $this->result(
            'regression_guard',
            'Regression guard (11/11)',
            $ok,
            $ok ? '' : 'Regression guard did not pass: exit='.$exitCode.', status='.$status,
        );
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function checkConfigPresence(): array
    {
        $configPath = base_path('config/support_chat.php');

        if (! is_readable($configPath)) {
            return $this->result(
                'config_presence',
                'Config presence',
                false,
                'config/support_chat.php is not readable.',
            );
        }

        $source = (string) file_get_contents($configPath);
        $ok = str_contains($source, "'ai'")
            && str_contains($source, "'telegram'")
            && str_contains($source, 'openai_api_key');

        $detail = $ok ? '' : 'config/support_chat.php missing ai/telegram sections.';

        if ($ok && ! is_readable(base_path('.env.example'))) {
            $detail = 'config OK; .env.example not present (optional).';
        }

        return $this->result(
            'config_presence',
            'Config presence',
            $ok,
            $detail,
        );
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function checkPromptBoundConstants(): array
    {
        $constants = [
            'MAX_RECENT_MESSAGES',
            'MAX_MESSAGE_CHARS',
            'MAX_MEMORY_CHARS',
            'MAX_PROMPT_CONTEXT_CHARS',
        ];

        $missing = [];
        foreach ($constants as $constant) {
            if (! str_contains($this->draftServiceSource, $constant)) {
                $missing[] = $constant;
            }
        }

        $ok = $missing === []
            && str_contains($this->draftServiceSource, 'truncateUtf8($contextBlock, self::MAX_PROMPT_CONTEXT_CHARS)');

        return $this->result(
            'prompt_bounds',
            'Prompt size / token protection constants',
            $ok,
            $ok ? '' : 'Missing prompt bound constants: '.implode(', ', $missing),
        );
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function checkOpenAiFailureSafe(): array
    {
        $ok = str_contains($this->draftServiceSource, 'catch (Throwable $e)')
            && str_contains($this->draftServiceSource, "return \$this->emptyResult(\$language, \$choices, 'exception')")
            && str_contains($this->draftServiceSource, "'rate_limit'")
            && str_contains($this->draftServiceSource, "'empty_response'")
            && str_contains($this->draftServiceSource, 'safeLogMessage');

        return $this->result(
            'openai_failure_safe',
            'OpenAI failure handling (emptyResult + safe logs)',
            $ok,
            $ok ? '' : 'Missing OpenAI failure-safe hooks in SupportAiDraftService.',
        );
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function checkTelegramFailureIsolation(): array
    {
        $visitorFirst = preg_match(
            '/sendTelegramText\([^;]+\);\s*\n\s*if \(\$telegramMessageId === null\)[^}]+\}\s*\n\s*\$this->sendAiSuggestionsSeparateMessage/s',
            $this->telegramOutboundSource
        ) === 1;

        $aiSeparateTry = str_contains($this->telegramOutboundSource, 'sendAiSuggestionsSeparateMessage')
            && str_contains($this->telegramOutboundSource, 'telegram_separate_failed')
            && str_contains($this->telegramOutboundSource, 'SupportChatDiagnosticsLog::sanitizeError');

        $ok = $visitorFirst && $aiSeparateTry;

        return $this->result(
            'telegram_failure_isolation',
            'Telegram visitor notification isolated from AI send',
            $ok,
            $ok ? '' : 'Visitor notification must succeed before AI suggestions; AI failures must be isolated.',
        );
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function checkSensitiveMasking(): array
    {
        $ok = str_contains($this->draftServiceSource, '[api-key-redacted]')
            && str_contains($this->draftServiceSource, '[bot-token-redacted]')
            && str_contains($this->draftServiceSource, '[secret-redacted]')
            && str_contains($this->draftServiceSource, 'SupportChatDiagnosticsLog::sanitizeError');

        return $this->result(
            'sensitive_masking',
            'Sensitive data masking in AI context and logs',
            $ok,
            $ok ? '' : 'Missing sanitization patterns for secrets/API keys in AI service.',
        );
    }

    /**
     * @param  list<array{name: string, label: string, status: string, message?: string}>  $checks
     */
    private function resolveOverallStatus(array $checks): string
    {
        foreach ($checks as $check) {
            if ($check['status'] === 'FAIL') {
                return 'FAIL';
            }
        }

        return 'PASS';
    }

    /**
     * @return array{name: string, label: string, status: string, message?: string}
     */
    private function result(string $name, string $label, bool $ok, string $detail = ''): array
    {
        if ($ok) {
            return [
                'name' => $name,
                'label' => $label,
                'status' => 'PASS',
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
