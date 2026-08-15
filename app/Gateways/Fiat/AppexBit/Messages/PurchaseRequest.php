<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\AppexBit\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;

/**
 * Создание платежа (incoming).
 */
final class PurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        // Пример: минимальная валидация
        // $this->validate('amount', 'currency');

        $merchantData = $this->getMerchant();
        $bank_name = ($merchantData->ext_options['bank_name'] ?? 0);
        return [
            'amountFiat' => $this->getAmount(),
            'fiatInfo' => (object)[
                'fiatCode' => $this->getCurrency(),
                'providerCode' => $bank_name ?: 'other',
            ],
            'tokenCode'  => 'USDT',
            'clientCardNumber' => $this->getTask()->from_shot ?? null,
            'externalId'  => (string)$this->getTransactionId(),
            'clientIp' => request()->ip(),
            'type'       => 0,
            'clientEmail' => $this->getTask()->email ?? null,
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $response = $this->sendRequest('post', '/trade/createOffer', $data);

        // гарантируем массив
        $httpResponse = is_array($response) ? $response : [];

        // старое поведение: отдаём первый offer, если есть
        $payload = $httpResponse['addedOffers'][0] ?? $httpResponse;

        return $this->response = new PurchaseResponse(
            $this,
            is_array($payload) ? $payload : $httpResponse,
            $data
        );
    }
}
