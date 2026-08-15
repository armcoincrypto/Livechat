<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount\Services;

use App\Models\Task;
use Carbon\CarbonImmutable;
use iEXPackages\OrderRecount\Models\OrderRecountAudit;
use iEXPackages\OrderRecount\Models\OrderRecountState;
use iEXPackages\OrderRecount\Support\PolicyRepository;
use iEXPackages\OrderRecount\Support\RateProvider;
use iEXPackages\OrderRecount\Support\RecountExecutor;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * OrderRecountEngine
 *
 * Логика пересчёта:
 *  1) FIX по направлению:
 *     - заявка в режиме type-rate (task.is_type_rate=1)
 *     - выбран FIX-режим (task.type_rate != 1)
 *     - в направлении включён пересчёт FIX (direction_exchange.fix_is_enable_recount=1)
 *     - статус заявки входит в direction_exchange.fix_fee_statuses
 *     - соблюдён интервал direction_exchange.fix_fee_time (мин)
 *     - выполняем recount(at_rate=2)
 *
 *  2) FLOATING по направлению:
 *     - заявка плавающая (task.is_type_rate=1, task.type_rate=1)
 *     - направление type-rate (direction_exchange.is_type_rate=1)
 *     - статус заявки входит в direction_exchange.floating_fee_statuses
 *     - соблюдён интервал direction_exchange.floating_fee_time (мин)
 *     - изменение курса выходит за пороги floating_threshold_recount_up / floating_threshold_recount_down
 *     - выполняем recount(at_rate=1)
 *
 *  3) Политики (валюта/общие):
 *     - применяются, если FIX/FLOATING не сработали
 *     - условия из order_recount_policies (scope=global/currency)
 *
 * Дополнения:
 *  - В audit.meta добавляем old/new course_display (чтобы было понятно человеку)
 *  - Метка изменения курса:
 *      - rate_changed: bool|null
 *      - rate_diff_percent: float|null
 *  - Условия:
 *      - field_equals (например, freeze-safe: is_frozen == 0)
 *      - rate_changed_required (пересчитывать только если курс изменился)
 *      - min_give_amount (минимальная сумма «Отдаю»)
 *  - Event-хуки (опционально, если класс события существует):
 *      - OrderRecountNotice('rate_changed', ...)
 *      - OrderRecountNotice('failed', ...)
 *
 * Stop-recount настройки/статусы не используются.
 */
final class OrderRecountEngine
{
    public function __construct(
        private readonly PolicyRepository $policies,
        private readonly RateProvider $rates,
        private readonly RecountExecutor $executor,
    ) {}

