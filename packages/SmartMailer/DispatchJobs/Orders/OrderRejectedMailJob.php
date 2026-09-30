<?php

namespace iEXPackages\SmartMailer\DispatchJobs\Orders;

use App\Models\Task;
use iEXPackages\SmartMailer\Dispatches\Orders\OrderRejectedMail;
use iEXPackages\SmartMailer\SmartQueueableJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderRejectedMailJob extends SmartQueueableJob
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
        $sentKey = 'smartmail_sent:order_rejected:'.$this->order->id;
        if (Cache::get($sentKey)) {
            Log::info('smartmail_order_rejected_duplicate_skipped', [
                'order_id' => $this->order->id,
            ]);

            return;
        }

        Mail::to($this->order->email)
            ->send(new OrderRejectedMail($this->order));

        Cache::put($sentKey, 1, now()->addDays(7));
    }
}
