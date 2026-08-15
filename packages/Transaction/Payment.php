<?php
declare(strict_types=1);

namespace iEXPackages\Transaction;

use App\Models\Task;
use iEXPackages\TagProcessors\TagProcessors;
use Illuminate\Support\Str;

class Payment
{
    /**
     * Детали транзакции
     *
     * @var Task
    */
    protected Task $order;

    protected mixed $apiService;

    /**
     * Экземпляр деталей по выплате
     *
     * @param Task $task
     * @param mixed $apiService
     */
    public function __construct(Task $task, mixed $apiService)
    {
        $this->order = $task;
        $this->apiService = $apiService;
    }

    /**
     * Получаем итоговую сумму для выплаты
     *
     * @return float
     */
    public function getFinallyAmount(): float
    {
        if ((float)$this->order->out_price_fee > 0) {
            return (float)$this->order->out_price_fee;
        }

        return match ((int)($this->apiService->pay_amount_type ?? 0)) {
            1 => (float)$this->order->receiving_price_with_comm,
            2 => (float)$this->order->receiving_price_default,
            default => (float)$this->order->receiving_price_with_comm_pay,
        };
    }

    /**
     * Получаем код для выплаты
     *
     * @param bool $default_currency
     * @return string
     */
    public function getNetworkCode(bool $default_currency = true): string
    {
        $extParams = ($this->apiService['ext_params'] ?? []);

        // Получаем код валюты из модуля "Авто-выплаты"
        $pay_code = ($extParams['code_currency'] ?? '');

        if(!empty($pay_code)) {
            return $pay_code;
        }


        // Проверяем в направлениях для начала
        if(!empty($this->order->direction_exchange->network_code_out)) {
            return $this->order->direction_exchange->network_code_out;
        }


        // Check currency-level bindings and per-payment override
        $currencyHasPayments = $this->order->direction_exchange->currency2->gateway_payments->where('status', '=', 1)->isNotEmpty();

        if ($currencyHasPayments) {
            // Try to read per-payment network_code from the pivot for the current payment
            $gatewayPaymentId = $this->apiService?->id ?? null;

            if ($gatewayPaymentId) {
                $pivotNetwork = $this->order->direction_exchange->currency2
                    ->gateway_payments()
                    ->where('currency_payments.gateway_payment_id', $gatewayPaymentId)
                    ->value('currency_payments.network_code');

                if (!empty($pivotNetwork)) {
                    return $pivotNetwork; // specific override for this payment gateway
                }
            }

            // Получаем код валюты из самих валют
            $currency_code = $this->order->direction_exchange->currency2->network_code_out;
            // Если пусты, оба значения, то берем стандартный код
            if (!empty($currency_code)) {
                return $currency_code;
            }
        }

        return $default_currency ? $this->order->direction_exchange->currency2->code_currency->name : '';
    }

    /**
     * Получаем номер счета
     *
     * @return string
     */
    public function getScore(): string
    {
        return str_replace(' ', '', $this->order->to_shot);
    }

    /**
     * Получаем комментарий
     *
     * @return string
     */
    public function getComment(): string
    {
        $comment = trim(is_string($this->apiService->comment) ? $this->apiService->comment : '');

        if ($comment !== '') {
            $processedComment = app(TagProcessors::class)
                ->setProcessor('order')
                ->setText($comment)
                ->setData($this->order)
                ->process()
                ->getText();

            return Str::markdown($processedComment);
        }

        return sprintf('%s:%s', iEXContentLanguage('sitename'), $this->order->id);
    }
}
