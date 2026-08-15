<?php
declare(strict_types=1);

namespace iEXPackages\Payment\Gateways\PSPWare\Message;

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
            'api_key', 'amount', 'currency', 'transactionId'
        );

        $merchantData = $this->getMerchantGateway();
        $typePay = ($merchantData->ext_options['type_pay'] ?? 'card');
        $paymentMethod = !(($typePay === 'card'));


        $formattedSum = $this->formatSumForSignature((float) $this->getAmount());
        $stringToSign = "{$formattedSum}:{$this->getApiKey()}";
        $signature = hash('sha256', mb_convert_encoding($stringToSign, 'UTF-8'));


        return [
            'sum' => $formattedSum,
            'currency' => $this->getCurrency(),
            'orderType' => 'PAY-IN',
            'merchant_id' => $this->getMerchantId(),
            'signature' => $signature,
            'isSbp' => $paymentMethod
        ];
    }

    private function formatSumForSignature(float $sum): string
    {
        // Используем bcmod для избежания ошибок сравнения float
        if (bcmod(sprintf('%.10f', $sum), '1', 10) == 0) {
            // Если число целое, оставляем один знак после точки
            return number_format($sum, 1, '.', '');
        }

        // В остальных случаях — два знака после запятой
        return number_format($sum, 2, '.', '');
    }

    /**
     * Отправить запрос с указанными данными
     *
     * @throws \Exception
     */
    public function sendData(array $data): ResponseInterface
    {
        $path = '/payphoria/merchant/api/v1/orders';

        // Первый POST-запрос — создание заказа
        $response = Http::baseUrl($this->baseUrl)
            ->asJson()
            ->post($path, $data);

        $httResponse = $response->json();

        // Логируем результат создания заказа
        add_merchant_log_event([
            'provider'   => 'PSPWare',
            'id_order'   => $this->getTransactionId(),
            'ip_address' => $this->getOrderData()->ip,
            'url'        => $this->baseUrl . '/' . $path,
            'headers'    => null,
            'content'    => json_encode($data),
            'response'   => json_encode($httResponse)
        ]);

        return $this->response = new PurchaseResponse($this, $httResponse);
    }
}

