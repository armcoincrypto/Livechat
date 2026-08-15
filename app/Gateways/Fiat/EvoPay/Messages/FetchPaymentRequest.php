<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\EvoPay\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use InvalidArgumentException;

/**
 * Проверка поступления средств через API (polling).
 *
 * Входные параметры предполагаются такими:
 *  - externalId  — ID депозита/платежа  (из PurchaseResponse::getExternalId())
 *  - transactionId (опционально) — твой внутренний ID (для логов/связки с Task)
 */
final class FetchPaymentRequest extends AbstractRequest
{
    /**
     * Получение статуса входящего платежа через API.
     *
     * ВАЖНО:
     * - externalId = ID платежа/ордера у провайдера (id) ИЛИ твой tracking key
     * - Мы не делаем прямой GET /smart-orders/{id}, потому что у этого шлюза фактический источник — /order/list.
     */
    public function getData(): array
    {
        // externalId может быть пустой — тогда просто вернем "последний" (по customId)
        $trackingId = $this->requireIncomingTrackingId();

        return [
            'externalId' => $trackingId,
            'order_type' => 'PAYIN',
            'limit'      => 100,
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $orderType = (string) ($data['order_type'] ?? 'PAYIN');
        $limit     = (int) ($data['limit'] ?? 100);

        // 1) Берём список
        $result = $this->sendRequest('get', '/order/list', [
            'order_type' => $orderType,
            'limit'      => $limit,
        ], 'asJson');

        $entries = $result['entries'] ?? null;

        if (!is_array($entries) || $entries === []) {
            throw new InvalidArgumentException('Транзакция не найдена');
        }

        // 2) Сортировка как раньше: по числу после ORDER_ID_
        $sorted = $this->sortEntriesByCustomIdDesc($entries);

        // 3) Если externalId НЕ передан — берём самую свежую запись (первую в отсортированном)
        $externalId = (string) ($data['externalId'] ?? '');

        if ($externalId === '') {
            $entry = $sorted[0] ?? [];
            if (!is_array($entry) || $entry === []) {
                throw new InvalidArgumentException('Транзакция не найдена');
            }

            return $this->response = new FetchPaymentResponse(
                request: $this,
                data: $entry,
                query: $data
            );
        }

        // 4) Если externalId задан — ищем точное совпадение по id
        $entry = $this->findEntryById($sorted, $externalId);

        if ($entry === null) {
            throw new InvalidArgumentException('Транзакция не найдена');
        }

        return $this->response = new FetchPaymentResponse(
            request: $this,
            data: $entry,
            query: $data
        );
    }

    /**
     * @param array<int, mixed> $entries
     * @return array<int, array<string, mixed>>
     */
    private function sortEntriesByCustomIdDesc(array $entries): array
    {
        // Оставляем только валидные массивы
        $normalized = [];

        foreach ($entries as $row) {
            if (is_array($row)) {
                $normalized[] = $row;
            }
        }

        usort($normalized, function (array $a, array $b): int {
            $aNum = $this->customIdNumber($a['customId'] ?? null);
            $bNum = $this->customIdNumber($b['customId'] ?? null);

            // DESC
            return $bNum <=> $aNum;
        });

        return $normalized;
    }

    private function customIdNumber(mixed $customId): int
    {
        if (!is_string($customId) || $customId === '') {
            return 0;
        }

        // ORDER_ID_123 → 123
        $n = str_replace('ORDER_ID_', '', $customId);

        return is_numeric($n) ? (int) $n : 0;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function findEntryById(array $entries, string $orderId): ?array
    {
        foreach ($entries as $row) {
            $id = $row['id'] ?? null;

            if ($id === null) {
                continue;
            }

            // строгое сравнение как строка (чтобы не потерять UUID/строки)
            if ((string) $id === $orderId) {
                return $row;
            }
        }

        return null;
    }
}
