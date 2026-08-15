<?php

namespace iEXPackages\SmartMailer\Conditions;

use Illuminate\Database\Eloquent\Model;

class OrderRejectedCondition implements MailConditionInterface
{
    protected Model $order;

    public function __construct(Model $model)
    {
        $this->order = $model;
    }

    public function shouldSend(): bool
    {
        return $this->isMailingEnabled() && $this->isRejectedStatus();
    }

    protected function isMailingEnabled(): bool
    {
        return (int)iEXSetting('is_mail_order_change_status') === 1;
    }

    protected function isRejectedStatus(): bool
    {
        return (int)$this->order->status === 5;
    }
}
