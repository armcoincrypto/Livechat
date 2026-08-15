<?php

declare(strict_types=1);

namespace iEXPackages\Transaction\Services;

use App\Models\GatewayMerchant;
use App\Models\Task;
use iEXPackages\Payments\Core\Engine\GatewayManager;
use iEXPackages\Payments\Payments;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Вызывает "хуки" шлюза после финальных событий по заявке.
 *
 * Идея:
 * - НИКАКИХ method_exists по API-классам шлюза.
 * - Только операции из config.php (operations.*), чтобы было декларативно и безопасно.
 *
 * Операции (рекомендуемые имена):
 * - hook_order_completed  (service) — после успешного завершения заявки
 * - hook_order_rejected   (service) — после отклонения/отмены заявки
 *
 * Эти операции НЕ обязательны: если не объявлены в config.php — просто SKIP.
 */
final class MerchantEventService
{
    public function __construct(
        private readonly GatewayManager $gatewayManager,
    ) {}

    /**
     * Вызывается после успешного завершения заявки.
     */
    public function handleCompleted(Task $task): void
    {
        $merchant = $this->resolveMerchant($task);
        if (!$merchant) {
            return;
        }

        $this->callGatewayHook(
            hook: 'hook_order_completed',
            task: $task,
            merchant: $merchant,
            payload: [
                'transactionId'       => (string) $task->id
            ],
        );
    }

    /**
     * Вызывается после отклонения/отмены заявки.
     */
    public function handleRejected(Task $task, ?string $reason = null): void
    {
        $merchant = $this->resolveMerchant($task);
        if (!$merchant) {
            return;
        }

        $this->callGatewayHook(
            hook: 'hook_order_rejected',
            task: $task,
            merchant: $merchant,
            payload: [
                'transactionId'      => (string) $task->id
            ],
        );
    }

    // ---------------------------------------------------------------------

    private function resolveMerchant(Task $task): ?GatewayMerchant
    {
        // Только если реально назначен мерчант и модель подгружена/доступна
        if ((int) ($task->id_merchant ?? 0) <= 0) {
            return null;
        }

        $merchant = $task->merchant ?? $task->merchant()->first();
        if (!$merchant instanceof GatewayMerchant) {
            return null;
        }

        // Старые/неизвестные alias не должны ломать систему
        $alias = (string) ($merchant->alias ?? '');
        if ($alias === '' || !$this->gatewayManager->hasAlias($alias)) {
            return null;
        }

        return $merchant;
    }

    private function callGatewayHook(string $hook, Task $task, GatewayMerchant $merchant, array $payload): void
    {
        try {
            // runtime gateway с Vault-конфигом мерчанта
            $gateway = Payments::forMerchant($merchant);

            // 1) операция должна быть объявлена в config.php
            $opCfg = $gateway->gatewayConfig()->operationConfig($hook);
            if (empty($opCfg) || empty($opCfg['request_class'])) {
                return; // SKIP без ошибок
            }

            // 2) вызов
            $gateway->request($hook, [
                'payload' => $payload,
            ])->withTask($task)->send();

        } catch (Throwable $e) {
            Log::error('MerchantEventService hook failed', [
                'hook'      => $hook,
                'task_id'   => $task->id ?? null,
                'merchant'  => $merchant->alias ?? null,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
