<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use App\Models\SupportConversation;
use iEXPackages\SupportChat\Services\SupportAiDraftService;
use Illuminate\Console\Command;

final class SupportChatAiDraftCommand extends Command
{
    protected $signature = 'support-chat:ai-draft
                            {conversation_id : Support conversation numeric id}
                            {--dry-run : Generate draft only; never send to visitor or Telegram}
                            {--choices= : Number of reply options (1-4); omit for dynamic UX count}';

    protected $description = 'Generate operator-assist AI draft reply option(s) for a support conversation (never auto-sends).';

    public function handle(SupportAiDraftService $aiDraft): int
    {
        $id = (int) $this->argument('conversation_id');
        if ($id < 1) {
            $this->error('Invalid conversation_id.');

            return self::FAILURE;
        }

        $choicesOpt = $this->option('choices');
        $choices = ($choicesOpt === null || $choicesOpt === '')
            ? null
            : max(1, min(4, (int) $choicesOpt));

        $conversation = SupportConversation::query()->find($id);
        if ($conversation === null) {
            $this->error('Conversation not found: '.$id);

            return self::FAILURE;
        }

        if (! $aiDraft->isEnabled()) {
            $this->warn('Support AI is disabled (SUPPORT_AI_ENABLED=0 or missing OPENAI_API_KEY).');
            $this->line('Config check only — no OpenAI request sent.');
            $this->line('');
            $this->line('Conversation: '.($conversation->public_support_id ?? $conversation->uuid));
            $this->line('Language: '.($conversation->locale ?? '—'));
            $this->line('Choices requested: '.($choices === null ? 'dynamic' : (string) $choices));

            return self::SUCCESS;
        }

        if (! (bool) $this->option('dry-run')) {
            $this->warn('Running in dry-run mode (this command never sends to visitors).');
        }

        $result = $aiDraft->draftForConversation($conversation, null, $choices);

        if ($result['error'] !== null && ($result['draft'] ?? null) === null && ($result['options'] ?? []) === []) {
            $this->error('Draft failed: '.$result['error']);

            return self::FAILURE;
        }

        $this->line('');
        $this->line('Conversation: '.($conversation->public_support_id ?? $conversation->uuid));
        $this->line('Language: '.$result['language']);
        $this->line('Confidence: '.($result['operator_confidence'] ?? $result['confidence']));
        if (is_array($result['ux'] ?? null)) {
            $ux = $result['ux'];
            $this->line('Intent: '.($ux['intent'] ?? '—'));
            if (! empty($ux['knowledge_matched'])) {
                $this->line('Knowledge: '.implode(', ', $ux['knowledge_matched']));
            }
            if (! empty($ux['templates_matched'])) {
                $this->line('Templates: '.implode(', ', $ux['templates_matched']));
            }
        }

        if ($result['warnings'] !== []) {
            $this->line('Warnings:');
            foreach ($result['warnings'] as $warning) {
                $this->line('* '.$warning);
            }
        }

        $options = $result['options'] ?? [];
        if ($options === [] && ! empty($result['draft'])) {
            $options = [[
                'label' => 'Short professional',
                'style' => 'short_professional',
                'text' => (string) $result['draft'],
            ]];
        }

        $this->line('');
        foreach ($options as $index => $option) {
            $num = $index + 1;
            $label = (string) ($option['label'] ?? ('Option '.$num));
            $text = (string) ($option['text'] ?? '');

            $this->line("Option {$num} — {$label}: {$text}");
            $this->line('');
            $this->line('--- COPY OPTION '.$num.' ---');
            $this->line($text);
            $this->line('--- END OPTION '.$num.' ---');
            $this->line('');
        }

        return self::SUCCESS;
    }
}
