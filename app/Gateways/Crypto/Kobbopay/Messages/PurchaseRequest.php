<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Kobbopay\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

final class PurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        $this->validate('transactionId');

        return [
            'token' => (string) $this->getMerchantNetworkCode(),
            'client_transaction_id' => (string) $this->getTransactionId(),
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $maxAttempts = 3;
        $lastThrowable = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $this->sendRequest('post', '/api/crypto/invoice/create', $data, 'asJson');

                return $this->response = new PurchaseResponse(
                    $this,
                    is_array($response) ? $response : [],
                    $data
                );
            } catch (ConnectionException $e) {
                $lastThrowable = $e;
                Log::warning('Kobbopay invoice/create transient failure (connection)', [
                    'attempt'       => $attempt,
                    'max_attempts'  => $maxAttempts,
                    'task_id'       => $this->getTask()?->id,
                    'transactionId' => $this->getTransactionId(),
                ]);
                if ($attempt >= $maxAttempts) {
                    break;
                }
                usleep(200_000 * $attempt);
            } catch (RequestException $e) {
                $lastThrowable = $e;
                $httpStatus = (int) ($e->response?->status() ?? 0);
                $retryable = in_array($httpStatus, [429, 502, 503, 504], true);

                if (!$retryable) {
                    throw $e;
                }

                Log::warning('Kobbopay invoice/create transient failure (http)', [
                    'attempt'       => $attempt,
                    'max_attempts'  => $maxAttempts,
                    'http_status'   => $httpStatus,
                    'task_id'       => $this->getTask()?->id,
                    'transactionId' => $this->getTransactionId(),
                ]);

                if ($attempt >= $maxAttempts) {
                    break;
                }
                usleep(200_000 * $attempt);
            }
        }

        throw $lastThrowable ?? new \RuntimeException('Kobbopay invoice/create failed after retries.');
    }
}
