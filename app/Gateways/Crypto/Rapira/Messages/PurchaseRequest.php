<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Rapira\Messages;

use App\Gateways\Crypto\Rapira\Services\JwtTokenService;
use Exception;
use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class PurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        $this->validate('amount');

        return [
            'currency' => trim(Str::lower($this->getMerchantNetworkCode())),
            'amount'   => $this->getAmount(),
            'nonce'    => request()->httpHost() . '_' . $this->getTransactionId(),
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $response = $this->sendRequest('post', '/open/deposit_address', $data, 'asForm');

        return $this->response = new PurchaseResponse(
            request: $this,
            data: $response,
            query: $data
        );
    }
}
