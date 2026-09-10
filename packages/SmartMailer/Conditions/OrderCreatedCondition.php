<?php

namespace iEXPackages\SmartMailer\Conditions;

use App\Models\Task;
use App\Services\Orders\InboundPaymentDestinationGuard;
use App\Services\Orders\PaymentDestinationFailClosedGuard;
use App\Services\Orders\PaymentDestinationRouter;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class OrderCreatedCondition implements MailConditionInterface
{
    protected Model $order;

    public function __construct(Model $model)
    {
        $this->order = $model;
    }

    /**
     * Destination-required rails (Kobbopay) may send the normal created mail
     * only after a usable deposit destination exists. Other rails unchanged.
     */
    public static function destinationAllowsCreatedMail(string $owner, bool $hasAssignedDestination): bool
    {
        return ! PaymentDestinationFailClosedGuard::mustNotRemainPayable($owner, $hasAssignedDestination);
    }

    public function shouldSend(): bool
    {
        return $this->isMailingEnabled()
            && $this->hasValidStatus()
            && ($this->isCardPaymentValid() || $this->isRequisitesPaymentValid())
            && $this->destinationAllowsCreatedMailForOrder();
    }

    protected function isMailingEnabled(): bool
    {
        return (int)iEXSetting('is_mail_order_change_status') === 1;
    }

    protected function hasValidStatus(): bool
    {
        return in_array((int)$this->order->status, [2, 3], true);
    }

    protected function isCardPaymentValid(): bool
    {
        return (int)$this->order->method_request_payment === 0
            && (int)$this->order->is_from_verification_card === 0;
    }

    protected function isRequisitesPaymentValid(): bool
    {
        return (int)$this->order->method_request_payment === 1
            && !empty($this->order->requisites_receive);
    }

    protected function destinationAllowsCreatedMailForOrder(): bool
    {
        if (! $this->order instanceof Task) {
            return true;
        }

        try {
            $this->order->loadMissing(['direction_exchange.currency1']);
            $owner = PaymentDestinationRouter::classifyDirection($this->order->direction_exchange);
        } catch (Throwable) {
            return true;
        }

        if (! PaymentDestinationFailClosedGuard::destinationRequired($owner)) {
            return true;
        }

        try {
            return InboundPaymentDestinationGuard::taskHasAssignedDestination($this->order);
        } catch (Throwable) {
            return false;
        }
    }
}
