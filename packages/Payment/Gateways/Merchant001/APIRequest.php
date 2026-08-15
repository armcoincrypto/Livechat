<?php

namespace iEXPackages\Payment\Gateways\Merchant001;

use App\Models\MerchantTransactionId;
use Exception;
use iEXPackages\Payment\Engines\AbstractAPIRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class APIRequest extends AbstractAPIRequest
{
    protected string $baseUrl = 'https://api.merchant001.io';

    /**
     * Конструктор
     */
    public function __construct(array $config = [])
    {
        $this->initialize($config);
    }

    /**
     * Баланс кошелька
     *
     * @param  null  $currency
     * @return mixed
     *
     * @throws Exception
     */
    public function getBalance($currency = null): float
    {
        $response = $this->apiRequest('get', '/v1/transaction/merchant/balance');

        return Arr::first($response['amount']);
    }

    /**
     * Баланс кошелька
     *
     * @param  null  $order_id
     * @return mixed
     *
     * @throws Exception
     */
    public function findTransaction($order_id = null)
    {
        return $this->apiRequest('get', '/v1/transaction/merchant/'.$order_id);
    }

    /**
     * Проверка поступлений
     */
    public function checkPayment($options = [])
    {
        // Получаем детали транзакции по адресу с сервера
        $tx_refs = $this->findTransaction($options['id_from_merchant']);

        // Перед обработкой проверяем, существует ли вообще транзакция по адресу
        $validate_tx = (! empty($tx_refs) and (int)$tx_refs['transaction']['invoiceId'] == $this->getOrderData()->id);

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
     * @return array
     * @throws Exception
     */
    public function sendToWithdrawal(array $options = []): array
    {
        $this->typeLog = 'pay';
        $merchantData = $this->getPayGateway();
        $method_pay = ($merchantData->ext_options['method_pay'] ?? 'all');

        $sendRequest = $this->apiRequest('post', '/v1/withdraw/merchant', [
            'outcomeAddress' => Str::upper($options['score']),
            'balanceAmount' => [
                'currency' => 'USDT'
            ],
            'withdrawAmount' => [
                'amount' => (float)$options['amount'],
                'currency' => (string)$options['code'],
            ],

            'method' => $method_pay,
            'comment' => (string)$options['comment'],
            'invoiceId' => (string)$this->getOrderData()->id
        ]);


        if($sendRequest['status'] == 'FAILED') {
            throw new \Exception(json_encode([
                'status' => $sendRequest['status'],
                'message' => 'Выплата не прошла'
            ]));
        }

        return [
            'status' => in_array($sendRequest['status'], ['CREATED', 'IN_PROGRESS']) ? 0 : 1,
            'withdrawal_id' => $sendRequest['id'],
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
        $tx = $this->findTransaction($transaction_id);
        return [
            'is_canceled' => in_array($tx['status'], ['FAILED', 'EXPIRED', 'CANCELED']),
            'is_pending' => in_array($tx['status'], ['PAID', 'IN_PROGRESS', 'PENDING', 'CREATED']),
            'is_successful' => in_array($tx['status'], ['CONFIRMED'])
        ];
    }

    /**
     * Вызов для получения данных из ADGroup
     *
     * @return mixed
     *
     * @throws Exception
     */
    private function apiRequest(string $method, string $path, array $options = [])
    {
        $httpRequest = Http::baseUrl($this->baseUrl)
            ->withToken($this->getParameter('api_token'));

        $response = $httpRequest->{$method}($path, $options)->json();

        if(isset($response['code']) and isset($response['message'])) {
            throw new \Exception(json_encode($response, JSON_UNESCAPED_UNICODE));
        }
        return $response;
    }
}
