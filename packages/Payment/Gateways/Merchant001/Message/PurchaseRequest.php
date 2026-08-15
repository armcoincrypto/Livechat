<?php

namespace iEXPackages\Payment\Gateways\Merchant001\Message;

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
            'api_token', 'amount', 'currency', 'transactionId'
        );

        $merchantData = $this->getMerchantGateway();
        $method_pay = ($merchantData->ext_options['method_pay'] ?? 0);

        $codes_options = json_decode(file_get_contents(storage_path('/app/gateway-options/merchant001_codes.json')), true);
        $code_find = collect($codes_options)->first(function ($query) use ($method_pay) {
            return $query['id'] == $method_pay;
        });

        return [
            'isPartnerFee' => true,
            'pricing' => [
                'local' => [
                    'amount' => (float) $this->getAmount(),
                    'currency' => $this->getCurrency(),
                ],
            ],
            'selectedProvider' => [
                'method' => $code_find['id'],
            ],

            'invoiceId' => $this->getTransactionId(),
        ];
    }

    /**
     * Отправить запрос с указанными данными
     *
     * @throws \Exception
     */
    public function sendData(array $data): ResponseInterface
    {
        $merchantData = $this->getMerchantGateway();
        $type_method_receiving_pay = ($merchantData->ext_options['type_method_receiving_pay'] ?? 0);

        $httpRequest = Http::baseUrl($this->endpointUrl)
            ->withToken($this->getApiToken());

        $httpResponse = $httpRequest->post('/v2/transaction/merchant', $data)->json();

        // Записываем результаты в лог
        add_merchant_log_event([
            'provider' => 'merchant001',
            'id_order' => $this->getTransactionId(),
            'ip_address' => $this->getOrderData()->ip,
            'url' => $this->endpointUrl.'/v1/transaction/merchant',
            'headers' => null,
            'content' => json_encode($data),
            'response' => json_encode($httpResponse),
        ]);


        // Если проблем нет
        if (isset($httpResponse['transaction']['id'])) {
            // Тип: Переадресация на (платежную форму)
            if ($type_method_receiving_pay == 0) {
                return $this->response = new PurchaseResponse($this, $httpResponse);
            }

            $httpIdResponse = $httpRequest->get('/v1/transaction/merchant/requisite/'.$httpResponse['transaction']['id'])->json();
            return $this->response = new PurchaseH2HResponse($this, $httpIdResponse);
        }
        throw new \Exception(json_encode($httpResponse));
    }
}
