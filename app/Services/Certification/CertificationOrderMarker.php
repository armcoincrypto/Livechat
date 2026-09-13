<?php

declare(strict_types=1);

namespace App\Services\Certification;

use App\Models\Task;

final class CertificationOrderMarker
{
    public const FLAG = 'YES';

    public const PROVIDER = 'INTERNAL_FAKE';

    /**
     * @return array<string, mixed>
     */
    public static function payload(string $scenario): array
    {
        return [
            'QA_CERTIFICATION' => self::FLAG,
            'NO_REAL_FUNDS' => self::FLAG,
            'CERTIFICATION_PROVIDER' => self::PROVIDER,
            'scenario' => $scenario,
        ];
    }

    public static function isMarked(?Task $task): bool
    {
        if ($task === null) {
            return false;
        }

        $bag = is_array($task->opt_params) ? $task->opt_params : [];

        return ($bag['QA_CERTIFICATION'] ?? null) === self::FLAG
            && ($bag['NO_REAL_FUNDS'] ?? null) === self::FLAG
            && ($bag['CERTIFICATION_PROVIDER'] ?? null) === self::PROVIDER;
    }

    public static function scenario(Task $task): string
    {
        $bag = is_array($task->opt_params) ? $task->opt_params : [];
        $scenario = (string) ($bag['scenario'] ?? CertificationContext::SCENARIO_SUCCESS);

        return $scenario === CertificationContext::SCENARIO_FAIL_CLOSED
            ? CertificationContext::SCENARIO_FAIL_CLOSED
            : CertificationContext::SCENARIO_SUCCESS;
    }

    public static function apply(Task $task, string $scenario): void
    {
        $bag = is_array($task->opt_params) ? $task->opt_params : [];
        $task->opt_params = array_merge($bag, self::payload($scenario));
        $task->merchant_provider = self::PROVIDER;
        $task->id_merchant = 0;
        $task->is_bot = 0;
        $task->is_check_payment_merchant = 0;
        // Skip Transaction::setStatus reserve mutation for synthetic QA orders.
        $task->is_reserve_out_used = 1;
        $task->is_reserve_in_used = 1;
        $task->save();

        $context = app(CertificationContext::class);
        if ($context->isActive()) {
            $context->recordOrder($task);
        }
    }
}
