<?php
declare(strict_types=1);

namespace iEXPackages\Payments\Logging\Services;

use App\Models\MerchantFlowEvent;
use App\Models\Task;
use App\Models\GatewayMerchant;

final class MerchantFlowLogger
{
    public function info(
        string $event,
        ?Task $task = null,
        ?GatewayMerchant $merchant = null,
        array $ctx = [],
        ?string $message = null,
        ?string $stage = null,
        string $flow = 'merchant'
    ): void {
        $this->write('info', $event, $task, $merchant, $ctx, $message, $stage, $flow);
    }

    public function warning(
        string $event,
        ?Task $task = null,
        ?GatewayMerchant $merchant = null,
        array $ctx = [],
        ?string $message = null,
        ?string $stage = null,
        string $flow = 'merchant'
    ): void {
        $this->write('warning', $event, $task, $merchant, $ctx, $message, $stage, $flow);
    }

    public function error(
        string $event,
        ?Task $task = null,
        ?GatewayMerchant $merchant = null,
        array $ctx = [],
        ?string $message = null,
        ?string $stage = null,
        string $flow = 'merchant'
    ): void {
        $this->write('error', $event, $task, $merchant, $ctx, $message, $stage, $flow);
    }

    public function security(
        string $event,
        ?Task $task = null,
        ?GatewayMerchant $merchant = null,
        array $ctx = [],
        ?string $message = null,
        ?string $stage = null,
        string $flow = 'merchant'
    ): void {
        $this->write('security', $event, $task, $merchant, $ctx, $message, $stage, $flow);
    }

    private function write(
        string $level,
        string $event,
        ?Task $task,
        ?GatewayMerchant $merchant,
        array $ctx,
        ?string $message,
        ?string $stage,
        string $flow
    ): void {
        MerchantFlowEvent::create([
            'task_id'      => $task?->id,
            'merchant_id'  => $merchant?->id,
            'gateway_alias'=> $merchant?->alias ?? ($ctx['gateway_alias'] ?? null),
            'flow'         => $flow,
            'stage'        => $stage,
            'event'        => $event,
            'level'        => $level,
            'ip'           => $ctx['ip'] ?? null,
            'user_agent'   => $ctx['user_agent'] ?? null,
            'message'      => $message,
            'context'      => $ctx['context'] ?? $ctx,

            'checkout_id'  => $ctx['checkout_id'] ?? null,
            'external_id'  => $ctx['external_id'] ?? null,
        ]);
    }
}
