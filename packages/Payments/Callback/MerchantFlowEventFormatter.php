<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Callback;

use App\Models\MerchantFlowEvent;
use iEXPackages\Payments\Logging\SensitiveDataMasker;

final class MerchantFlowEventFormatter
{
    public function __construct(
        private readonly SensitiveDataMasker $masker,
    ) {}

    public function format(MerchantFlowEvent $row): array
    {
        $context = is_array($row->context ?? null) ? $row->context : [];

        return [
            'id' => (int) $row->id,
            'createdAt' => $row->created_at?->toIso8601String(),

            'taskId' => $row->task_id ? (int) $row->task_id : null,
            'merchantId' => $row->merchant_id ? (int) $row->merchant_id : null,

            'gatewayAlias' => $row->gateway_alias,
            'flow' => $row->flow,
            'stage' => $row->stage,
            'event' => $row->event,
            'level' => $row->level,

            'ip' => $row->ip,
            'userAgent' => $row->user_agent,
            'message' => $row->message,

            'checkoutId' => $row->checkout_id,
            'externalId' => $row->external_id,

            'context' => $this->masker->mask($context),
        ];
    }
}
