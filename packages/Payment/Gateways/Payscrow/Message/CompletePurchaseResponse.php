<?php

namespace iEXPackages\Payment\Gateways\Payscrow\Message;

use App\Models\MerchantTransactionId;
use App\Models\Task;
use iEXPackages\Payment\Engines\Message\AbstractResponse;
use iEXPackages\Payment\Engines\Message\RequestInterface;
use iEXPackages\Payment\Exception\InvalidResponseException;
use Illuminate\Support\Facades\Http;

class CompletePurchaseResponse extends AbstractResponse
{
    /**
     * @var CompletePurchaseRequest|RequestInterface
     */
    protected RequestInterface $request;

    /**
     * @return Task
     */
    protected $transaction;

    protected $billId;

    /**
     * @throws InvalidResponseException
     */
    public function __construct(RequestInterface $request, $data)
    {
        parent::__construct($request, $data);

        if (isset($this->data['clientOrderID'])) {
            $merchant_id = MerchantTransactionId::where('id_task', $this->data['clientOrderID'])->first();
            $this->billId = $merchant_id->transaction_id;
            $this->transaction = $merchant_id->tasks;

            $array = [
                'clientOrderID' => (string) $this->data['clientOrderID'],
            ];

            $payload = json_encode($array);
            $hash_hmac = hash_hmac('sha512', $payload, $this->request->getApiSecret());

            $httpResponse = Http::baseUrl('https://api.aifory.io')->withHeaders([
                'API-Key' => $this->request->getApiKey(),
                'Content-Type' => 'application/json',
                'Signature' => $hash_hmac,
                'user-agent' => $this->request->getUserAgent(),
            ])->post('/payin/details', $array)->json();

            $this->data = $httpResponse;
        }
    }

    /**
     * Если транзакция успешна
     */
    public function isSuccessful(): bool
    {
        return $this->data['statusID'] == 2;
    }

    /**
     * Если транзакция отменена
     */
    public function isPending(): bool
    {
        return $this->data['statusID'] == 1;
    }

    /**
     * Если транзакция отменена
     */
    public function isCancelled(): bool
    {
        return $this->data['statusID'] != 3;
    }

    /**
     * ID заявки от платежной системы
     *
     * @return string|int
     */
    public function getTransferId()
    {
        return $this->data['ID'];
    }

    /**
     * ID заявки (с сайта)
     */
    public function getTransactionId(): mixed
    {
        return $this->data['clientOrderID'];
    }

    /**
     * Получение суммы
     *
     * @return string | float
     */
    public function getAmount()
    {
        return $this->data['successPaid'];
    }

    /**
     * Получаем код валюты
     */
    public function getCurrency(): string
    {
        $currencies_options = json_decode(file_get_contents(storage_path('/app/gateway-options/aifory_currencies.json')), true);

        return collect($currencies_options)->first(function ($query) {
            return $query['ID'] == $this->data['currencyID'];
        })['currencyName'];
    }

    /**
     * Получаем номер счета отправителя
     */
    public function getFromAccount(): string
    {
        return '';
    }
}
