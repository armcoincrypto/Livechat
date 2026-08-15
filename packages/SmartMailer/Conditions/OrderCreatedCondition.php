<?php

namespace iEXPackages\SmartMailer\Conditions;

use Illuminate\Database\Eloquent\Model;


class OrderCreatedCondition implements MailConditionInterface
{
    protected Model $order;

    public function __construct(Model $model)
    {
        $this->order = $model;
    }

    public function shouldSend(): bool
    {
        return $this->isMailingEnabled()
            && $this->hasValidStatus()
            && ($this->isCardPaymentValid() || $this->isRequisitesPaymentValid());
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
}
