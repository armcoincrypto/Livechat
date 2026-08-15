<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Rapira\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Проверка поступления средств через API Rapira (polling).
 *
 * Входные параметры предполагаются такими:
 *  - externalId  — ID депозита/платежа в Rapira (из PurchaseResponse::getExternalId())
 *  - transactionId (опционально) — твой внутренний ID (для логов/связки с Task)
 */
final class FetchPaymentRequest extends AbstractRequest
{
    /**
     * Сборка payload для Rapira API.
     *
     * Мы ожидаем, что:
     *  - externalId передаётся снаружи, или
     *  - его можно взять из параметров Request.
     */
    public function getData(): array
    {

        // обязательный параметр: externalId (ID депозита в Rapira)
        $externalId = $this->getParameter('externalId');
        if ($externalId === null || $externalId === '') {
            // допустимо вместо externalId использовать id
            $externalId = $this->getParameter('id');
        }
        // минимальная проверка
        if ($externalId === null || $externalId === '') {
            // можно сделать InvalidRequestException, если он у тебя есть
            throw new \InvalidArgumentException('externalId (или id) обязателен для checkPayment');
        }

        return [
            // Rapira ожидает ID депозита:
            'id' => (string) $externalId,
            'currency' => $this->getCurrency(),
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $task = $this->getTask();
        $merchantData = $task->merchantTransactionData;


        $request = $this->findTransaction([
            'pageNo' => 0,
            'pageSize' => 100,
            'unit' => Str::lower($data['currency']),
            'address' => $data['id']
        ]);

        $httpResponse = collect($request)
            ->first(function ($tx)  use($merchantData) {
                if (!empty($merchantData->ext_data->memo_id)) {
                    return isset($tx['memo']) && (string)$tx['memo'] === (string)$merchantData->ext_data->memo_id;
                }
                // Если memo не требуется — ищем только по адресу
                return true;
            });


        return $this->response = new FetchPaymentResponse(
            request: $this,
            data: (array) $httpResponse,
            query: $data
        );
    }

    /**
     * Helper для выполнения запросов к Rapira API.
     *
     * @throws \Throwable
     */
    protected function findTransaction(array $data = []): array
    {
        $response = $this->sendRequest(
            method:        'POST',
            path:          '/open/deposit/records',
            data:          $data,
            format:        'asForm',
        );


        $trans = [];
        if (! isset($response['error']) and $response['code'] == 0) {
            $trans = $response['data']['content'];
        }

        return $trans;
    }
}
