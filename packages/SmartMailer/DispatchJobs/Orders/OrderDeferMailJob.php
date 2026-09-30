<?php

namespace iEXPackages\SmartMailer\DispatchJobs\Orders;

use App\Models\Task;
use iEXPackages\SmartMailer\Dispatches\Orders\OrderDeferMail;
use iEXPackages\SmartMailer\SmartQueueableJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderDeferMailJob extends SmartQueueableJob
{
    /**
     * Детали заявки
     */
    protected Task $order;

    /**
     * Create a new job instance.
     */
    public function __construct(Task $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     */
    protected function run(): void
    {
        $pendingId = (int) ($this->order->id_pending_status ?? 0);
        $sentKey = 'smartmail_sent:order_defer:'.$this->order->id.':'.$pendingId;
        if (Cache::get($sentKey)) {
            Log::info('smartmail_order_defer_duplicate_skipped', [
                'order_id' => $this->order->id,
                'pending_status_id' => $pendingId,
            ]);

            return;
        }

        Mail::to($this->order->email)
            ->send(new OrderDeferMail($this->order));

        Cache::put($sentKey, 1, now()->addDays(7));
    }
}
