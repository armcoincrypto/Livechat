<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Pshb\Messages;

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
            throw new \InvalidArgumentException('PSHB: Task is required (withTask).');
        }

        $projectId = (string) ($this->getIdProject() ?? '');
        $secretKey = (string) ($this->getSecretKey() ?? '');

        if ($projectId === '' || $secretKey === '') {
            throw new \InvalidArgumentException('PSHB: project_id / secret_key not configured.');
        }

        $orderId  = (string) ($this->getTransactionId() ?? (string) $task->id);
        $currency = strtoupper((string) $this->getCurrency());
        $amount   = (string) $this->getAmount();

        $amountMinor = $this->toMinorUnits($amount);

        $description = (string) ($this->getDescription('#' . $orderId) ?? ('#' . $orderId));
        $method = trim((string) $this->merchantString('method_pay', ''));

        return [
            'project_id' => $projectId,
            'secret_key' => $secretKey,
            'order_id'   => $orderId,
            'currency'   => $currency,
            'amount'     => $amountMinor,
            'method'   => $method,
            'description' => $description,
        ];
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
        $task = $this->getTask();

        $payload = [
            'project_id' => $data['project_id'],
            'order_id'   => $data['order_id'],
            'return_url' => $this->getReturnUrl(). '?task_id='. $this->getTransactionId(),
            'customer'   => [
                'id'         => (string) ($task->user?->id ?? $task->id),
                'ip_address' => (string) request()->ip(),
                'email'      => (string) ($task->user?->email ?? $task->email ?? ''),
                'first_name' => (string) ($task->user?->name ?? ''),
                'last_name'  => '',
            ],
            'payment' => [
                'currency' => $data['currency'],
                'amount'   => $data['amount'],
            ],
            'payment_data' => [
                'method_type' => $data['method'] ?? 'sbp',
                'description' => $data['description'] ?? '',
            ],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('PSHB: JSON encode error.');
        }

        $signature = hash_hmac('sha256', $json, $data['secret_key']);



        $redirectUrl = rtrim($this->endpointStaticBaseUrl(), '/')
            . '/payment?body=' . rawurlencode($json)
            . '&signature=' . $signature;

        return $this->response = new PurchaseResponse(
            $this,
            [
                'redirect_url' => $redirectUrl,
            ],
            $payload
        );
    }
}
