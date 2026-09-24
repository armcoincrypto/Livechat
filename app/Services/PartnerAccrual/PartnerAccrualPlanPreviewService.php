<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

/**
 * Read-only schedule preview. Never mutates DB.
 */
final class PartnerAccrualPlanPreviewService
{
    public function __construct(
        private readonly PartnerAccrualPlanGenerator $generator = new PartnerAccrualPlanGenerator(),
    ) {}

    /**
     * @param  array{
     *   user_id?: int,
     *   currency_code?: string,
     *   total_amount: string,
     *   start_date: string,
     *   end_date: string,
     *   schedule_seed?: string|null,
     *   min_installment?: string|null,
     *   max_installment?: string|null,
     *   variance_percent?: int|null
     * }  $input
     * @return array<string, mixed>
     */
    public function preview(array $input): array
    {
        $generated = $this->generator->generate($input);

        return [
            'user_id' => $input['user_id'] ?? null,
            'currency_code' => $generated['currency_code'],
            'installment_count' => $generated['installment_count'],
            'min' => $generated['min'],
            'max' => $generated['max'],
            'average' => $generated['average'],
            'sum' => $generated['sum'],
            'total_amount' => $generated['total_amount'],
            'schedule' => $generated['schedule'],
            'schedule_seed' => $generated['schedule_seed'],
            'variance_percent' => $generated['variance_percent'],
            'mutates_db' => false,
        ];
    }
}
