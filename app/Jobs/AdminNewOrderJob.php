<?php

namespace App\Jobs;

use App\Mail\Admin\AdminNewOrderMail;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class AdminNewOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Детали заявки
     */
    protected Task $order;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Task $task)
    {
        $this->order = $task;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            foreach (get_admin_recipient_email() as $value) {
                Mail::to($value)
                    ->send(new AdminNewOrderMail($this->order));
            }
        } catch (\Exception|NotFoundExceptionInterface|ContainerExceptionInterface $e) {
            //
        }
    }
}
