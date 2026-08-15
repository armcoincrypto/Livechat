<?php

namespace App\Listeners;

use App\Events\AutoPaymentSuccessful;
use App\Support\Facades\iEXApp;

class AutoPaymentSuccessfulListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(AutoPaymentSuccessful $event): void
    {
        iEXApp::telegramNotificationForChannel('order_pay', $event->order, $event->provider);
    }
}
