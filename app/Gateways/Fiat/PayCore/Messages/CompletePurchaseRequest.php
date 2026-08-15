<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\PayCore\Messages;

use App\Gateways\Fiat\PayCore\Messages\FetchPaymentResponse;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use InvalidArgumentException;

/**
 * Обработка callback/IPN (completePurchase).
 *
 * Важно:
 * - Этот Request НЕ отправляет HTTP.
 * - Он принимает входящие данные (payload) от контроллера/роута.
 */
final class CompletePurchaseRequest extends AbstractRequest
{
    /**
     * Callback от PayCore содержит только идентификатор операции.
     *
     * Пример payload:
     *  {"id":"169351"}
     *
     * Мы не доверяем этому payload как финальному статусу, поэтому:
     *  1) валидируем наличие `id`
     *  2) строим hash = sha256(merchant_token + transaction_id)
     *  3) дальше делаем запрос в API PayCore и получаем полноценные данные транзакции
     */
    public function getData(): array
    {
        $this->validate('id');

        $payload = $this->getParameters();

        $transactionId = $payload['id'] ?? null;
        $transactionId = is_scalar($transactionId) ? trim((string) $transactionId) : '';

        if ($transactionId === '') {
            throw new InvalidArgumentException('PayCore callback: отсутствует корректный id транзакции.');
        }

        $this->setTransactionId($transactionId);

        $hash = hash('sha256', $this->getMerchantToken() . $transactionId);

        return [
            'transaction_id' => $transactionId,
            'hash'           => $hash,
        ];
    }


    /**
     * Получаем полные данные транзакции из PayCore по hash.
     *
     * Важно: этот Request формально является complete_purchase, но по сути
     * это безопасная схема "callback -> fetch".
     */
    protected function sendData(array $data): ResponseInterface
    {
        $hash = (string) ($data['hash'] ?? '');
        if ($hash === '') {
            throw new InvalidArgumentException('PayCore callback: hash не сформирован.');
        }

        $httpResponse = $this->sendRequest('get', "/api/v2/exchanger/{$hash}");

        if (empty($httpResponse) || !is_array($httpResponse)) {
            throw new InvalidArgumentException('PayCore: транзакция не найдена.');
        }

        return $this->response = new CompletePurchaseResponse(
            $this,
            is_array($httpResponse) ? $httpResponse : [],
            $this->getParameters()
        );
    }
}
