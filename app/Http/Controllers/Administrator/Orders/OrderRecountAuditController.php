<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Orders;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use iEXPackages\OrderRecount\Models\OrderRecountAudit;
use iEXPackages\OrderRecount\Models\OrderRecountState;

final class OrderRecountAuditController extends Controller
{
    /**
     * Полная информация по пересчётам заявки (новая система):
     * - summary (заявка + контекст)
     * - state   (order_recount_states)
     * - audit   (order_recount_audit)
     *
     * GET /admin/orders/{id}/order-recount
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $task = Task::query()
            ->with(['direction_exchange.currency1'])
            ->find($id);

        if (!$task) {
            return response()->json([
                'status' => 1,
                'message' => 'Заявка не найдена.',
                'data' => null,
            ], 404);
        }

        $auditLimit = max(1, min(500, (int) $request->query('audit_limit', 100)));

        // Фильтры для audit (опционально)
        $decision  = trim((string) $request->query('decision', ''));
        $trigger   = trim((string) $request->query('trigger', ''));
        $reason    = trim((string) $request->query('reason_code', ''));
        $policyId  = (int) $request->query('policy_id', 0);

        // rate_changed: 1/0/'' (пусто = не фильтровать)
        $rateChangedRaw = $request->query('rate_changed', null);
        $rateChanged = null;
        if ($rateChangedRaw !== null && $rateChangedRaw !== '') {
            $rateChanged = ((int)$rateChangedRaw) === 1;
        }

        $state = OrderRecountState::query()->find((int) $task->id);

        $auditQ = OrderRecountAudit::query()
            ->where('task_id', (int) $task->id);

        if ($decision !== '') {
            $auditQ->where('decision', $decision);
        }
        if ($reason !== '') {
            $auditQ->where('reason_code', $reason);
        }

        // trigger лежит в meta.trigger (строка)
        if ($trigger !== '') {
            $auditQ->where('meta->trigger', '=', $trigger);
        }

        if ($policyId > 0) {
            $auditQ->where('policy_id', '=', $policyId);
        }

        // rate_changed лежит в meta.rate_changed (bool)
        if ($rateChanged !== null) {
            $auditQ->where('meta->rate_changed', '=', $rateChanged);
        }

        $audit = $auditQ
            ->orderByDesc('created_at')
            ->limit($auditLimit)
            ->get()
            ->map(function (OrderRecountAudit $a) {
                $meta = is_array($a->meta) ? $a->meta : [];

                return [
                    'id' => (int) $a->id,
                    'created_at' => $a->created_at?->toIso8601String(),

                    'decision' => (string) $a->decision,
                    'reason_code' => (string) $a->reason_code,
                    'policy_id' => $a->policy_id !== null ? (int) $a->policy_id : null,

                    // numeric (машинный курс)
                    'old_rate' => $a->old_rate !== null ? (string) $a->old_rate : null,
                    'new_rate' => $a->new_rate !== null ? (string) $a->new_rate : null,

                    // Удобные плоские поля (для UI)
                    'trigger' => isset($meta['trigger']) ? (string) $meta['trigger'] : null,

                    'perf_ms' => $meta['perf_ms'] ?? null,
                    'calc_ms' => $meta['calc_ms'] ?? null,
                    'recount_ms' => $meta['recount_ms'] ?? null,
                    'recount_total_ms' => $meta['recount_total_ms'] ?? null,

                    'old_course_display' => isset($meta['old_course_display']) ? (string) $meta['old_course_display'] : null,
                    'new_course_display' => isset($meta['new_course_display']) ? (string) $meta['new_course_display'] : null,

                    'rate_changed' => $meta['rate_changed'] ?? null,
                    'rate_diff_percent' => $meta['rate_diff_percent'] ?? null,

                    // И meta целиком (для расширенной диагностики)
                    'meta' => $meta,
                ];
            })
            ->values();

        $dir = $task->direction_exchange;
        $cur = $dir?->currency1;

        $summary = [
            'task' => [
                'id' => (int) $task->id,
                'status' => (int) $task->status,
                'created_at' => $task->created_at?->toIso8601String(),
                'updated_at' => $task->updated_at?->toIso8601String(),
                'completed_at' => $task->completed_at?->toIso8601String(),

                'course_float' => (string) ($task->course_float ?? ''),
                'course_display' => (string) ($task->course_display ?? ''),

                'is_type_rate' => (int) ($task->is_type_rate ?? 0),
                'type_rate' => (int) ($task->type_rate ?? 0),

                'floating_recount_stop' => (int) ($task->floating_recount_stop ?? 0),
                'is_frozen' => (int) ($task->is_frozen ?? 0),
            ],
            'direction' => $dir ? [
                'id' => (int) $dir->id,
                'tech_name' => (string) ($dir->tech_name ?? ''),
                'is_type_rate' => (int) ($dir->is_type_rate ?? 0),

                // floating settings (для понимания)
                'floating_fee_time' => (int) ($dir->floating_fee_time ?? 0),
                'floating_fee_statuses' => (array) ($dir->floating_fee_statuses ?? []),
                'floating_threshold_recount_up' => (float) ($dir->floating_threshold_recount_up ?? 0),
                'floating_threshold_recount_down' => (float) ($dir->floating_threshold_recount_down ?? 0)
            ] : null,
            'currency' => $cur ? [
                'id' => (int) $cur->id,
                'tech_name' => (string) ($cur->tech_name ?? ''),
                'small_code' => (string) ($cur->small_code ?? ''),
            ] : null,
        ];

        return response()->json([
            'status' => 0,
            'data' => [
                'summary' => $summary,
                'state' => $state ? [
                    'task_id' => (int) $state->task_id,

                    'last_recalculated_at_global' => $state->last_recalculated_at_global?->toIso8601String(),
                    'last_rate_value_global' => $state->last_rate_value_global,

                    'last_recalculated_at_floating' => $state->last_recalculated_at_floating?->toIso8601String(),
                    'last_rate_value_floating' => $state->last_rate_value_floating,

                    'status_last_global' => $state->status_last_global,
                    'status_entered_at_global' => $state->status_entered_at_global?->toIso8601String(),
                    'recount_in_status_count_global' => (int) ($state->recount_in_status_count_global ?? 0),

                    'status_last_floating' => $state->status_last_floating,
                    'status_entered_at_floating' => $state->status_entered_at_floating?->toIso8601String(),
                    'recount_in_status_count_floating' => (int) ($state->recount_in_status_count_floating ?? 0),

                    'last_checked_at' => $state->last_checked_at?->toIso8601String(),
                    'fail_streak' => (int) ($state->fail_streak ?? 0),
                    'locked_until' => $state->locked_until?->toIso8601String(),
                ] : null,
                'audit' => $audit,
            ],
        ]);
    }
}
