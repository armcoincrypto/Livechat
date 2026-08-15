<?php

namespace iEXPackages\Payment\Gateways\WhiteBitCrypto;

use App\Models\Task;
use App\Models\TaskSingleLogConfirm;
use Exception;
use iEXPackages\Payment\Engines\AbstractAPIRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class APIRequest extends AbstractAPIRequest
{
    protected string $baseUrl = 'https://whitebit.com';

    /**
     * Конструктор
     */
    public function __construct(array $parameters = [],
    ) {
        $this->initialize($parameters);
    }

    /**
     * Детали транзакции
     *
     * @throws Exception
     */
    public function findTransaction(string $currency, string $address): array
    {
        $response = $this->apiRequest('/api/v4/main-account/history', [
            'transactionMethod' => '1',
            'ticker' => $currency,
            'address' => $address,
            'limit' => 1,
            'offset' => 0,
        ]);

        if(isset($response['records']) and empty($response['records'])) {
            throw new \Exception('Транзакций не найдено');
        }

        if (isset($response['records'])) {
            return Arr::first($response['records']);
        }

        throw new \Exception('Данные не найдены');
    }

    /**
     * Детали транзакции
     *
     * @throws ConnectionException
     */
    public function findWithdrawsTransaction($order_id, $ticker): array
    {
        $response = $this->apiRequest('/api/v4/main-account/history', [
            'transactionMethod' => '2',
            'ticker' => $ticker,
            'uniqueId' => (string) $order_id,
            'limit' => 1,
            'offset' => 0,
        ]);

        if (isset($response['records']) and !empty($response['records'])) {
            return Arr::first($response['records']);
        }

        return [];
    }

    /**
     * Отправка средств
     *
     * @throws Exception
     */
    public function transfer(array $options = []): mixed
    {
        $this->typeLog = 'pay';
        $params = [
            'ticker' => Str::upper($options['currency']),
            'amount' => (string) $options['amount'],
            'address' => (string) $options['address'],
            'uniqueId' => (string) $options['order_id'],
        ];

        // При найден дополнительный Tag который необходим для
        // выплаты конкретному клиенту
        if (isset($options['memo']) and ! empty($options['memo'])) {
            $params['memo'] = (string) $options['memo'];
        }

        // Добавляем к массиву есть, если он необходим для выплаты
        if (isset($options['network'])) {
            $params['network'] = (string) $options['network'];
        }

        return $this->apiRequest('/api/v4/main-account/withdraw-pay', $params);
    }


    /**
     * Проверка поступлений
     */
    public function checkPayment($options = [])
    {
        $total_data = explode('__', $options['id_from_merchant']);
        $address_id = $total_data[0];
        if(isset($total_data[1]) and !empty($total_data[1])) {
            $address_id = $total_data[1];
        }

        $asset_id = Str::upper($options['code']);

        // Получаем детали транзакции по адресу с сервера
        $tx_refs = $this->findTransaction($asset_id, $address_id);

        if (empty($tx_refs)) {
            throw new \Exception('Транзакция не найдена');
        }

        if (Str::lower($tx_refs['method']) != 1) {
            throw new \Exception('Ошибка платежа');
        }

        $response = new CheckPayment($tx_refs);

        // Получаем данные по подтверждениям
        if (isset($tx_refs['confirmations']) and is_array($tx_refs['confirmations']) and isset($tx_refs['requireConfirmations'])) {
            TaskSingleLogConfirm::updateOrCreate([
                'id_task' => $options['order_id'],
            ], [
                'id_task' => $options['order_id'],
                'needed_confirm' => (int)$tx_refs['confirmations']['required'],
                'received_confirm' => (int)$tx_refs['confirmations']['actual'] ?? 0,
            ]);
        }

        if (isset($tx_refs['confirmations']) and $tx_refs['confirmations']['actual'] < $tx_refs['confirmations']['required'])
        {
            throw new \Exception(
                sprintf('Необходимо %s/%s подтверждений, для выполнения заявки.', $tx_refs['confirmations']['actual'], $tx_refs['confirmations']['required']),
            );
        }


        return $response;
    }

    /**
     * Выплата на счет клиента
     *
     * @param Task $task
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function sendToWithdrawal(array $options = []): array
    {
        $this->typeLog = 'pay';
        $task = $this->getOrderData();


        $params = [
            'address' => $options['score'],
            'amount' => (float)$options['amount'],
            'currency' => $options['code'],
            'order_id' => $task->id,
        ];

        if (isset($options['buy_additional_fields']['outcome_unk'])) {
            $params['memo'] = $options['buy_additional_fields']['outcome_unk'];
        }

        $network_code = $options['network_code'];
        if (! empty($network_code)) {
            $params['network'] = $network_code;
        }

        $response = $this->transfer($params);

        return [
            'status' => 0,
            'is_waiting_tx_hash' => true,
            'withdrawal_id' => $this->getOrderData()->id.'__'. $options['code'],
            'response' => $response
        ];
    }


    /**
     * Получаем Tx ID
     *
     * @param string $id
     * @return string
     */
    public function getTransactionHash(string $id): string
    {
        $explode = explode('__', $id);


        $transaction = $this->findWithdrawsTransaction($explode[0], $explode[1]);

        if (isset($transaction['transactionHash']) and !empty($transaction['transactionHash'])) {
            return $transaction['transactionHash'];
        }

        return '';
    }


    /**
     * Запросы К API
     *
     * @throws ConnectionException
     * @throws Exception
     */
    public function apiRequest(string $url, array $params = []): mixed
    {
        // Запрос на получение текущего времени на сервере WhiteBit
        $getNonce = Http::get('https://whitebit.com/api/v4/public/time')->json();
        // Преобразовываем время в миллисекунды для отправки на сервер WhiteBit
        $nonceToValueOf = Carbon::parse($getNonce['time'])->valueOf();

        $data = array_merge($params, [
            'request' => $url,
            'nonce' => $nonceToValueOf,
            'nonceWindow' => true,
        ]);

        $dataJsonStr = json_encode($data, JSON_UNESCAPED_SLASHES);
        $payload = base64_encode($dataJsonStr);
        $signature = hash_hmac('sha512', $payload, $this->getParameter('secret_key'));

        $headers = [
            'X-TXC-APIKEY' => $this->getParameter('public_key'),
            'X-TXC-PAYLOAD' => $payload,
            'X-TXC-SIGNATURE' => $signature,
        ];

        $response = Http::withHeaders($headers)
            ->asJson()->post($this->baseUrl.$url, $data)->json();


        // Записываем в лог
        $this->createLogRequest([
            'url' => $this->baseUrl. '/'. $url,
            'headers' => json_encode($headers),
            'content' => json_encode($data),
            'response' => json_encode($response),
        ]);;


        // Если есть ошибки
        if (isset($response['errors'])) {
            Log::debug(json_encode($response['errors']));
            throw new Exception(json_encode($response));
        }

        return $response;
    }
}
