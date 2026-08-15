<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\NicePay\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;

/**
 * Создание платежа (incoming).
 */
final class PurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        $this->validate('amount', 'currency');

        $task = $this->getTask();
        if (!$task) {
            throw new \InvalidArgumentException('NicePay: Task is required (withTask).');
        }

        // Merchant credentials (must be configured in gateway merchant fields)
        $merchantId = (string) ($this->getIdMerchantKey() ?? '');
        $secretKey  = (string) ($this->getSecretKey() ?? '');

        if ($merchantId === '' || $secretKey === '') {
            throw new \InvalidArgumentException('NicePay: merchant_id/secret are not configured.');
        }

        $orderId   = (string) ($this->getTransactionId() ?? (string) $task->id);
        $currency  = strtoupper((string) $this->getCurrency());
        $amountRaw = (string) $this->getAmount();

        $amountMinor = $this->toMinorUnits($amountRaw);

        $customer = (string) ($task->user?->email ?? $task->email ?? '');
        if ($customer === '') {
            $customer = 'order_' . $orderId;
        }

        $description = (string) ($this->getDescription('#' . $orderId) ?? ('#' . $orderId));
        if (mb_strlen($description) > 150) {
            $description = mb_substr($description, 0, 150);
        }

        $method = trim((string) $this->merchantString('method_pay', ''));

        $successUrl = $this->getReturnUrl();
        $failUrl    = $this->getCancelUrl();

        $payload = [
            'merchant_id'  => $merchantId,
            'secret'       => $secretKey,
            'order_id'     => $orderId,
            'customer'     => $customer,
            'amount'       => $amountMinor,
            'currency'     => $currency,
            'description'  => $description,
        ];

        if ($method !== '' && $method !== 'auto') {
            $payload['method'] = $method;
        }

        if ($successUrl !== '') {
            $payload['success_url'] = $successUrl;
        }

        if ($failUrl !== '') {
            $payload['fail_url'] = $failUrl;
        }

        return $payload;
    }

    /**
     * Converts a decimal amount string to minor units (cents/kopeks).
     * Example: "125.28" => 12528
     */
    private function toMinorUnits(string $amount): int
    {
        $s = trim($amount);
        $s = str_replace(["\u{00A0}", ' '], '', $s);
        $s = str_replace(',', '.', $s);

        if ($s === '' || !is_numeric($s)) {
            throw new \InvalidArgumentException('NicePay: invalid amount format.');
        }

        // Using float is acceptable here because we only need 2 decimals for minor units.
        $value = (float) $s;
        $minor = (int) round($value * 100);

        if ($minor <= 0) {
            throw new \InvalidArgumentException('NicePay: amount must be greater than 0.');
        }

        return $minor;
    }

    protected function sendData(array $data): ResponseInterface
    {
        // Create Payment API
        $response = $this->sendRequest('post', '/payment', $data, 'asJson');

        $status = is_array($response) ? (string)($response['status'] ?? '') : '';
        $dataNode = is_array($response) && isset($response['data']) && is_array($response['data'])
            ? $response['data']
            : [];

        if ($status !== 'success') {
            $message = is_array($dataNode) ? (string)($dataNode['message'] ?? '') : '';
            if ($message === '') {
                $message = 'NicePay: payment creation failed.';
            }

            throw new \RuntimeException($message);
        }

        return $this->response = new PurchaseResponse(
            $this,
            [
                'payment_id' => (string)($dataNode['payment_id'] ?? ''),
                'url'        => (string)($dataNode['link'] ?? ''),
                'expired'    => (string)($dataNode['expired'] ?? ''),
            ],
            $data
        );
    }
}
