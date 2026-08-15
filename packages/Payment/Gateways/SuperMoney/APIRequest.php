<?php

namespace iEXPackages\Payment\Gateways\SuperMoney;

use App\Models\Task;
use Exception;
use iEXPackages\Payment\Engines\AbstractAPIRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
     * Детали транзакции
     *
     * @throws \Exception
     */
    public function findTransaction(int $id): array
    {
        $this->typeLog = 'merchant';
        return $this->request('get', '/v2/merchant/transactions/' . $id);
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
        // Данные по транзакции
        $order = $this->getOrderData();
        $merchantData = $this->getPayGateway();

        // Нормализуем тип оплаты: card | sbp (учитываем возможную опечатку spb)
        $method_pay = strtolower((string)($merchantData->ext_options['method_pay'] ?? 'card'));
        if ($method_pay === 'spb') {
            $method_pay = 'sbp';
        }

        // Собираем поля out в удобный словарь: field_key => запись
        $fieldsOut = collect($order->tasks_fields_out ?? [])->keyBy('field_key');
        $bankName = data_get($fieldsOut, 'outcome_bank_name.field_value')
            ?? data_get($fieldsOut, 'outcome_bank.field_value');

        $params = [
            'extId'    => (string)('OUT_' . ($order->id ?? '0')),
            'currency' => $options['code'],
            'amount'   => (float)$options['amount'],
        ];

        if ($method_pay === 'card') {
            // Выплата на карту
            $params['cardNumber'] = $options['score'];
        } else { // sbp
            // Выплата по СБП: телефон + банк
            $params['phoneNumber'] = $options['score'];
            if (!empty($bankName)) {
                $params['bankName'] = $bankName;
            }
        }

        $response = $this->request('post', '/v2/merchant/transactions/withdrawal', $params);

        return [
            'status' => 0,
            'is_waiting_tx_hash' => true,
            'withdrawal_id' => $response['id'] ?? null,
            'response' => $response
        ];
    }

    /**
     * Проверка поступлений
     */
    public function checkPayment($options = [])
    {

        // Получаем детали транзакции по адресу с сервера
        $tx_refs = $this->findTransaction(
            $options['id_from_merchant']
        );

        // Проверяем структуру ответа
        if (
            !isset($tx_refs['id'])
        ) {
            return [
                'status' => 1,
                'message' => 'Транзакция не найдена',
            ];
        }

        if (empty($tx_refs)) {
            return [
                'status' => 1,
                'message' => 'Транзакция не найдена',
            ];
        }

        return new CheckPayment($tx_refs);
    }

    /**
     * Запросы WestWallet
     *
     * @throws \Exception
     */
    public function request(string $method, $path, array $data = []): array
    {
        $method = strtolower($method);

        if ($method === 'get' && !empty($data)) {
            $queryString = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
            $path .= (str_contains($path, '?') ? '&' : '?') . $queryString;
        }


        // Формируем тело для запроса и для подписи
        // По требованиям HM: подпись = (тело запроса ИЛИ пустая строка) + (path + ?query)
        $bodyForRequest = ($method === 'get') ? [] : $data;
        $requestJson = ($method === 'get') ? '' : json_encode($bodyForRequest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $signSecret = $this->getParameter('api_sign_token');

        // Расчёт подписи
        $signature = $this->calculateSignature($path, $requestJson, $signSecret);

        $headers = [
            'X-Signature'   => $signature,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];

        try {
            $request = Http::baseUrl($this->getParameter('api_domain'))
                ->withToken($this->getParameter('api_auth_token'))
                ->withHeaders($headers)
                ->asJson()
                ->acceptJson();

            if ($method === 'post') {
                $request = $request->post($path, $bodyForRequest);
            } else { // GET и прочие без тела
                $request = $request->get($path);
            }

            $httpResponse = $request->json();
        }catch (\Throwable $exception) {

            // Записываем в лог
            $this->createLogRequest([
                'url' => $this->getParameter('api_domain').$path,
                'headers' => json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'content' => json_encode($data),
                'response' => json_encode([
                    'message' => $exception->getMessage()
                ]),
            ]);

            throw new \Exception($exception->getMessage());
        }

        // Записываем в лог
        $this->createLogRequest([
            'url' => $this->getParameter('api_domain').$path,
            'headers' => json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'content' => json_encode($data),
            'response' => json_encode($httpResponse),
        ]);

        if (empty($httpResponse)) {
            throw new \Exception(json_encode($httpResponse));
        }

        return $httpResponse;
    }

    private function calculateSignature($urlOrPath, $requestJson, $secret) {
        // Допускаем как полный URL, так и относительный путь
        $parts = parse_url($urlOrPath);
        $path  = $parts['path']  ?? '';
        $query = isset($parts['query']) && $parts['query'] !== '' ? ('?' . $parts['query']) : '';

        // По спецификации: bodyJson + path + (optional) ?query
        $signatureString = (string)$requestJson . $path . $query;

        return hash_hmac('sha256', $signatureString, (string)$secret);
    }
}
