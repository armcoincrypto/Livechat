<?php

namespace iEXPackages\Transaction\Bindings;

use App\Events\OrderStatusesEvent;
use App\Jobs\AdminNewOrderJob;
use App\Jobs\Order\OrderStatusMailJob;
use App\Models\DirectionExchange;
use App\Models\Reserve;
use App\Models\Task;
use App\Models\TaskInfo;
use App\Services\Reserves\ReserveLinkResolver;
use Carbon\Carbon;
use iEXPackages\SmartMailer\Facades\SmartMailer;
use Psr\SimpleCache\InvalidArgumentException;

trait ManagersBootstrap
{
    /**
     * Восстановление отклоненной заявки
     *
     * @throws \Exception
     * @throws InvalidArgumentException
     */
    public function restoreOrder(): void
    {
        $this->setStatus(3);
        $this->transaction->update([
            'is_from_verification_card' => 0
        ]);

        // Уведомлять клиента о восстановлении заявки
        if ((int)iEXSetting('is_notify_order_restore') == 1)
        {
            SmartMailer::dispatch(
                sendable: 'order_restored',
                model: $this->transaction,
                email: $this->transaction->email,
                delaySeconds: 10,
                queue: 'low'
            );
        }
    }

    /**
     * Восстановление отклоненные заявки
     *
     * @throws \Exception
     */
    public function handlerOrderFromCron(): void
    {
        $this->transaction->update([
            'status' => 3,
            'is_autopay_limit' => 1,
            'started_at' => Carbon::now()->toDateTimeString(),
            'next_checkout_at' => Carbon::now()->addSeconds(30)->toDateTimeString(),
        ]);

        // Если заявка мошенническая то морозим
        if ($this->transaction->task_info->is_freeze_scam == 1) {
            $this->setDeferType(5);
            $this->defer();
        } else {

            if (iEXSetting('is_enabled_module_socket') == 1) {
                try {
                    broadcast(new OrderStatusesEvent($this->transaction));
                } catch (\Exception $exception) {
                    //
                }
            }

            // Уведомляем оператора о новой заявки
            if ((int) iEXSetting('is_mail_notify_order_manager') == 1) {
                // Уведомляем оператора о новой заявки
                dispatch(new AdminNewOrderJob($this->transaction))->delay(
                    now()->addSeconds(30)
                )->onQueue('low');
            }

            // Уведомлять о новых заявках в Telegram (Для операторов)
            \iEXApp::telegramNotificationForChannel('new_order_for_operator', $this->transaction);

            // Уведомляем клиента об отклонении заявки
            if ((int) iEXSetting('is_mail_order_change_status') == 1) {
                $delay = now()->addSeconds(15);
                dispatch(new OrderStatusMailJob($this->transaction))
                    ->delay($delay)->onQueue('high');
            }
        }
    }

    /**
     * Получить детали "Направления"
     */
    public function getDirectionExchange(): DirectionExchange
    {
        return $this->transaction->direction_exchange;
    }

    /**
     * Получить детали валюты "Отдаю"
     *
     * @return \App\Models\Currency
     */
    public function getCurrencyIn()
    {
        return $this->transaction->direction_exchange->currency1;
    }

    /**
     * Получить детали валюты "Получаю"
     *
     * @return \App\Models\Currency
     */
    public function getCurrencyOut()
    {
        return $this->transaction->direction_exchange->currency2;
    }

    public function getCurrencyOutApi()
    {
        return $this->getCurrencyOut()->gateway_payments ?? [];
    }

    /**
     * Получаем детали выплаты
     *
     * @deprecated
     *
     * @return \App\Models\GatewayPayment
     */
    public function getPay()
    {
        return $this->transaction->direction_exchange->currency2->pay;
    }

    /**
     * Получение дневной суммы обменов
     *
     * @return float
     */
    public function getAmountOutDay()
    {
        $order = Task::whereBetween('created_at', [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()])
            ->where('status', '=', 4)->sum('receiving_price');

        return (float) $order + $this->transaction->receiving_price;
    }

    /**
     * Получаем детали мерчанта
     *
     * @return \App\Models\GatewayMerchant
     */
    public function getMerchant()
    {
        return $this->transaction->merchant;
    }

    public function historyPaymentTx()
    {
        return $this->transaction->history_payment_transaction;
    }