    public function execute(Task $task, string $triggerType, ?int $currentStatus = null): void
    {
        $tsStart = hrtime(true);
        $now     = CarbonImmutable::now();
        $taskId  = (int) $task->id;
        $status  = (int) ($currentStatus ?? $task->status);

        // lock
        $lockStore  = (string) config('order-recount.lock.store', 'redis');
        $lockPrefix = (string) config('order-recount.lock.prefix', 'order-recount');
        $ttl        = (int) config('order-recount.lock.ttl_seconds', 60);

        $lock = Cache::store($lockStore)->lock("{$lockPrefix}:task:{$taskId}", $ttl);

        if (!$lock->get()) {
            $this->audit(
                $taskId,
                null,
                'skipped',
                'locked',
                $this->auditMeta($task, $triggerType, $status, $tsStart, ['reason' => 'locked']),
                null,
                null
            );
            return;
        }

        try {
            /** @var OrderRecountState $state */
            $state = OrderRecountState::query()->firstOrCreate(
                ['task_id' => $taskId],
                ['fail_streak' => 0]
            );

            // locked_until
            if ($state->locked_until !== null && $now->lt($state->locked_until)) {
                $this->audit(
                    $taskId,
                    null,
                    'skipped',
                    'locked_until',
                    $this->auditMeta($task, $triggerType, $status, $tsStart, [
                        'locked_until' => $state->locked_until->toIso8601String(),
                    ]),
                    null,
                    null
                );
                return;
            }

            // sync status sessions (global + floating)
            $this->syncStatusSession($state, $status, $now, 'global');
            $this->syncStatusSession($state, $status, $now, 'floating');

            $state->last_checked_at = $now;
            $state->save();

            // --------------------------------------------------------------
            // 1) Direction FIX
            // --------------------------------------------------------------
            $fixCalcMs = null;
            $fixRecountMs = null;
            $fixOldDisplay = null;
            $fixNewDisplay = null;
            $fixOldRate = null;
            $fixNewRate = null;

            if ($this->tryDirectionFix(
                $task,
                $state,
                $fixCalcMs,
                $fixRecountMs,
                $fixOldDisplay,
                $fixNewDisplay,
                $fixOldRate,
                $fixNewRate
            )) {
                // Для направленческих пересчётов используем direction-канал state (у нас он назван floating)
                $this->afterSuccess($task, $state, $now, 'floating');

                [$rateChanged, $rateDiffPercent] = $this->computeRateChange(
                    $fixOldRate,
                    $fixNewRate,
                    (string) ($fixOldDisplay ?? ''),
                    (string) ($fixNewDisplay ?? '')
                );

                $extra = [
                    'source' => 'direction_exchange',
                    'mode' => 'fix',
                    'rate_changed' => $rateChanged,
                    'rate_diff_percent' => $rateDiffPercent,
                ];

                if ($fixCalcMs !== null) $extra['calc_ms'] = $fixCalcMs;
                if ($fixRecountMs !== null) $extra['recount_ms'] = $fixRecountMs;
                if ($fixOldDisplay !== null && $fixOldDisplay !== '') $extra['old_course_display'] = $fixOldDisplay;
                if ($fixNewDisplay !== null && $fixNewDisplay !== '') $extra['new_course_display'] = $fixNewDisplay;

                $this->audit(
                    $taskId,
                    null,
                    'executed',
                    'fix_executed',
                    $this->auditMeta($task, $triggerType, $status, $tsStart, $extra),
                    $fixOldRate,
                    $fixNewRate
                );

                if ($rateChanged === true) {
                    $this->dispatchNotice('rate_changed', $task, [
                        'trigger' => $triggerType,
                        'diff_percent' => $rateDiffPercent,
                        'old_course_display' => $fixOldDisplay,
                        'new_course_display' => $fixNewDisplay,
                        'mode' => 'fix',
                    ]);
                }

                return;
            }

            // --------------------------------------------------------------
            // 2) Direction FLOATING
            // --------------------------------------------------------------
            $floatingCalcMs = null;
            $floatingRecountMs = null;
            $floatingOldDisplay = null;
            $floatingNewDisplay = null;
            $floatingOldRate = null;
            $floatingNewRate = null;

            if ($this->tryDirectionFloating(
                $task,
                $state,
                $floatingCalcMs,
                $floatingRecountMs,
                $floatingOldDisplay,
                $floatingNewDisplay,
                $floatingOldRate,
                $floatingNewRate
            )) {
                $this->afterSuccess($task, $state, $now, 'floating');

                [$rateChanged, $rateDiffPercent] = $this->computeRateChange(
                    $floatingOldRate,
                    $floatingNewRate,
                    (string) ($floatingOldDisplay ?? ''),
                    (string) ($floatingNewDisplay ?? '')
                );

                $extra = [
                    'source' => 'direction_exchange',
                    'mode' => 'floating',
                    'rate_changed' => $rateChanged,
                    'rate_diff_percent' => $rateDiffPercent,
                ];

                if ($floatingCalcMs !== null) $extra['calc_ms'] = $floatingCalcMs;
                if ($floatingRecountMs !== null) $extra['recount_ms'] = $floatingRecountMs;

                if ($floatingOldDisplay !== null && $floatingOldDisplay !== '') $extra['old_course_display'] = $floatingOldDisplay;
                if ($floatingNewDisplay !== null && $floatingNewDisplay !== '') $extra['new_course_display'] = $floatingNewDisplay;

                $this->audit(
                    $taskId,
                    null,
                    'executed',
                    'floating_executed',
                    $this->auditMeta($task, $triggerType, $status, $tsStart, $extra),
                    $floatingOldRate,
                    $floatingNewRate
                );

                if ($rateChanged === true) {
                    $this->dispatchNotice('rate_changed', $task, [
                        'trigger' => $triggerType,
                        'diff_percent' => $rateDiffPercent,
                        'old_course_display' => $floatingOldDisplay,
                        'new_course_display' => $floatingNewDisplay,
                        'mode' => 'floating',
                    ]);
                }

                return;
            }

            // --------------------------------------------------------------
            // 3) Policies (currency/global)
            // --------------------------------------------------------------
            $policies = $this->policies->forTask($task);
            if ($policies->isEmpty()) {
                return;
            }

            foreach ($policies as $policy) {
                if (!in_array($triggerType, (array) $policy->trigger_types, true)) {
                    continue;
                }

                // conditions AND
                foreach ((array) $policy->conditions as $cond) {
                    $r = $this->checkCondition($task, $state, $status, $triggerType, (array) $cond);

                    if (($r['ok'] ?? false) !== true) {
                        $this->audit(
                            $taskId,
                            (int) $policy->id,
                            'skipped',
                            (string) ($r['reason'] ?? 'condition_failed'),
                            $this->auditMeta($task, $triggerType, $status, $tsStart, (array) ($r['meta'] ?? [])),
                            null,
                            null
                        );
                        continue 2;
                    }
                }

                $oldRate = $this->rates->currentRate($task);
                $oldCourseDisplay = trim((string) ($task->course_display ?? ''));

                $this->audit(
                    $taskId,
                    (int) $policy->id,
                    'matched',
                    'matched',
                    $this->auditMeta($task, $triggerType, $status, $tsStart, [
                        'policy_title' => (string) $policy->title,
                        'policy_scope_type' => (string) ($policy->scope_type ?? ''),
                        'policy_scope_id' => (int) ($policy->scope_id ?? 0),
                        'old_course_display' => $oldCourseDisplay,

                        'rate_changed' => null,
                        'rate_diff_percent' => null,
                    ]),
                    $oldRate,
                    null
                );

                try {
                    $totalRecountMs = 0;
                    $lastRecountMs = null;

                    foreach ((array) $policy->actions as $a) {
                        $atype = (string) ($a['type'] ?? '');

                        if ($atype === 'recount') {
                            $atRate = (int) ($a['at_rate'] ?? 1);

                            $t0 = hrtime(true);
                            $this->executor->recount($task, $atRate);
                            $dt = (int) ((hrtime(true) - $t0) / 1_000_000);

                            $lastRecountMs = $dt;
                            $totalRecountMs += $dt;
                        }

                        if ($atype === 'set_task_flag') {
                            $field = (string) ($a['field'] ?? '');
                            if ($field !== '') {
                                $task->update([$field => ($a['value'] ?? 1)]);
                            }
                        }
                    }

                    $fresh = $task->fresh() ?? $task;
                    $newRate = $this->rates->currentRate($fresh);
                    $newCourseDisplay = trim((string) ($fresh->course_display ?? ''));

                    $this->afterSuccess($fresh, $state, $now, 'global');

                    [$rateChanged, $rateDiffPercent] = $this->computeRateChange(
                        $oldRate,
                        $newRate,
                        $oldCourseDisplay,
                        $newCourseDisplay
                    );

                    $extra = [
                        'policy_title' => (string) $policy->title,
                        'policy_scope_type' => (string) ($policy->scope_type ?? ''),
                        'policy_scope_id' => (int) ($policy->scope_id ?? 0),

                        'old_course_display' => $oldCourseDisplay,
                        'new_course_display' => $newCourseDisplay,

                        'rate_changed' => $rateChanged,
                        'rate_diff_percent' => $rateDiffPercent,
                    ];

                    if ($lastRecountMs !== null) $extra['recount_ms'] = $lastRecountMs;
                    if ($totalRecountMs > 0) $extra['recount_total_ms'] = $totalRecountMs;

                    $this->audit(
                        $taskId,
                        (int) $policy->id,
                        'executed',
                        'executed',
                        $this->auditMeta($fresh, $triggerType, $status, $tsStart, $extra),
                        $oldRate,
                        $newRate
                    );

                    if ($rateChanged === true) {
                        $this->dispatchNotice('rate_changed', $fresh, [
                            'trigger' => $triggerType,
                            'diff_percent' => $rateDiffPercent,
                            'old_course_display' => $oldCourseDisplay,
                            'new_course_display' => $newCourseDisplay,
                            'policy_id' => (int) $policy->id,
                            'mode' => 'policy',
                        ]);
                    }

                    if ((bool) $policy->stop_further) {
                        return;
                    }
                } catch (Throwable $e) {
                    $this->afterFail($state, $now);

                    $this->audit(
                        $taskId,
                        (int) $policy->id,
                        'failed',
                        'exception',
                        $this->auditMeta($task, $triggerType, $status, $tsStart, [
                            'message' => $e->getMessage(),
                            'policy_title' => (string) $policy->title,
                            'policy_scope_type' => (string) ($policy->scope_type ?? ''),
                            'policy_scope_id' => (int) ($policy->scope_id ?? 0),
                            'old_course_display' => $oldCourseDisplay,
                        ]),
                        null,
                        null
                    );

                    $this->dispatchNotice('failed', $task, [
                        'trigger' => $triggerType,
                        'policy_id' => (int) $policy->id,
                        'message' => $e->getMessage(),
                        'mode' => 'policy',
                    ]);
                }
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * FIX пересчёт по настройкам direction_exchange (fix_is_enable_recount, fix_fee_statuses, fix_fee_time).
     *
     * Важно:
     * - FIX режим определяем по заявке: task.type_rate != 1
     * - Для status сравнения используем int[] + strict in_array
     * - FIX не использует калькулятор, calcMs остаётся null
     */
    private function tryDirectionFix(
        Task $task,
        OrderRecountState $state,
        ?int &$calcMs = null,
        ?int &$recountMs = null,
        ?string &$oldCourseDisplay = null,
        ?string &$newCourseDisplay = null,
        ?string &$oldRate = null,
        ?string &$newRate = null
    ): bool {
        $dir = $task->direction_exchange;
        if (!$dir) {
            return false;
        }

        $oldCourseDisplay = trim((string)($task->course_display ?? ''));
        $newCourseDisplay = null;

        $oldRate = $this->rates->currentRate($task);
        $newRate = null;

        // direction/task должны быть type-rate
        if ((int)($dir->is_type_rate ?? 0) !== 1) return false;
        if ((int)($task->is_type_rate ?? 0) !== 1) return false;

        // заявка НЕ floating (fixed/other mode)
        if ((int)($task->type_rate ?? 0) === 1) return false;

        // включён ли FIX пересчёт
        if ((int)($dir->fix_is_enable_recount ?? 0) !== 1) {
            return false;
        }

        $statuses = $dir->fix_fee_statuses ?? [];
        if (!is_array($statuses) || $statuses === []) {
            return false;
        }

        $statuses = array_values(array_unique(array_map('intval', $statuses)));
        if (!in_array((int)$task->status, $statuses, true)) {
            return false;
        }

        $minutes = (int)($dir->fix_fee_time ?? 0);
        if ($minutes > 0 && $state->last_recalculated_at_floating !== null) {
            $next = $state->last_recalculated_at_floating->addMinutes($minutes);
            if (!CarbonImmutable::now()->gt($next)) {
                return false;
            }
        }

        $tRec = hrtime(true);
        $this->executor->recount($task, 2);
        $recountMs = (int)((hrtime(true) - $tRec) / 1_000_000);

        $fresh = $task->fresh() ?? $task;
        $newCourseDisplay = trim((string)($fresh->course_display ?? ''));
        $newRate = $this->rates->currentRate($fresh);

        return true;
    }

    /**
     * Floating пересчёт по настройкам direction_exchange.
     *
     * Важно:
     * - Срабатывает только если task.type_rate === 1 (иначе FIX может ошибочно попадать сюда)
     * - Для status сравнения используем int[] + strict in_array
     */
    private function tryDirectionFloating(
        Task $task,
        OrderRecountState $state,
        ?int &$calcMs = null,
        ?int &$recountMs = null,
        ?string &$oldCourseDisplay = null,
        ?string &$newCourseDisplay = null,
        ?string &$oldRate = null,
        ?string &$newRate = null
    ): bool {
        $dir = $task->direction_exchange;
        if (!$dir) {
            return false;
        }

        $oldCourseDisplay = trim((string) ($task->course_display ?? ''));
        $newCourseDisplay = null;

        $oldRate = $this->rates->currentRate($task);
        $newRate = null;

        // направление и заявка должны быть type-rate
        if ((int) ($dir->is_type_rate ?? 0) !== 1) return false;
        if ((int) ($task->is_type_rate ?? 0) !== 1) return false;

        // заявка должна быть именно floating
        if ((int) ($task->type_rate ?? 0) !== 1) return false;

        $statuses = $dir->floating_fee_statuses ?? [];
        if (!is_array($statuses) || $statuses === []) {
            return false;
        }

        $statuses = array_values(array_unique(array_map('intval', $statuses)));
        if (!in_array((int) $task->status, $statuses, true)) {
            return false;
        }

        $minutes = (int) ($dir->floating_fee_time ?? 0);
        if ($minutes <= 0) {
            return false;
        }

        if ($state->last_recalculated_at_floating !== null) {
            $next = $state->last_recalculated_at_floating->addMinutes($minutes);
            if (!CarbonImmutable::now()->gt($next)) {
                return false;
            }
        }

        $calcOpts = [
            'type_rate' => (int) ($task->type_rate ?? 0), // будет 1
        ];

        $tCalc = hrtime(true);
        $newRateStr = $this->rates->calculateNewRate($task, $calcOpts);
        $calcMs = (int) ((hrtime(true) - $tCalc) / 1_000_000);

        $newRateFloat = (float) ($newRateStr ?? 0);
        $currentRateFloat = (float) ($task->course_float ?? 0);

        if ($newRateFloat <= 0 || $currentRateFloat <= 0) {
            return false;
        }

        $diff = round((($newRateFloat / $currentRateFloat) - 1) * 100, 6);

        $up = (float) ($dir->floating_threshold_recount_up ?? 0);
        $down = (float) ($dir->floating_threshold_recount_down ?? 0);

        // защита от кривых настроек
        if ($up < 0) $up = abs($up);
        if ($down > 0) $down = -abs($down);

        // “мертвая зона” между down..up
        if ($diff < $up && $diff > $down) {
            return false;
        }

        $tRec = hrtime(true);
        $this->executor->recount($task, 1);
        $recountMs = (int) ((hrtime(true) - $tRec) / 1_000_000);

        $fresh = $task->fresh() ?? $task;
        $newCourseDisplay = trim((string) ($fresh->course_display ?? ''));
        $newRate = $this->rates->currentRate($fresh);

        return true;
    }

    /**
     * Возвращает:
     *  - rate_changed (bool)
     *  - rate_diff_percent (float|null)
     */
    private function computeRateChange(?string $oldRate, ?string $newRate, string $oldDisplay, string $newDisplay): array
    {
        $oldF = $oldRate !== null ? (float)$oldRate : 0.0;
        $newF = $newRate !== null ? (float)$newRate : 0.0;

        $rateChanged = false;
        $rateDiffPercent = null;

        if ($oldF > 0 && $newF > 0) {
            $rateDiffPercent = round((($newF / $oldF) - 1) * 100, 8);
            $rateChanged = abs($rateDiffPercent) > 0.00000001;
            return [$rateChanged, $rateDiffPercent];
        }

        if ($oldDisplay !== '' && $newDisplay !== '' && $oldDisplay !== $newDisplay) {
            $rateChanged = true;
        }

        return [$rateChanged, null];
    }

    /**
     * Нормализует значение суммы (строка/число) в float.
     *
     * Использовать только для:
     *  - условий/сравнений
     *  - логов/аудита
     *
     * Не использовать для хранения денежных значений.
     */
    private function normalizeAmountToFloat(string|int|float|null $raw): float
    {
        if ($raw === null) return 0.0;

        $s = trim((string) $raw);
        if ($s === '') return 0.0;

        $s = str_replace(',', '.', $s);
        $s = preg_replace('/[^\d.]/', '', $s) ?? '';

        $pos = strpos($s, '.');
        if ($pos !== false) {
            $s = substr($s, 0, $pos + 1) . str_replace('.', '', substr($s, $pos + 1));
        }

        $v = (float) $s;
        return $v > 0 ? $v : 0.0;
    }

    /**
     * Проверка условия (AND).
     *
     * Поддерживает:
     * - field_equals
     * - rate_changed_required
     * - min_give_amount
     * - status_in
     * - min_interval_minutes (value / cron / status_change)
     * - rate_percent_change_gte
     * - any_of
     * - max_recounts_in_status
     * - max_age_in_status_minutes
     * - not_flag
     */
    private function checkCondition(Task $task, OrderRecountState $state, int $status, string $triggerType, array $cond): array
    {
        $type = (string) ($cond['type'] ?? '');
        if ($type === '') {
            return ['ok' => false, 'reason' => 'invalid_condition', 'meta' => ['cond' => $cond]];
        }

        return match ($type) {
            'field_equals' => $this->condFieldEquals($task, (string)($cond['field'] ?? ''), $cond['value'] ?? null),

            'rate_changed_required' => $this->condRateChangedRequired($task, (int)($cond['value'] ?? 0)),
            'min_give_amount' => $this->condMinGiveAmount($task, (float)($cond['value'] ?? 0)),

            'status_in' => $this->condStatusIn($status, (array)($cond['value'] ?? [])),
            'min_interval_minutes' => $this->condMinInterval($state, $cond, 'global', $triggerType),

            'rate_percent_change_gte' => $this->condPercentChange(
                $task,
                $state,
                (float)($cond['value'] ?? 0),
                (string)($cond['base'] ?? 'last_rate_value')
            ),

            'any_of' => $this->condAnyOf($task, $state, $status, $triggerType, (array)($cond['value'] ?? [])),
            'max_recounts_in_status' => $this->condMaxRecountsInStatus($state, (int)($cond['value'] ?? 0), 'global'),
            'max_age_in_status_minutes' => $this->condMaxAgeInStatus($state, (int)($cond['value'] ?? 0), 'global'),
            'not_flag' => $this->condNotFlag($task, (string)($cond['value'] ?? '')),
            default => ['ok' => false, 'reason' => 'unknown_condition', 'meta' => ['type' => $type]],
        };
    }

    private function condFieldEquals(Task $task, string $field, mixed $value): array
    {
        if ($field === '') return ['ok' => false, 'reason' => 'field_missing', 'meta' => []];

        $current = $task->{$field} ?? null;
        $ok = (string) $current === (string) $value;

        return $ok
            ? ['ok' => true, 'reason' => 'ok', 'meta' => []]
            : ['ok' => false, 'reason' => 'field_not_equal', 'meta' => [
                'field' => $field,
                'current' => $current,
                'expected' => $value,
            ]];
    }

    /**
     * Пересчитывать только если курс изменился.
     * Если калькулятор не дал курс — условие не блокирует пересчёт.
     */
    private function condRateChangedRequired(Task $task, int $enabled): array
    {
        if ($enabled !== 1) {
            return ['ok' => true, 'reason' => 'ok', 'meta' => []];
        }

        $currentStr = trim((string)($task->course_float ?? ''));
        $current = $currentStr !== '' ? (float)$currentStr : 0.0;

        if ($current <= 0) {
            return ['ok' => true, 'reason' => 'ok', 'meta' => [
                'note' => 'current_rate_missing',
                'current' => $currentStr,
            ]];
        }

        $t0 = hrtime(true);
        $newStr = $this->rates->calculateNewRate($task, []);
        $calcMs = (int)((hrtime(true) - $t0) / 1_000_000);

        $new = $newStr !== null && trim($newStr) !== '' ? (float)$newStr : 0.0;
        if ($new <= 0) {
            return ['ok' => true, 'reason' => 'ok', 'meta' => [
                'note' => 'new_rate_unavailable',
                'calc_ms' => $calcMs,
            ]];
        }

        $diffPercent = round((($new / $current) - 1) * 100, 8);
        $changed = abs($diffPercent) > 0.00000001;

        if (!$changed) {
            return ['ok' => false, 'reason' => 'rate_not_changed', 'meta' => [
                'calc_ms' => $calcMs,
                'diff_percent' => $diffPercent,
                'current' => $currentStr,
                'new' => $newStr,
            ]];
        }

        return ['ok' => true, 'reason' => 'ok', 'meta' => [
            'calc_ms' => $calcMs,
            'diff_percent' => $diffPercent,
            'current' => $currentStr,
            'new' => $newStr,
        ]];
    }

    /**
     * Минимальная сумма "Отдаю" (give) >= N.
     * Используем give_price_with_comm (если пусто — fallback на give_price).
     */
    private function condMinGiveAmount(Task $task, float $min): array
    {
        $min = max(0.0, $min);

        if ($min <= 0) {
            return ['ok' => true, 'reason' => 'ok', 'meta' => []];
        }

        $raw = trim((string)($task->give_price_with_comm ?? ''));
        if ($raw === '') {
            $raw = trim((string)($task->give_price ?? ''));
        }

        $give = $this->normalizeAmountToFloat($raw);

        if ($give < $min) {
            return ['ok' => false, 'reason' => 'min_give_not_reached', 'meta' => [
                'min' => $min,
                'give' => $give,
                'raw' => $raw,
            ]];
        }

        return ['ok' => true, 'reason' => 'ok', 'meta' => [
            'min' => $min,
            'give' => $give,
        ]];
    }

    private function condStatusIn(int $status, array $allowed): array
    {
        $allowed = array_values(array_unique(array_map('intval', $allowed)));
        if ($allowed === []) return ['ok' => false, 'reason' => 'status_in_empty', 'meta' => []];

        return in_array($status, $allowed, true)
            ? ['ok' => true, 'reason' => 'ok', 'meta' => []]
            : ['ok' => false, 'reason' => 'status_not_allowed', 'meta' => [
                'current' => $status,
                'allowed' => $allowed,
            ]];
    }

    /**
     * Интервал пересчёта с поддержкой разных лимитов для cron и status-change.
     *
     * Форматы:
     *  - {type:'min_interval_minutes', value: 5}
     *  - {type:'min_interval_minutes', cron: 5, status_change: 1}
     */
    private function condMinInterval(OrderRecountState $state, array $cond, string $channel, string $triggerType): array
    {
        $minutes = null;

        if (array_key_exists('value', $cond)) $minutes = (int) $cond['value'];
        if ($triggerType === 'cron' && array_key_exists('cron', $cond)) $minutes = (int) $cond['cron'];
        if ($triggerType === 'status-change' && array_key_exists('status_change', $cond)) $minutes = (int) $cond['status_change'];

        if ($minutes === null || $minutes <= 0) {
            return ['ok' => true, 'reason' => 'ok', 'meta' => []];
        }

        $last = $channel === 'floating'
            ? $state->last_recalculated_at_floating
            : $state->last_recalculated_at_global;

        if ($last === null) return ['ok' => true, 'reason' => 'ok', 'meta' => ['last' => null]];

        $passed = $last->diffInMinutes(CarbonImmutable::now());

        return ($passed >= $minutes)
            ? ['ok' => true, 'reason' => 'ok', 'meta' => []]
            : ['ok' => false, 'reason' => 'interval_not_reached', 'meta' => [
                'required' => $minutes,
                'passed' => $passed,
                'trigger' => $triggerType,
                'channel' => $channel,
            ]];
    }

    private function condPercentChange(Task $task, OrderRecountState $state, float $threshold, string $baseType): array
    {
        if ($threshold <= 0) return ['ok' => true, 'reason' => 'ok', 'meta' => []];

        $baseStr = null;
        if ($baseType === 'last_rate_value') $baseStr = $state->last_rate_value_global;
        elseif ($baseType === 'order_current') $baseStr = trim((string)($task->course_float ?? ''));

        $base = (float)($baseStr ?? 0);
        if ($base <= 0) {
            return ['ok' => false, 'reason' => 'base_rate_missing', 'meta' => ['base' => $baseStr]];
        }

        $tCalc = hrtime(true);
        $newStr = $this->rates->calculateNewRate($task, []);
        $calcMs = (int)((hrtime(true) - $tCalc) / 1_000_000);

        $new = (float)($newStr ?? 0);
        if ($new <= 0) {
            return ['ok' => false, 'reason' => 'new_rate_unavailable', 'meta' => ['calc_ms' => $calcMs]];
        }

        $pct = abs(($new / $base - 1) * 100);

        return ($pct >= $threshold)
            ? ['ok' => true, 'reason' => 'ok', 'meta' => ['percent' => round($pct, 6), 'calc_ms' => $calcMs]]
            : ['ok' => false, 'reason' => 'percent_not_reached', 'meta' => [
                'percent' => round($pct, 6),
                'threshold' => $threshold,
                'calc_ms' => $calcMs,
            ]];
    }

    private function condAnyOf(Task $task, OrderRecountState $state, int $status, string $triggerType, array $items): array
    {
        if ($items === []) return ['ok' => true, 'reason' => 'ok', 'meta' => []];

        $fails = [];
        foreach ($this->sortAnyOf($items) as $it) {
            $r = $this->checkCondition($task, $state, $status, $triggerType, (array)$it);
            if (($r['ok'] ?? false) === true) {
                return ['ok' => true, 'reason' => 'ok', 'meta' => ['matched' => $it['type'] ?? '']];
            }
            $fails[] = ['type' => $it['type'] ?? '', 'reason' => $r['reason'] ?? 'failed'];
        }

        return ['ok' => false, 'reason' => 'any_of_not_matched', 'meta' => ['fails' => $fails]];
    }

    private function sortAnyOf(array $items): array
    {
        $cost = [
            'field_equals' => 1,
            'rate_changed_required' => 2,
            'min_give_amount' => 2,
            'min_interval_minutes' => 3,
            'max_recounts_in_status' => 3,
            'max_age_in_status_minutes' => 3,
            'status_in' => 3,
            'not_flag' => 3,
            'rate_percent_change_gte' => 10,
        ];

        usort($items, function ($a, $b) use ($cost) {
            $ta = (string)($a['type'] ?? '');
            $tb = (string)($b['type'] ?? '');
            return ($cost[$ta] ?? 5) <=> ($cost[$tb] ?? 5);
        });

        return $items;
    }

    private function condMaxRecountsInStatus(OrderRecountState $state, int $limit, string $channel): array
    {
        if ($limit <= 0) return ['ok' => true, 'reason' => 'ok', 'meta' => []];

        $count = $channel === 'floating'
            ? (int)($state->recount_in_status_count_floating ?? 0)
            : (int)($state->recount_in_status_count_global ?? 0);

        return ($count < $limit)
            ? ['ok' => true, 'reason' => 'ok', 'meta' => []]
            : ['ok' => false, 'reason' => 'max_recounts_reached', 'meta' => [
                'limit' => $limit,
                'count' => $count,
                'channel' => $channel
            ]];
    }

    private function condMaxAgeInStatus(OrderRecountState $state, int $limitMinutes, string $channel): array
    {
        if ($limitMinutes <= 0) return ['ok' => true, 'reason' => 'ok', 'meta' => []];

        $enteredAt = $channel === 'floating'
            ? ($state->status_entered_at_floating ?? null)
            : ($state->status_entered_at_global ?? null);

        if ($enteredAt === null) {
            return ['ok' => true, 'reason' => 'ok', 'meta' => ['entered_at' => null, 'channel' => $channel]];
        }

        $age = $enteredAt->diffInMinutes(CarbonImmutable::now());

        return ($age <= $limitMinutes)
            ? ['ok' => true, 'reason' => 'ok', 'meta' => []]
            : ['ok' => false, 'reason' => 'status_age_exceeded', 'meta' => [
                'limit' => $limitMinutes,
                'age' => $age,
                'channel' => $channel
            ]];
    }

    private function condNotFlag(Task $task, string $field): array
    {
        if ($field === '') return ['ok' => false, 'reason' => 'flag_field_missing', 'meta' => []];

        $v = (int)($task->{$field} ?? 0);

        return ($v !== 1)
            ? ['ok' => true, 'reason' => 'ok', 'meta' => []]
            : ['ok' => false, 'reason' => 'flag_set', 'meta' => ['field' => $field]];
    }

    private function afterSuccess(Task $task, OrderRecountState $state, CarbonImmutable $now, string $channel): void
    {
        $rate = trim((string)($task->course_float ?? ''));

        if ($channel === 'floating') {
            $state->last_recalculated_at_floating = $now;
            $state->last_rate_value_floating = $rate !== '' ? $rate : $state->last_rate_value_floating;
            $state->recount_in_status_count_floating = (int)($state->recount_in_status_count_floating ?? 0) + 1;
        } else {
            $state->last_recalculated_at_global = $now;
            $state->last_rate_value_global = $rate !== '' ? $rate : $state->last_rate_value_global;
            $state->recount_in_status_count_global = (int)($state->recount_in_status_count_global ?? 0) + 1;
        }

        $state->fail_streak = 0;
        $state->save();
    }

    private function afterFail(OrderRecountState $state, CarbonImmutable $now): void
    {
        $state->fail_streak = (int)$state->fail_streak + 1;

        $lockAfter = (int) config('order-recount.fail.lock_after_fail_streak', 5);
        $lockMinutes = (int) config('order-recount.fail.lock_minutes', 10);

        if ($state->fail_streak >= $lockAfter) {
            $state->locked_until = $now->addMinutes($lockMinutes);
        }

        $state->save();
    }

    private function audit(int $taskId, ?int $policyId, string $decision, string $reasonCode, array $meta, ?string $oldRate, ?string $newRate): void
    {
        OrderRecountAudit::query()->create([
            'task_id' => $taskId,
            'policy_id' => $policyId,
            'decision' => $decision,
            'reason_code' => $reasonCode,
            'meta' => $meta === [] ? null : $meta,
            'old_rate' => $oldRate,
            'new_rate' => $newRate,
        ]);
    }

    private function auditContext(Task $task, string $triggerType, int $status): array
    {
        $dir = $task->direction_exchange;
        $cur = $dir?->currency1;

        return [
            'trigger' => $triggerType,
            'status'  => $status,

            'direction_id'   => (int)($dir?->id ?? 0),
            'direction_name' => (string)($dir?->tech_name ?? ''),

            'currency_id'    => (int)($cur?->id ?? 0),
            'currency_name'  => (string)($cur?->tech_name ?? $cur?->small_code ?? ''),
        ];
    }

    private function auditMeta(Task $task, string $triggerType, int $status, int $tsStart, array $extra = []): array
    {
        $perfMs = (int)((hrtime(true) - $tsStart) / 1_000_000);
        return array_merge($this->auditContext($task, $triggerType, $status), ['perf_ms' => $perfMs], $extra);
    }

    private function syncStatusSession(OrderRecountState $state, int $status, CarbonImmutable $now, string $channel): void
    {
        if ($channel === 'floating') {
            if ($state->status_last_floating === null || (int)$state->status_last_floating !== $status) {
                $state->status_last_floating = $status;
                $state->status_entered_at_floating = $now;
                $state->recount_in_status_count_floating = 0;
            }
            return;
        }

        if ($state->status_last_global === null || (int)$state->status_last_global !== $status) {
            $state->status_last_global = $status;
            $state->status_entered_at_global = $now;
            $state->recount_in_status_count_global = 0;
        }
    }

    /**
     * Event-хук. Ты сам подключаешь listener и решаешь, куда отправлять уведомления.
     */
    private function dispatchNotice(string $type, Task $task, array $payload): void
    {
        $class = '\\iEXPackages\\OrderRecount\\Events\\OrderRecountNotice';

        if (!class_exists($class)) {
            return;
        }

        event(new $class($type, $task, $payload));
    }
}
