<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\NicePay\Messages;

use iEXPackages\Payment\Exception\InvalidResponseException;
use iEXPackages\Payments\Core\Contracts\RequestInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

/**
 * CompletePurchaseResponse (NicePay)
 *
 * Проверяет подпись "hash" согласно документации:
 * 1) берём ВСЕ параметры callback
 * 2) удаляем hash
 * 3) сортируем по ключам (ksort SORT_STRING)
 * 4) добавляем в конец secret key мерчанта
 * 5) склеиваем значения через "{np}"
 * 6) sha256 от полученной строки
 * 7) сравниваем с hash из запроса
 *
 * Важно:
 * - Secret Key берём из настроек мерчанта (inputs.merchant.fields.secret)
 * - В новой архитектуре доступ к нему через $this->request (Request -> Inputs accessor)
 */
final class CompletePurchaseResponse extends AbstractResponse
{
    public function __construct(
        RequestInterface $request,
        array $data,
        array $query = []
    ) {
        parent::__construct($request, $data, $query);

        // В продакшене подпись обязана совпадать.
        // В dev можно разрешить пропуск, если надо (но лучше не надо).
        if ($this->isProduction()) {
            $this->assertValidSignature();
        }
    }

    /**
     * Финальный успех.
     * success => платёж подтверждён
     */
    public function isSuccessful(): bool
    {
        return $this->safeString($this->dataGet('result')) === 'success';
    }

    /**
     * Финальная ошибка.
     * error => платёж завершён с ошибкой
     */
    public function isCancelled(): bool
    {
        return $this->safeString($this->dataGet('result')) === 'error';
    }

    /**
     * NicePay не отдаёт pending в этом callback — он приходит только после финального статуса.
     */
    public function isPending(): bool
    {
        return false;
    }

    /**
     * Внешний ID операции у провайдера.
     */
    public function getExternalId(): ?string
    {
        // по смыслу это payment_id
        return $this->safeString($this->dataGet('payment_id'));
    }

    /**
     * ID заявки в нашей системе.
     * В доке: order_id — "ID платежа или клиента в Вашей системе"
     */
    public function getOrderId(): ?string
    {
        return $this->safeString($this->dataGet('order_id'));
    }

    /**
     * Сумма (в центах/копейках у NicePay).
     * Возвращаем строкой.
     */
    public function getAmount(): ?string
    {
        // amount приходит как number (minor units)
        $raw = $this->dataGet('amount');

        if (is_int($raw)) {
            return (string) $raw;
        }

        if (is_string($raw) && $raw !== '' && is_numeric($raw)) {
            return (string) (int) $raw;
        }

        return null;
    }

    /**
     * Валюта суммы платежа.
     */
    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('amount_currency'));
    }

    /**
     * Текст ошибки (если есть).
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        // У NicePay в callback нет "message", но оставим гибкость
        return $this->safeString($this->dataGet('message'))
            ?? 'NicePay: платёж завершён с ошибкой';
    }

    /**
     * Хэш из callback.
     */
    public function getHash(): ?string
    {
        return $this->safeString($this->dataGet('hash'));
    }

    // ---------------------------------------------------------------------
    // Internal: signature validation
    // ---------------------------------------------------------------------

    private function assertValidSignature(): void
    {
        $receivedHash = (string) ($this->getHash() ?? '');
        if ($receivedHash === '') {
            throw new InvalidResponseException('NicePay: отсутствует hash в callback.');
        }

        $expectedHash = $this->calculateSignature();

        if (!hash_equals($expectedHash, $receivedHash)) {
            throw new InvalidResponseException('NicePay: подпись callback не соответствует ожидаемой.');
        }
    }

    /**
     * sha256([values sorted by key]{np}secret)
     */
    private function calculateSignature(): string
    {
        $params = $this->data ?? [];
        if (!is_array($params)) {
            $params = [];
        }

        // hash не участвует в формировании подписи
        unset($params['hash']);

        // сортировка по ключам
        ksort($params, SORT_STRING);

        // берём secret key мерчанта из настроек
        $secret = $this->request->getSecretKey();
        if ($secret === '') {
            throw new InvalidResponseException('NicePay: secret key не задан в настройках мерчанта.');
        }

        // Собираем строку из VALUES через {np}
        $values = [];
        foreach ($params as $value) {
            if (is_array($value) || is_object($value)) {
                // на всякий случай: сериализация сложных типов (обычно нет)
                $values[] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                continue;
            }
            $values[] = (string) $value;
        }

        $values[] = $secret;

        $hashString = implode('{np}', $values);

        return hash('sha256', $hashString);
    }

    private function isProduction(): bool
    {
        // безопаснее: в проде проверяем всегда
        return (bool) app()->isProduction();
    }
}
