<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * C.3E — fail-open attribution lifecycle event writer.
 *
 * Synchronous insertOrIgnore; never throws to callers; never participates in
 * financial transactions. Unlinked tasks are silent no-ops.
 */
final class AttributionEventRecorder
{
    /**
     * Record a mapped lifecycle event for a status transition.
     *
     * @param  object  $task  Task-like object with id / session_attribution_id
     */
    public function recordStatusTransitionFailOpen(object $task, int $newStatus): void
    {
        if (! AttributionFeatures::eventsEnabled()) {
            return;
        }

        try {
            $eventType = AttributionFunnelEventMap::eventForStatus($newStatus);
            if ($eventType === null) {
                return;
            }

            $taskId = isset($task->id) ? (int) $task->id : 0;
            if ($taskId <= 0) {
                return;
            }

            $attrId = isset($task->session_attribution_id)
                ? (int) $task->session_attribution_id
                : 0;
            if ($attrId <= 0) {
                // Unlinked orders must not require events.
                return;
            }

            if (! Schema::hasTable('session_attribution_events')) {
                return;
            }

            $now = now();
            // insertOrIgnore + unique(task_id, event_type) ⇒ idempotent under retries.
            DB::table('session_attribution_events')->insertOrIgnore([
                'session_attribution_id' => $attrId,
                'task_id' => $taskId,
                'event_type' => $eventType,
                'status_code' => $newStatus,
                'occurred_at' => $now,
                'created_at' => $now,
            ]);
        } catch (Throwable $e) {
            Log::warning('attribution_event_record_failed', [
                'class' => $e::class,
                'task_id' => $task->id ?? null,
                'status' => $newStatus,
            ]);
        }
    }
}
