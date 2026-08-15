<?php

namespace iEXPackages\SmartMailer\DispatchJobs\Orders;

use App\Models\Task;
use iEXPackages\SmartMailer\Dispatches\Orders\OrderCreatedMail;
use iEXPackages\SmartMailer\SmartQueueableJob;
use Illuminate\Support\Facades\Mail;

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
        Mail::to($this->order->email)
            ->send(new OrderCreatedMail($this->order));
    }
}
