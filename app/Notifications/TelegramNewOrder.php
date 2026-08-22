<?php

namespace App\Notifications;

use App\Models\Task;
use App\Services\TelegramOperator\TelegramBotTokenResolver;
use App\Services\TelegramOperator\TelegramOrderOperatorWorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramNewOrder extends Notification implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public int $timeout = 20;

    public int $uniqueFor = 86400;

    /**
     * @param  mixed  $tokens  TelegramNotification (token + default channel)
     * @param  mixed  $order  Task
     * @param  string|null  $chatIdOverride  Optional destination (channel or operator DM)
     */
    public function __construct(
        public mixed $tokens,
        public mixed $order,
        public ?string $chatIdOverride = null,
    ) {
        $this->onQueue('high');
    }

    public function uniqueId(): string
    {
        $orderId = $this->order instanceof Task ? (string) $this->order->id : 'unknown';
        $dest = $this->resolvedChatId() ?: 'default';

        return 'telegram-new-order:'.$orderId.':'.$dest;
    }

    public function via()
    {
        return [TelegramChannel::class];
    }

    /**
     * Destination chat: override (DM / alternate) or configured channel.
     */
    public function resolvedChatId(): string
    {
        if ($this->chatIdOverride !== null && $this->chatIdOverride !== '') {
            return (string) $this->chatIdOverride;
        }

        return (string) ($this->tokens->id_channel ?? '');
    }

    /**
     * @throws \Throwable
     */
    public function toTelegram()
    {
        $token = app(TelegramBotTokenResolver::class)->resolve(
            $this->tokens->token_access ?? null
        );
        $telegram = TelegramMessage::create()
            ->token($token)
            ->to($this->resolvedChatId());

        $telegram->content(view('telegram.message', [
            'detail' => $this->order,
        ])->render());

        if (filter_var(config('telegram_operator.actions_enabled', false), FILTER_VALIDATE_BOOLEAN)
            && $this->order instanceof Task
        ) {
            $taskId = (int) $this->order->id;
            foreach (TelegramOrderOperatorWorkflowService::notificationKeyboard($taskId) as $row) {
                foreach ($row as $button) {
                    if (isset($button['callback_data'])) {
                        $telegram->buttonWithCallback($button['text'], $button['callback_data'], 1);
                    } elseif (isset($button['url'])) {
                        $telegram->button($button['text'], $button['url'], 1);
                    }
                }
            }
        }

        return $telegram->options([
            'parse_mode' => 'HTML',
        ]);
    }
}
