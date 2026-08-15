<?php

namespace iEXPackages\Transaction\Bindings;

use App\Events\OrderStatusesEvent;
use App\Models\PendingOrderStatus;
use iEXPackages\SmartMailer\Facades\SmartMailer;
use iEXPackages\SmartMailer\SmartMailerConditionFactory;
use Illuminate\Support\Facades\Log;

trait ManagersDefer
{
    private ?PendingOrderStatus $pending_status = null;

    public const STATUS_DEFERRED = 8;

    /**
     * Установить причину отложения заявки.
     */
    public function setDeferType(int $id = 1): static
    {
        $this->pending_status = PendingOrderStatus::find($id);

        return $this;
    }

    /**
     * Отложить (заморозить) заявку.
     *
     * @throws \Exception
     */
    public function defer(): void
    {
        $this->setStatus(self::STATUS_DEFERRED);

        if (iEXSetting('is_enabled_module_socket')) {
            broadcast(new OrderStatusesEvent($this->transaction));
        }

        if (!empty(optional($this->transaction->meta)->telegram_id)) {
            $message = "⚠️ Ваша заявка №" . current_order_id($this->transaction) . " была отложена." . PHP_EOL;

            $message .= isset($this->transaction->pending_order_status)
                ? 'Причина: ' . $this->transaction->pending_order_status->name
                : 'Причина не указана.';

            sendTelegramNotification($this->transaction->meta->telegram_id, $message);
        }


        if ($this->pending_status !== null) {
            optional($this->transaction)->update([
                'id_pending_status' => $this->pending_status->id,
            ]);

            // Отсылаем сообщение о создании заявки (стандартная)
            if (SmartMailerConditionFactory::make('order_defer', $this->transaction)->shouldSend())
            {
                SmartMailer::dispatch(
                    sendable: 'order_defer_job',
                    model: $this->transaction,
                    delaySeconds: 5,
                    queue: 'low'
                );
            }
        }
    }
}
