<?php

namespace iEXPackages\SmartMailer\Conditions;

use Illuminate\Database\Eloquent\Model;

class OrderFrozenCondition implements MailConditionInterface
{
    protected Model $order;

    public function __construct(Model $model)
    {
        $this->order = $model;
    }

    public function shouldSend(): bool
    {
        return $this->isNotifyEnabled() && $this->isFrozenStatus();
    }

    protected function isNotifyEnabled(): bool
    {
        return (int)iEXSetting('is_notify_order_defer') === 1;
    }

    protected function isFrozenStatus(): bool
    {
        return (int)$this->order->status === 8;
    }
}
