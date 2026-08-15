<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SupportConversation;
use iEXPackages\SupportChat\Services\SupportConversationLifecycleService;
use Illuminate\Console\Command;

final class SupportChatReopenCommand extends Command
{
    protected $signature = 'support-chat:reopen
        {identifier : Conversation UUID or Support ID (e.g. S-0000042)}
        {--waiting=operator : Initial wait state: operator|visitor}';

    protected $description = 'Reopen a closed support conversation from CLI (sets waiting_operator or waiting_visitor).';

    public function handle(SupportConversationLifecycleService $lifecycle): int
    {
        $raw = (string) $this->argument('identifier');
        $conversation = SupportConversation::findByPublicIdOrUuid($raw);

        if ($conversation === null) {
            $this->error('support-chat: conversation not found for identifier.');

            return self::FAILURE;
        }

        $waiting = strtolower(trim((string) $this->option('waiting')));
        $lifecycle->reopenByOperator($conversation, $waiting, 'cli');

        $this->info('Reopened '.$conversation->public_support_id.' as '.$conversation->status);

        return self::SUCCESS;
    }
}
