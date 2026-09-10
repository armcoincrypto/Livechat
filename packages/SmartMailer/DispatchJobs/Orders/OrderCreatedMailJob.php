<?php

namespace iEXPackages\SmartMailer\DispatchJobs\Orders;

use App\Models\Task;
use iEXPackages\SmartMailer\Conditions\OrderCreatedCondition;
use iEXPackages\SmartMailer\Dispatches\Orders\OrderCreatedMail;
use iEXPackages\SmartMailer\SmartQueueableJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OrderCreatedMailJob extends SmartQueueableJob
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
        try {
            $this->order->refresh();
        } catch (Throwable) {
            Log::warning('order_created_mail_skipped', [
                'task_id' => $this->order->id ?? null,
                'public_id' => $this->order->public_id ?? null,
                'reason' => 'order_refresh_failed',
            ]);

            return;
        }

        if (! (new OrderCreatedCondition($this->order))->shouldSend()) {
            Log::info('order_created_mail_skipped', [
                'task_id' => $this->order->id,
                'public_id' => $this->order->public_id,
                'status' => (int) $this->order->status,
                'reason' => 'order_created_condition_false',
            ]);

            return;
        }

        Mail::to($this->order->email)
            ->send(new OrderCreatedMail($this->order));
    }
}
