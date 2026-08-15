<?php
declare(strict_types=1);

namespace iEXPackages\Payment\Gateways\Payscrow\Message;

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
            'api_domain', 'api_key', 'api_secret', 'amount', 'currency', 'transactionId'
        );
        $merchantData = $this->getMerchantGateway();

        // Итоговая сумма
        $amount = (float) $this->getAmount();
        $order_data = $this->getOrderData();

        $method_pay = ($merchantData->ext_options['method_pay'] ?? 0);
        $bank_name = ($merchantData->ext_options['bank_name'] ?? 0);

        // Получаем доп. поля Отдаю
        $sellAdditionalFields = $this->getSellAdditionalFields();
        $sender_fullname = (isset($sellAdditionalFields['sender_fullname']) ? $sellAdditionalFields['sender_fullname'] : 'Default Name');


        return [
            'basePaymentMethodId' => $bank_name,
            'externalOrderId' => (string) $this->getTransactionId(),
            'orderSide' => 'Buy',
            'targetAmount' => (string) $amount,
            'feeType' => 'ChargeMerchant',
            'currencyType' => 'Fiat',
            'currency' => $method_pay,
            'customerName' => $sender_fullname
        ];
    }

    /**
     * Отправить запрос с указанными данными
     *
     * @throws \Exception
     */
    public function sendData(array $data): ResponseInterface
    {
        $path = '/api/v1/Orders/Create';

        if (! empty($data)) {
            $digestInput = implode('/', [$path, $this->getApiSecret(), $this->getApiKey()])."\r\n".json_encode($data);
        } else {
            $digestInput = implode('/', [$path, $this->getApiSecret(), $this->getApiKey()]);
        }
        $digest = hash('sha256', $digestInput);

        $httResponse = Http::baseUrl($this->getApiDomain())->asJson()->withHeaders([
            'X-API-Key' => $this->getApiKey(),
            'X-API-Sign' => $digest,
        ])->post($path, $data)->json();

        // Записываем результаты в лог
        add_merchant_log_event([
            'provider' => 'payscrow',
            'id_order' => $this->getTransactionId(),
            'ip_address' => $this->getOrderData()->ip,
            'url' => $this->getApiDomain().$path,
            'headers' => null,
            'content' => json_encode($data),
            'response' => json_encode($httResponse),
        ]);

        return $this->response = new PurchaseResponse($this, $httResponse);
    }
}
