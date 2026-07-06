<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use iEXPackages\SupportChat\Services\SupportAiDraftService;
use iEXPackages\SupportChat\Services\SupportAiSuggestionUxService;
use iEXPackages\SupportChat\Services\SupportTelegramAiActionService;
use Illuminate\Console\Command;

final class SupportChatTelegramActionsSelfTestCommand extends Command
{
    protected $signature = 'support-chat:telegram-actions-self-test';

    protected $description = 'Self-test Telegram AI card formatting and optional inline actions config.';

    public function handle(
        SupportAiSuggestionUxService $ux,
        SupportAiDraftService $draft,
        SupportTelegramAiActionService $actions,
    ): int {
        $pass = 0;
        $fail = 0;

        $highSample = [
            'language' => 'ru',
            'confidence' => 'high',
            'operator_confidence' => 'high',
            'ux' => ['intent' => 'single_payment', 'policy_protected' => true],
            'options' => [[
                'label' => 'Short professional',
                'style' => 'short_professional',
                'text' => 'Да, можем выполнить выплату одним платежом или разделить её на 1–3 платежа при необходимости.',
            ]],
        ];

        $highHtml = (string) ($draft->formatTelegramSeparateMessage($highSample) ?? '');
        if (str_contains($highHtml, 'AI assistant') && ! str_contains($highHtml, 'high confidence')) {
            $this->line('PASS compact high card (AI assistant header)');
            $pass++;
        } else {
            $this->error('FAIL compact high card');
            $fail++;
        }

        $longText = str_repeat('Да, можем выполнить выплату одним платежом. ', 40);
        $longSample = $highSample;
        $longSample['options'][0]['text'] = trim($longText);
        $longHtml = (string) ($draft->formatTelegramSeparateMessage($longSample) ?? '');
        if (! str_contains($longHtml, 'Full reply is longer.')) {
            $this->line('PASS long reply shown inline (no collapse note by default)');
            $pass++;
        } else {
            $this->error('FAIL long reply still collapsed');
            $fail++;
        }

        $markup = $actions->buildReplyMarkup(42, $longSample, true);
        if ($markup === null) {
            $this->line('PASS no inline buttons by default (actions disabled)');
            $pass++;
        } else {
            $this->warn('INFO inline buttons available when SUPPORT_AI_TELEGRAM_ACTIONS_ENABLED=1');
            $callback = $markup['inline_keyboard'][0][0]['callback_data'] ?? '';
            if ($callback === 'ai:f:42') {
                $this->line('PASS optional full callback payload');
                $pass++;
            } else {
                $this->error('FAIL optional callback payload');
                $fail++;
            }
        }

        $lowSample = [
            'language' => 'ru',
            'confidence' => 'low',
            'operator_confidence' => 'low',
            'ux' => ['intent' => 'unknown_context'],
            'options' => [
                ['label' => 'a', 'style' => 'x', 'text' => 'Option one text here.'],
                ['label' => 'b', 'style' => 'x', 'text' => 'Option two text here.'],
                ['label' => 'c', 'style' => 'x', 'text' => 'Option three text here.'],
                ['label' => 'd', 'style' => 'x', 'text' => 'Option four text here.'],
            ],
        ];
        $lowHtml = (string) ($draft->formatTelegramSeparateMessage($lowSample) ?? '');
        if (str_contains($lowHtml, 'AI assistant') && substr_count($lowHtml, '<code>') === 4) {
            $this->line('PASS low confidence numbered card');
            $pass++;
        } else {
            $this->error('FAIL low confidence numbered card');
            $fail++;
        }

        $this->line("Result: {$pass} passed, {$fail} failed");

        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }
}
