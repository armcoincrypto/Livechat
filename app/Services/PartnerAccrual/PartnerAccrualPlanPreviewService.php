<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

/**
 * Read-only schedule preview. Never mutates DB.
 * Returns a signed schedule_seed bound to operator context.
 */
final class PartnerAccrualPlanPreviewService
{
    public function __construct(
        private readonly PartnerAccrualPlanGenerator $generator = new PartnerAccrualPlanGenerator(),
        private readonly PartnerAccrualScheduleSeedBinder $seedBinder = new PartnerAccrualScheduleSeedBinder(),
    ) {}

    /**
     * @param  array{
     *   user_id: int,
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
        if (! isset($input['user_id'])) {
            throw Exceptions\InvalidScheduleSeed::message('user_id required for bound preview');
        }

        $context = [
            'user_id' => (int) $input['user_id'],
            'total_amount' => (string) $input['total_amount'],
            'start_date' => (string) $input['start_date'],
            'end_date' => (string) $input['end_date'],
            'currency_code' => $input['currency_code'] ?? PartnerAccrualCurrency::V1_CODE,
            'variance_percent' => $input['variance_percent'] ?? null,
            'min_installment' => $input['min_installment'] ?? null,
            'max_installment' => $input['max_installment'] ?? null,
        ];

        $incoming = isset($input['schedule_seed']) ? trim((string) $input['schedule_seed']) : '';
        $rngNonce = null;
        if ($incoming !== '') {
            $rngNonce = $this->seedBinder->resolveRngNonce($incoming, $context);
        }

        $token = $this->seedBinder->issue([
            ...$context,
            'rng_nonce' => $rngNonce,
        ]);
        $rngNonce = $this->seedBinder->assertMatches($token, $context);

        $generated = $this->generator->generate([
            'total_amount' => $input['total_amount'],
            'start_date' => $input['start_date'],
            'end_date' => $input['end_date'],
            'currency_code' => $context['currency_code'],
            'schedule_seed' => $rngNonce,
            'min_installment' => $input['min_installment'] ?? null,
            'max_installment' => $input['max_installment'] ?? null,
            'variance_percent' => $input['variance_percent'] ?? null,
        ]);

        return [
            'user_id' => (int) $input['user_id'],
            'currency_code' => $generated['currency_code'],
            'installment_count' => $generated['installment_count'],
            'min' => $generated['min'],
            'max' => $generated['max'],
            'average' => $generated['average'],
            'sum' => $generated['sum'],
            'total_amount' => $generated['total_amount'],
            'schedule' => $generated['schedule'],
            'schedule_seed' => $token,
            'rng_nonce' => $rngNonce,
            'variance_percent' => $generated['variance_percent'],
            'mutates_db' => false,
        ];
    }
}
