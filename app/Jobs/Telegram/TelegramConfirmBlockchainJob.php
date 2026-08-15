<?php

namespace App\Jobs\Telegram;

use App\Models\Task;
use App\Notifications\TelegramConfirmBlockchain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TelegramConfirmBlockchainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Детали заявки
     *
     * @var Task
     */
    protected $order;

    /**
     * Провайдер
     *
     * @var string
     */
    protected $provider;

    /**
     * Create a new job instance.
     */
    public function __construct(Task $order, string $provider)
    {
        $this->order = $order;
        $this->provider = $provider;
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function handle()
    {
        // Отправляем уведомления в канал
        $this->order->notify(new TelegramConfirmBlockchain($this->provider));
    }
}
