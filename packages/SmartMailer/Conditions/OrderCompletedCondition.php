<?php

namespace iEXPackages\SmartMailer\Conditions;

use Illuminate\Database\Eloquent\Model;

class OrderCompletedCondition implements MailConditionInterface
{
    protected Model $order;

    public function __construct(Model $model)
    {
        $this->order = $model;
    }

    public function shouldSend(): bool
    {
        return $this->isMailingEnabled() && $this->isCompletedStatus();
    }

    protected function isMailingEnabled(): bool
    {
        return (int)iEXSetting('is_mail_order_change_status') === 1;
    }

    protected function isCompletedStatus(): bool
    {
        return (int)$this->order->status === 4;
    }
}
