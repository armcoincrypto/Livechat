<?php

declare(strict_types=1);

namespace iEXPackages\Payment\Gateways\WhiteBitCrypto\Message;

use iEXPackages\Payment\Engines\Message\ResponseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AddressRequest extends AbstractRequest
{
    /**
     * Получить массив необработанных данных для этого сообщения.
     * Формат этого варьируется от шлюза к шлюзу,
     * но обычно это либо ассоциативный массив, либо SimpleXMLElement.
     *
     * @throws
     */
    public function getData(): array
    {
        $this->validate('currency', 'transactionId');

        return [];
    }

    /**
     * Отправить запрос с указанными данными
     *
     * @throws ConnectionException
     */
    public function sendData(array $data): ResponseInterface
    {
        // Путь для генерации нового адреса для транзакции
        $request_url = '/api/v4/main-account/create-new-address';

        // Запрос на получение текущего времени на сервере WhiteBit
        $getNonce = Http::get('https://whitebit.com/api/v4/public/time')->json();
        // Преобразовываем время в миллисекунды для отправки на сервер WhiteBit
        $nonceToValueOf = Carbon::parse($getNonce['time'])->valueOf();

        $options = [
            'ticker' => $this->getCurrency(),
            'request' => $request_url,
            'nonce' => $nonceToValueOf,
            'nonceWindow' => true,
        ];

        $network_code = $this->getMerchantNetworkCode(false);
        if (! empty($network_code)) {
            $options['network'] = $network_code;
        }

        $dataJsonStr = json_encode($options, JSON_UNESCAPED_SLASHES);
        $payload = base64_encode($dataJsonStr);
        $signature = hash_hmac('sha512', $payload, $this->getSecretKey());

        $headers = [
            'Content-type' => 'application/json',
            'X-TXC-APIKEY' => $this->getPublicKey(),
            'X-TXC-PAYLOAD' => $payload,
            'X-TXC-SIGNATURE' => $signature,
        ];

        $httpResponse = Http::baseUrl($this->endpointUrl)
            ->asJson()->withHeaders($headers)->post($request_url, $options)->json();

        // Записываем результаты в лог
        add_merchant_log_event([
            'provider' => 'WhiteBitCrypto',
            'id_order' => $this->getTransactionId(),
            'ip_address' => $this->getOrderData()->ip,
            'url' => $this->endpointUrl.'/api/v4/main-account/create-new-address',
            'headers' => json_encode($headers),
            'content' => json_encode($options),
            'response' => json_encode($httpResponse),
        ]);

        return $this->response = new AddressResponse($this, $httpResponse);
    }
}
