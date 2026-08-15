<?php

namespace iEXPackages\Payment\Gateways\Payscrow;

use App\Models\MerchantTransactionId;
use App\Models\Task;
use Exception;
use iEXPackages\Payment\Engines\AbstractAPIRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MatthiasMullie\Minify\JS;

class APIRequest extends AbstractAPIRequest
{
    /**
     * Конструктор
     */
    public function __construct(array $config = [])
    {
        $this->initialize($config);
        $this->baseUrl = $this->getParameter('api_domain') ?? '';
    }

    /**
     * Баланс кошелька
     *
     * @param  null  $order_id
     *
     * @throws Exception
     */
    public function findTransaction($order_id = null): array
    {
        return $this->call('/api/v1/Orders/GetByExternalOrderId/'.$order_id, 'GET', []);
    }

    public function findTransactionByOrder(int $order_id): array
    {
        return $this->call('/api/v1/Orders/GetByOrderId/'.$order_id, 'GET', []);
    }

    /**
     * Отправить на счет
     *
     * @throws Exception
     */
    public function transfer(array $options = []): array
    {
        return $this->call('/api/v1/Orders/Create', 'POST', $options);
    }

    /**
     * Проверка поступлений
     * @throws Exception
     */
    public function checkPayment($options = []): CheckPayment
    {
        // Получаем детали транзакции по адресу с сервера
        $tx_refs = $this->findTransaction($options['order_id']);

        if (isset($tx_refs['success']) and (int)$tx_refs['success'] != 1) {
            throw new \Exception('Транзакция не найдена');
        }

        // Перед обработкой проверяем, существует ли вообще транзакция по адресу
        $validate_tx = (! empty($tx_refs) and $tx_refs['order']['externalOrderId'] == $options['order_id']);

        // Если не подтвержден входящий платеж, не пускаем дальше
        if (! $validate_tx) {
            throw new \Exception('Транзакция не найдена');
        }

        return new CheckPayment($tx_refs);
    }

    /**
     * Выплата на счет клиента
     *
     * @param array $options
     * @throws Exception
     */
    public function sendToWithdrawal($options = []): array
    {
        $merchantData = $this->getPayGateway();
        $method_pay = ($merchantData->ext_options['method_pay'] ?? '');
        $bank_name = ($merchantData->ext_options['bank_name'] ?? '');

        $orderData = $this->getOrderData();

        $params = [
            'basePaymentMethodId' => $bank_name,
            'externalOrderId' => (string) $orderData->id,
            'orderSide' => 'Sell',
            'targetAmount' => (string) $options['amount'],
            'feeType' => 'ChargeMerchant',
            'currencyType' => 'Fiat',
            'currency' => $method_pay,
            'customerName' => $orderData->recipient_fullname ?? 'Default Name',
            'customerPaymentAccount' => $options['score'],
        ];

        $response = $this->transfer($params);

        if(!isset($response['success']) or !$response['success']) {
            throw new \Exception(json_encode($response));
        }

        return [
            'status' => 0,
            'withdrawal_id' => $response['orderId'],
            'is_callback' => true,
            'defer_success' => true,
        ];
    }

    /**
     * Проверяем платеж
     *
     * @param string|null $transaction_id
     * @return array
     * @throws Exception
     */
    public function getPayCallback(string $transaction_id = null): array
    {
        $tx = $this->findTransactionByOrder($transaction_id);
        return [
            'is_canceled' => in_array($tx['order']['orderStatus'], ['CanceledByAdmin', 'CanceledByTimeout', 'CanceledByTrader', 'CanceledByMerchant', 'CanceledByCustomer']),
            'is_pending' => in_array($tx['order']['orderStatus'], ['Unpaid', 'Processing', 'Queued', 'Paid']),
            'is_successful' => in_array($tx['order']['orderStatus'], ['Completed'])
        ];
    }


    /**
     * Вызов для получения данных из ADGroup
     *
     * @return array
     *
     * @throws Exception
     */
    private function call(string $path, string $method, array $options = [])
    {
        if ($method == 'POST') {
            $digestInput = implode('/', [$path, $this->getParameter('api_secret'), $this->getParameter('api_key')])."\r\n".json_encode($options);
        } else {
            $digestInput = implode('/', [$path, $this->getParameter('api_secret'), $this->getParameter('api_key')]);
        }
        $digest = hash('sha256', $digestInput);

        return Http::baseUrl($this->baseUrl)->asJson()->withHeaders([
            'X-API-Key' => $this->getParameter('api_key'),
            'X-API-Sign' => $digest,
        ])->{\Str::lower($method)}($path, $options)->json();
    }
}
