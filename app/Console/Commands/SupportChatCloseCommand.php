<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SupportConversation;
use iEXPackages\SupportChat\Services\SupportConversationLifecycleService;
use Illuminate\Console\Command;

final class SupportChatCloseCommand extends Command
{
    protected $signature = 'support-chat:close
        {identifier : Conversation UUID or Support ID (e.g. S-0000042)}
        {--reason=Operator closed via CLI : Reason string for logs only}';

    protected $description = 'Close a support conversation (Telegram replies will be ignored until visitor reopens by messaging).';

    public function handle(SupportConversationLifecycleService $lifecycle): int
    {
        $raw = (string) $this->argument('identifier');
        $conversation = SupportConversation::findByPublicIdOrUuid($raw);

        if ($conversation === null) {
            $this->error('support-chat: conversation not found for identifier.');

            return self::FAILURE;
        }

        if ($conversation->isClosed()) {
            $this->warn('Conversation already closed: '.$conversation->public_support_id);

            return self::SUCCESS;
        }

        $lifecycle->closeByOperator($conversation, 'cli', [
            'reason' => (string) $this->option('reason'),
        ]);

        $this->info('Closed '.$conversation->public_support_id.' ('.$conversation->uuid.')');

        return self::SUCCESS;
    }
}
