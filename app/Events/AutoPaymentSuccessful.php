<?php

namespace App\Events;

class AutoPaymentSuccessful
{
    /**
     * Детали заявки
     *
     * @var \App\Models\Task
     */
    public $order;

    /**
     * Провайдер
     *
     * @var string
     */
    public $provider;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\Task  $order
     * @param  string  $provider
     */
    public function __construct($order, $provider)
    {
        $this->order = $order;
        $this->provider = $provider;
    }
}
