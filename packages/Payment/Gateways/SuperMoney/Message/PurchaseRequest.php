<?php
declare(strict_types=1);

namespace iEXPackages\Payment\Gateways\SuperMoney\Message;

use iEXPackages\Payment\Engines\Message\ResponseInterface;
use iEXPackages\Payment\Exception\InvalidRequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PurchaseRequest extends AbstractRequest
{
    /**
     * Получить массив необработанных данных для этого сообщения.
     * Формат этого варьируется от шлюза к шлюзу,
     * но обычно это либо ассоциативный массив, либо SimpleXMLElement.
     *
     * @throws InvalidRequestException
     */
    public function getData(): array
    {
        $this->validate(
            'api_domain', 'amount', 'currency', 'transactionId'
        );

        // Итоговая сумма
        $amount = $this->getAmount();
        $order_data = $this->getOrderData();


        // Получаем доп. поля Отдаю
        $sellAdditionalFields = $this->getSellAdditionalFields();
        $sender_fullname = ($sellAdditionalFields['sender_fullname'] ?? 'Default Name');


        return [
            'amount' => (float)$amount,
//            'clientDetails' => (object)[
//                'ip' => $order_data->ip,
//                'lastName' => $sender_fullname,
//                'email' => $order_data->user->email,
//                'clientId' => $order_data->user->id
//            ],
            'currency'  => $this->getCurrency(),
            'extId'  => (string)$this->getTransactionId()
        ];
    }

    /**
     * Отправить запрос с указанными данными
     *
     * @throws \Exception
     */
    public function sendData(array $data): ResponseInterface
    {
        // Тип выплаты
        $merchantData = $this->getMerchantGateway();

        // Тип выплаты (нормализуем к нижнему регистру и страхуемся дефолтом)
        $type_pay = strtolower((string)($merchantData->ext_options['type_pay'] ?? 'card'));
        switch ($type_pay) {
            case 'sbp':
                $path = '/v2/merchant/transactions/sbp';
                break;
            case 'card':
            default:
                // По умолчанию работаем как с картой
                $type_pay = 'card';
                $path = '/v2/merchant/transactions';
                break;
        }

        // JSON тела запроса
        $requestJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Расчёт подписи
        $signature = $this->calculateSignature($path, $requestJson, $this->getApiSignToken());

        $headers = [
            'X-Signature' => $signature
        ];

        $httResponse = Http::baseUrl($this->getApiDomain())
            ->withToken($this->getApiAuthToken())
            ->withHeaders($headers)
            ->asJson()
            ->acceptJson()
            ->post($path, $data)
            ->json();


        // Записываем результаты в лог
        add_merchant_log_event([
            'provider' => 'supermoney',
            'id_order' => $this->getTransactionId(),
            'ip_address' => $this->getOrderData()->ip,
            'url' => $this->getApiDomain() . '/v2/merchant/transactions',
            'headers' => json_encode($headers, JSON_UNESCAPED_UNICODE),
            'content' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'response' => json_encode($httResponse, JSON_UNESCAPED_UNICODE),
        ]);

        if (!isset($httResponse['id'])) {
            Log::debug('SuperMoney: '. json_encode($httResponse, JSON_UNESCAPED_UNICODE));
        }

        return $this->response = new PurchaseResponse(
            $this,
            $httResponse
        );
    }

    private function calculateSignature($url, $requestJson, $secret) {
        $signatureString = $requestJson . $url;
        return hash_hmac('sha256', $signatureString, $secret);
    }
}
