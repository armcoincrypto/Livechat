<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\TaskStatusEnum;

/**
 * Single product vocabulary for operator/admin/Telegram surfaces.
 * Does not change status IDs. Customer API keys remain stable (process/pay/…).
 */
final class OperatorStatusVocabulary
{
    /**
     * @return array{id:int, key:string, admin:string, telegram:string, customer_concept:string}
     */
    public static function describe(int $statusId): array
    {
        return match ($statusId) {
            TaskStatusEnum::WAITING_HANDLE->value => [
                'id' => 3,
                'key' => 'waiting_handle',
                'admin' => 'Customer marked paid / awaiting confirmation',
                'telegram' => 'Ожидает проверки оплаты (клиент отметил «Я оплатил»)',
                'customer_concept' => 'We are checking your payment (not yet confirmed on-chain/provider)',
            ],
            TaskStatusEnum::PAID->value => [
                'id' => 7,
                'key' => 'paid',
                'admin' => 'Payment confirmed — settlement required',
                'telegram' => 'Оплата подтверждена — требуется выплата',
                'customer_concept' => 'Payment confirmed — exchange in progress',
            ],
            TaskStatusEnum::COMPLETED->value => [
                'id' => 4,
                'key' => 'completed',
                'admin' => 'Completed',
                'telegram' => 'Выполнена',
                'customer_concept' => 'Exchange completed',
            ],
            TaskStatusEnum::REJECTED->value => [
                'id' => 5,
                'key' => 'rejected',
                'admin' => 'Rejected',
                'telegram' => 'Отклонена',
                'customer_concept' => 'Order rejected',
            ],
            TaskStatusEnum::CANCELED_BY_USER->value => [
                'id' => 6,
                'key' => 'canceled_by_user',
                'admin' => 'Canceled by user',
                'telegram' => 'Отменена клиентом',
                'customer_concept' => 'Order canceled',
            ],
            TaskStatusEnum::EXPIRED->value => [
                'id' => 1,
                'key' => 'expired',
                'admin' => 'Expired',
                'telegram' => 'Истекла',
                'customer_concept' => 'Order expired',
            ],
            TaskStatusEnum::FROZEN->value => [
                'id' => 8,
                'key' => 'frozen',
                'admin' => 'Needs review / frozen',
                'telegram' => 'На проверке (заморожена)',
                'customer_concept' => 'Order under review',
            ],
            default => [
                'id' => $statusId,
                'key' => 'status_'.$statusId,
                'admin' => 'Status '.$statusId,
                'telegram' => 'Статус '.$statusId,
                'customer_concept' => 'Status '.$statusId,
            ],
        };
    }

    public static function adminLabel(int $statusId, ?string $legacyName = null): string
    {
        $legacy = is_string($legacyName) ? trim($legacyName) : '';
        $canonical = self::describe($statusId)['admin'];
        // Prefer canonical vocabulary for the Wave A critical statuses.
        if (in_array($statusId, [1, 3, 4, 5, 6, 7, 8], true)) {
            return $canonical;
        }

        return $legacy !== '' ? $legacy : $canonical;
    }

    public static function telegramLabel(int $statusId, ?string $legacyName = null): string
    {
        $legacy = is_string($legacyName) ? trim($legacyName) : '';
        $canonical = self::describe($statusId)['telegram'];
        if (in_array($statusId, [1, 3, 4, 5, 6, 7, 8], true)) {
            return $canonical;
        }

        return $legacy !== '' ? $legacy : $canonical;
    }
}
