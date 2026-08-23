<?php

declare(strict_types=1);

namespace App\Services\TelegramOperator;

use Illuminate\Support\Facades\Cache;

final class TelegramCompletionNoticePublisher
{
    public function __construct(
        private readonly TelegramOperatorBotClient $bot,
    ) {
    }

    /**
     * @param  array<string, mixed>  $flow
     * @param  list<list<array{text: string, callback_data?: string, url?: string}>>|null  $keyboard
     */
    public function publish(
        int $taskId,
        int $telegramUserId,
        int|string|null $confirmChatId,
        ?int $confirmMessageId,
        array $flow,
        string $text,
        ?array $keyboard,
    ): string {
        $key = 'telegram_complete_notice:'.$taskId;
        if (! Cache::add($key, 1, now()->addDay())) {
            return 'duplicate_suppressed';
        }

        $origChat = $flow['chat_id'] ?? null;
        $origMsg = isset($flow['message_id']) ? (int) $flow['message_id'] : null;
        $ops = TelegramCompletionNoticePlan::decide($origChat, $origMsg, $confirmChatId, $confirmMessageId, $telegramUserId);

        foreach ($ops as $op) {
            if ($op['op'] === 'edit') {
                $this->bot->editMessage($op['chat'], (int) $op['message'], $text, $keyboard ?? []);
            } elseif ($op['op'] === 'send') {
                $this->bot->sendMessage($op['chat'], $text, $keyboard);
            } elseif ($op['op'] === 'delete') {
                $deleted = $this->bot->deleteMessage($op['chat'], (int) $op['message']);
                if (! $deleted) {
                    $this->bot->editMessage($op['chat'], (int) $op['message'], '✅', []);
                }
            }
        }

        return $ops[0]['op'] ?? 'none';
    }
}
