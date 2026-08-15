<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use iEXPackages\SupportChat\Services\SupportAiPlaybookService;
use Illuminate\Console\Command;

final class SupportChatAiPlaybookCommand extends Command
{
    protected $signature = 'support-chat:ai-playbook
                            {--dry-run : Read-only; no OpenAI, no DB writes}
                            {--limit=50 : Max conversations to scan}';

    protected $description = 'Extract anonymized operator reply patterns from historical support chats (read-only).';

    public function handle(SupportAiPlaybookService $playbook): int
    {
        $limit = max(1, min(200, (int) $this->option('limit')));

        $this->line('Support AI Playbook — read-only extraction');
        $this->line('Dry-run: yes (never writes DB, never calls OpenAI)');
        $this->line('Limit: '.$limit);
        $this->line('');

        $result = $playbook->buildPlaybook($limit);

        $this->line($result['playbook_text']);
        $this->line('');
        $this->line('Summary: '.$result['conversations_scanned'].' conversations, '.$result['operator_replies'].' operator replies grouped.');

        return self::SUCCESS;
    }
}
