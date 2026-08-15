<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Callback\Traits;

use App\Enums\TaskStatusEnum;
use App\Models\GatewayMerchant;
use App\Models\MerchantTransactionData;
use App\Models\Task;
use iEXPackages\Payments\Callback\Services\MerchantIncomingCallbackService;
use iEXPackages\Payments\Core\Config\GatewayConfig;
use Illuminate\Support\Arr;

trait CallbackTargetLookupTrait
{
    /**
     * Возвращает:
     * - Task (заявка)
     * - GatewayMerchant (мерчант)
     * - MerchantTransactionData|null (если нашли по external_id)
     *
     * @param array<string,mixed> $callbackConfig
     * @param array<string,mixed> $payload
     * @return array{0:?Task,1:?GatewayMerchant,2:?MerchantTransactionData}
     */
    private function resolveCallbackTarget(
        GatewayConfig $gatewayConfig,
        array $callbackConfig,
        string $alias,
        array $payload,
        string $lookupMode
    ): array {
        if ($lookupMode === MerchantIncomingCallbackService::LOOKUP_BY_EXTERNAL_ID) {
            $externalField = (string)($callbackConfig['external_id_field'] ?? '');
            if ($externalField === '') {
                return [null, null, null];
            }

            $externalId = Arr::get($payload, $externalField);
            $externalId = is_scalar($externalId) ? trim((string)$externalId) : '';

            if ($externalId === '') {
                return [null, null, null];
            }

            $mtd = MerchantTransactionData::query()
                ->where('id_from_merchant', $externalId)
                ->with(['tasks.task_info', 'merchant'])
                ->first();

            if (!$mtd || !$mtd->tasks || !$mtd->merchant) {
                return [null, null, null];
            }

            // Поддерживаем старое правило: только статусы 9/13
            if (!in_array((int)$mtd->tasks->status, [9, 13], true)) {
                return [null, null, null];
            }

            return [$mtd->tasks, $mtd->merchant, $mtd];
        }

        // default: by_order_id
        $orderIdField = (string)($callbackConfig['order_id_field'] ?? '');

        if ($orderIdField === '') {
            return [null, null, null];
        }

        $raw = Arr::get($payload, $orderIdField);
        $orderId = is_numeric($raw) ? (int)$raw : 0;

        if ($orderId <= 0) {
            return [null, null, null];
        }

        $task = Task::query()
            ->whereKey($orderId)
            ->whereIn('status', [
                TaskStatusEnum::PROCESSING_PAYMENT->value,
                TaskStatusEnum::MERCHANT_CONFIRMATION->value,
            ])
            ->first();

        if (!$task || !$task->merchant) {
            return [null, null, null];
        }

        return [$task, $task->merchant, null];
    }
}