    /**
     * Получить детали платежной системы "Отдаю"
     *
     * @return \App\Models\Payment
     */
    public function getPaymentIn()
    {
        return $this->getCurrencyIn()->payment;
    }

    /**
     * Получить детали платежной системы "Получаю"
     *
     * @return \App\Models\Payment
     */
    public function getPaymentOut()
    {
        return $this->getCurrencyOut()->payment;
    }

    /**
     * Получить код валюты "Отдаю"
     *
     * @return \App\Models\CodeCurrency
     */
    public function getCodeIn()
    {
        return $this->getCurrencyIn()->code_currency;
    }

    /**
     * Получить код валюты "Получаю"
     *
     * @return \App\Models\CodeCurrency
     */
    public function getCodeOut()
    {
        return $this->getCurrencyOut()->code_currency;
    }

    /**
     * Получить резерв "Отдаю".
     *
     * Новая система связанных резервов:
     * - у валюты есть свой Reserve
     * - если Reserve привязан цепочкой — операции должны выполняться по корневому резерву
     *   (reserve_links + reserve_closures)
     *
     * @return Reserve|null
     */
    public function getReserveIn(): ?Reserve
    {
        $reserve = $this->getCurrencyIn()?->reserve;

        if (!$reserve) {
            return null;
        }

        /** @var ReserveLinkResolver $resolver */
        $resolver = app(ReserveLinkResolver::class);

        // Берём корневой резерв цепочки (если связей нет — вернётся он же)
        return $resolver->getRoot($reserve);
    }

    /**
     * Получить резерв "Получаю".
     *
     * Новая система связанных резервов:
     * - у валюты есть свой Reserve
     * - если Reserve привязан цепочкой — операции должны выполняться по корневому резерву
     *   (reserve_links + reserve_closures)
     *
     * @return Reserve|null
     */
    public function getReserveOut(): ?Reserve
    {
        $reserve = $this->getCurrencyOut()?->reserve;

        if (!$reserve) {
            return null;
        }

        /** @var ReserveLinkResolver $resolver */
        $resolver = app(ReserveLinkResolver::class);

        return $resolver->getRoot($reserve);
    }

    /**
     * Получить детали реквизитов
     */
    public function getRequisites()
    {
        return $this->transaction->payment_requisites;
    }

    public function getRatesData()
    {
        return $this->transaction->tasks_rates_data;
    }

    /**
     * Получить резервы "Отдаю"
     *
     * @param  bool  $spaces
     * @return Reserve
     */
    public function getPropsIn($spaces = false)
    {
        if ($spaces == true) {
            return remove_all_spaces($this->transaction->from_shot);
        }

        return $this->transaction->from_shot;
    }

    /**
     * Получить резервы "Получаю"
     *
     * @param  bool  $spaces
     * @return Reserve
     */
    public function getPropsOut($spaces = false)
    {
        if ($spaces == true) {
            return remove_all_spaces($this->transaction->to_shot);
        }

        return $this->transaction->to_shot;
    }

    public function getWalletTransaction()
    {
        return $this->transaction->wallet_transaction;
    }

    public function getHistoryCode()
    {
        return $this->transaction->history_code;
    }

    /**
     * Дополнительная информация о заявке
     *
     * @return TaskInfo
     */
    public function getTaskInfo()
    {
        return $this->transaction->task_info;
    }

    /**
     * История пересчета
     *
     * @return \App\Models\HistoryRecalculation
     */
    public function getHistoryRecalculation()
    {
        return $this->transaction->history_recalculation;
    }

    /**
     * История операторов
     *
     * @return \App\Models\TaskHistoryOperator
     */
    public function getHistoryOperators()
    {
        return $this->transaction->history_operators;
    }

    /**
     * Включить ручную выплату
     */
    public function isDisabledManualButton()
    {

        $pay = $this->getPay();
        if (isset($pay) and $pay->manual_pay_order == 1) {
            return 1;
        }

        return 0;
    }

    /**
     * Получении истории чата
     *
     * @return \App\Models\TaskChat
     */
    public function getHistoryChat()
    {
        return $this->transaction->tasks_chat;
    }

    /**
     * Получить детали клиента
     *
     * @return \App\Models\User
     */
    public function getClient()
    {
        return $this->transaction->user;
    }
}
