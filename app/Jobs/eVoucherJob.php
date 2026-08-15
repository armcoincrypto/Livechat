<?php

namespace App\Jobs;

use App\Mail\NotificationClient;
use App\Models\EVoucherCode;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class eVoucherJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Детали транзакции
     *
     * @var \App\Models\Task
     */
    protected $order;

    /**
     * Create a new job instance.
     */
    public function __construct(Task $task)
    {
        $this->order = $task;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $voucher = EVoucherCode::where('id_task', $this->order->id)->first();
        $text = '<b>'.__('jobs.voucher.num').':</b> '.$voucher->voucher_num.'<br />';
        $text .= '<b>'.__('jobs.voucher.code').':</b> '.$voucher->voucher_code;

        Mail::to($this->order->user->email)
            ->send(new NotificationClient($this->order->toArray(), [
                'message_success' => $text,
            ]));
    }
}
