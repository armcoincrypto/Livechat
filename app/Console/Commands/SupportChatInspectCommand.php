<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SupportConversation;
use Illuminate\Console\Command;

final class SupportChatInspectCommand extends Command
{
    protected $signature = 'support-chat:inspect
        {identifier? : Conversation UUID or Support ID — omit to list recent}
        {--limit=15 : Max rows when listing recent conversations}';

    protected $description = 'Read-only support chat inspection for operators (no secrets).';

    public function handle(): int
    {
        $identifier = $this->argument('identifier');

        if ($identifier === null || trim((string) $identifier) === '') {
            $limit = max(1, min(100, (int) $this->option('limit')));
            $rows = SupportConversation::query()
                ->orderByDesc('id')
                ->limit($limit)
                ->get(['id', 'uuid', 'public_support_id', 'status', 'visitor_email', 'last_operator_display_name', 'closed_at', 'updated_at']);

            $this->table(
                ['id', 'public_support_id', 'status', 'visitor_email', 'last_operator', 'closed_at'],
                $rows->map(fn (SupportConversation $c) => [
                    (string) $c->id,
                    (string) ($c->public_support_id ?? '—'),
                    $c->status,
                    $c->visitor_email,
                    (string) ($c->last_operator_display_name ?? '—'),
                    $c->closed_at?->toIso8601String() ?? '—',
                ])->all()
            );

            return self::SUCCESS;
        }

        $conversation = SupportConversation::findByPublicIdOrUuid((string) $identifier);

        if ($conversation === null) {
            $this->error('support-chat: conversation not found.');

            return self::FAILURE;
        }

        $conversation->loadCount('messages');

        $this->line('public_support_id: '.$conversation->public_support_id);
        $this->line('uuid: '.$conversation->uuid);
        $this->line('status: '.$conversation->status);
        $this->line('waiting_on: '.($conversation->waitingOn() ?? '—'));
        $this->line('visitor: '.$conversation->visitor_name.' <'.$conversation->visitor_email.'>');
        $this->line('locale: '.($conversation->locale ?? '—'));
        $this->line('last_operator_telegram_user_id: '.($conversation->last_operator_telegram_user_id ?? '—'));
        $this->line('last_operator_display_name: '.($conversation->last_operator_display_name ?? '—'));
        $this->line('last_operator_username: '.($conversation->last_operator_telegram_username ?? '—'));
        $this->line('messages_count: '.(string) ($conversation->messages_count ?? 0));
        $this->line('closed_at: '.($conversation->closed_at?->toIso8601String() ?? '—'));
        $this->line('updated_at: '.$conversation->updated_at?->toIso8601String());

        return self::SUCCESS;
    }
}
