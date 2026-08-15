<?php

namespace App\Jobs;

use App\Mail\Order\OrderChatMail;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OrderChatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * E-mail получателя
     *
     * @var string
     */
    protected $to_email;

    /**
     * Дополнительные опции к отправке
     *
     * @var array
     */
    protected $options = [];

    /**
     * Детали заявки
     *
     * @var Task
     */
    protected $order;

    /**
     * Create a new job instance.
     *
     * @param  null  $to
     */
    public function __construct($to, Task $order, array $params = [])
    {
        $this->to_email = $to;
        $this->options = $params;
        $this->order = $order;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \Mail::to($this->to_email)->send(new OrderChatMail(
            $this->order, $this->options
        ));
    }
}
