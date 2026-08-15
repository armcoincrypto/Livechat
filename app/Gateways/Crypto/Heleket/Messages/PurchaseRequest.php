<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Heleket\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;

/**
 * Создание платежа (incoming).
 */
final class PurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        // Пример: минимальная валидация
        $this->validate('amount', 'currency');

        $merchant = $this->getMerchant();
        $typeMethod = (int) (($merchant?->ext_options['type_method_receiving_pay'] ?? 0) ?? 0);


        $options = [
            'currency' => (string) $this->getCurrency(),
            'amount' => (string) $this->getAmount(),
            'order_id' => (string) $this->getTransactionId(),
            'lifetime' => (int) iEXSetting('max_time_task', 1020),
            'is_payment_multiple' => false,
        ];

//        if($type_method_receiving_pay == 0) {
//            $options['url_success'] = $this->getReturnUrl();
//            $options['url_callback'] = $this->getNotifyUrl();
//        }
        $network_code = $this->getMerchantNetworkCode(false);

        // Текущий код валюты (в сети)
        if (! empty($network_code)) {
            $options['network'] = $network_code;
        }

        return $options;
    }

    protected function sendData(array $data): ResponseInterface
    {
        $httpResponse = $this->sendRequest('post', '/v1/payment', $data, 'asJson');

        // Выбор response по настройке
        $merchant = $this->getMerchant();
        $typeMethod = (int) (($merchant?->ext_options['type_method_receiving_pay'] ?? 0) ?? 0);


        if ($typeMethod === 1) {
            return $this->response = new PurchaseResponse(
                request: $this,
                data:    is_array($httpResponse) ? $httpResponse : [],
                query:   $data
            );
        }

        return $this->response = new PurchaseRedirectResponse(
            request: $this,
            data:    is_array($httpResponse) ? $httpResponse : [],
            query:   $data
        );
    }
}
