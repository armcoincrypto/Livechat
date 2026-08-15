<?php

namespace iEXPackages\Transaction\Bindings;

use App\Jobs\Order\OrderStatusMailJob;
use App\Jobs\OrderShotBlackListJob;
use App\Mail\Order\OrderRecountMail;
use App\Mail\Order\OrderRestoreMail;
use Illuminate\Support\Facades\Log;

trait ManagersNotification
{
    /**
     * Уведомлять клиента в случае пересчета
     */
    public function notifyRecountForMail(): void
    {
        try {
            \Mail::to($this->transaction->email)
                ->send(new OrderRecountMail($this->transaction));
        } catch (\Exception $exception) {
            \Log::error('OrderRecountMail - '.$exception->getMessage());
        }
    }
}
