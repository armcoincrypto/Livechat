<?php
declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Models\Task;
use App\Models\TelegramNotification;
use App\Notifications\TelegramPaymentFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class TelegramAutoPaymentFailed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Детали заявки
     *
     * @var Task
     */
    protected Task $order;

    /**
     * Текст ошибки
     *
     * @var string|null
     */
    protected string|null $error_text;

    /**
     * Create a new job instance.
     *
     * @param Task $order
     * @param string|null $error
     */
    public function __construct(Task $order, string|null $error = null)
    {
        $this->order = $order;
        $this->error_text = $error;
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
        $notifications = TelegramNotification::where('status', 1)->where('ext_params->is_failed_for_pay', 1)->get();
        foreach ($notifications as $notification) {

            Notification::route('telegram', $notification->id_channel)
                ->notify(new TelegramPaymentFailed($notification, $this->order, $this->error_text));
        }
    }
}
