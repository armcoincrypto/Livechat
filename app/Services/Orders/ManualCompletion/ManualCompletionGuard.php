<?php

declare(strict_types=1);

namespace App\Services\Orders\ManualCompletion;

use App\Models\Task;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Wave 1 completion policy (not a global state-machine rewrite).
 *
 * Manual HTTP: permission + settlement evidence + allowed inbound statuses.
 * System/console: lock/idempotency only; operator evidence not required.
 */
final class ManualCompletionGuard
{
    /** Operator-executable statuses historically used to reach COMPLETED. */
    public const MANUAL_FROM_STATUSES = [3, 7, 12, 14];

    /** Cron/autopay/pending-withdrawal inbound statuses (autopay remains OFF). */
    public const SYSTEM_FROM_STATUSES = [7, 14, 15, 16];

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SYSTEM = 'system';

    public const COMPLETED = 4;

    /**
     * @return array{already_completed: bool, settlement: ?array, source: string, from_status: int}
     */
    public function authorizeLockedTask(Task $task, string $source, array $options, ?Authenticatable $user): array
    {
        $from = (int) $task->status;
        if ($from === self::COMPLETED) {
            return [
                'already_completed' => true,
                'settlement' => is_array($task->opt_params['manual_settlement'] ?? null)
                    ? $task->opt_params['manual_settlement']
                    : null,
                'source' => $source,
                'from_status' => $from,
            ];
        }

        if ($source === self::SOURCE_MANUAL) {
            if ($user === null) {
                throw ManualCompletionException::unauthenticated();
            }
            if (!$user->can('admin_orders_execute')) {
                throw ManualCompletionException::forbidden();
            }
            if (!in_array($from, self::MANUAL_FROM_STATUSES, true)) {
                throw ManualCompletionException::invalidStatus($from);
            }
            $evidence = SettlementEvidence::fromOptions($options);
            $settlement = $evidence->toAuditArray((int) $user->getAuthIdentifier(), 'manual_admin_complete');
        } else {
            if (!in_array($from, self::SYSTEM_FROM_STATUSES, true)) {
                throw ManualCompletionException::invalidStatus($from);
            }
            $settlement = [
                'reference' => null,
                'source' => 'system',
                'writer' => (string) ($options['system_writer'] ?? 'system_success'),
                'completed_by' => 0,
                'completed_at' => now()->toIso8601String(),
            ];
        }

        return [
            'already_completed' => false,
            'settlement' => $settlement,
            'source' => $source,
            'from_status' => $from,
        ];
    }

    public static function detectSource(array $options): string
    {
        $explicit = $options['completion_source'] ?? null;
        if ($explicit === self::SOURCE_MANUAL || $explicit === self::SOURCE_SYSTEM) {
            return $explicit;
        }

        return app()->runningInConsole() ? self::SOURCE_SYSTEM : self::SOURCE_MANUAL;
    }
}
