<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Basic\Currency\Others;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\TaskStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use iEXPackages\OrderRecount\Models\OrderRecountPolicy;

/**
 * CurrencyRecountController
 *
 * Индивидуальные правила пересчёта для валюты.
 *
 * Принцип:
 * - Правила хранятся в order_recount_policies (scope_type=currency, scope_id=currency_id).
 * - Если индивидуальные правила выключены, применяется общий пересчёт.
 *
 * Важно:
 * - Engine поддерживает раздельные интервалы cron/status-change.
 * - UI пока отдаёт одно поле recount_time_minutes, поэтому сохраняем:
 *   cron = recount_time_minutes
 *   status_change = 0
 *
 * Ограничение безопасности:
 * - Freeze-safe включён всегда: пересчёт запрещён, если task.is_frozen = 1.
 */
final class CurrencyRecountController extends Controller
{
    public function edit(int $id): JsonResponse
    {
        $currency = Currency::query()->findOrFail($id);

        $statusOrders = TaskStatus::whereNotIn('id', [4])
            ->pluck('name', 'id')
            ->map(fn ($value, $sid) => ['id' => (int) $sid, 'value' => (string) $value])
            ->values();

        return response()->json([
            'options' => [
                'statuses' => $statusOrders,
            ],
            'attributes' => $this->loadCurrencyRecountSettings($currency),
            'default' => [
                'tech_name' => (string) ($currency->tech_name ?? ''),
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $currency = Currency::query()->findOrFail($id);

        $request->validate([
            'is_unique_recount_order'      => ['nullable'],
            'recount_statusses'            => ['nullable'],
            'is_enable_auto_recount_order' => ['nullable'],
            'recount_time_minutes'         => ['nullable'],
            'unique_recount_percent'       => ['nullable'],
            'max_recounts_in_status'       => ['nullable'],
            'max_age_in_status_minutes'    => ['nullable'],
            'recount_course_text'          => ['nullable', 'array'],

            'only_if_rate_changed'         => ['nullable'],
            'min_give_amount'              => ['nullable'],
        ]);

        $enabled  = (int) $request->input('is_unique_recount_order', 0) === 1;
        $mode     = (int) $request->input('is_enable_auto_recount_order', 0);
        $statuses = $this->normalizeIds($request->input('recount_statusses', []));

        // если включено и выбран режим пересчёта (1/2) — статусы обязательны
        if ($enabled && $mode !== 0 && $statuses === []) {
            return response()->json([
                'status' => 1,
                'message' => __('Выберите статусы для пересчёта'),
            ], 422);
        }

        // режим "по условиям" требует хотя бы одного условия (процент или интервал)
        if ($enabled && $mode === 2) {
            $minutesCheck = (int) $request->input('recount_time_minutes', 0);
            $percentCheck = (float) $request->input('unique_recount_percent', 0);

            if ($minutesCheck <= 0 && $percentCheck <= 0) {
                return response()->json([
                    'status' => 1,
                    'message' => __('Для режима "Пересчитывать по условиям" укажите процент изменения курса или минимальный интервал.'),
                ], 422);
            }
        }

        $this->saveCurrencyRecountSettings($request, $currency);

        return response()->json([
            'status' => 0,
            'message' => __('Настройки успешно сохранены'),
        ]);
    }

    private function saveCurrencyRecountSettings(Request $request, Currency $currency): void
    {
        $enabled = (int) $request->input('is_unique_recount_order', 0) === 1;

        // UI-текст под курсом
        if ($request->has('recount_course_text')) {
            $currency->update([
                'recount_course_text' => (array) $request->input('recount_course_text', []),
            ]);
        }

        $policy = OrderRecountPolicy::query()->firstOrNew([
            'scope_type' => 'currency',
            'scope_id' => (int) $currency->id,
        ]);

        if (!$enabled) {
            $policy->fill($this->disabledPolicyPayload())->save();
            return;
        }

        $mode    = (int) $request->input('is_enable_auto_recount_order', 0); // 0|1|2
        $percent = (float) $request->input('unique_recount_percent', 0);
        $minutes = (int) $request->input('recount_time_minutes', 0);

        $maxRecounts = (int) $request->input('max_recounts_in_status', 0);
        $maxAge      = (int) $request->input('max_age_in_status_minutes', 0);

        $statuses = $this->normalizeIds($request->input('recount_statusses', []));

        if ($mode === 0 || $statuses === []) {
            $policy->fill($this->disabledPolicyPayload())->save();
            return;
        }

        $conditions = [];

        // Freeze-safe всегда включён
        $conditions[] = ['type' => 'field_equals', 'field' => 'is_frozen', 'value' => 0];

        // Только если курс изменился (опционально)
        $onlyIfRateChanged = (int) $request->input('only_if_rate_changed', 0);
        if ($onlyIfRateChanged === 1) {
            $conditions[] = ['type' => 'rate_changed_required', 'value' => 1];
        }

        // Минимальная сумма "Отдаю" (опционально)
        $minGive = max(0.0, (float) $request->input('min_give_amount', 0));
        if ($minGive > 0) {
            $conditions[] = ['type' => 'min_give_amount', 'value' => $minGive];
        }

        // Статусы
        $conditions[] = ['type' => 'status_in', 'value' => $statuses];

        // mode=2 => по условиям (процент/интервал)
        if ($mode === 2) {
            $anyOf = [];

            if ($minutes > 0) {
                $anyOf[] = [
                    'type' => 'min_interval_minutes',
                    'cron' => $minutes,
                    'status_change' => 0,
                ];
            }

            if ($percent > 0) {
                $anyOf[] = [
                    'type' => 'rate_percent_change_gte',
                    'value' => $percent,
                    'base' => 'last_rate_value',
                ];
            }

            if ($anyOf !== []) {
                $conditions[] = ['type' => 'any_of', 'value' => $anyOf];
            }
        }

        if ($maxRecounts > 0) {
            $conditions[] = ['type' => 'max_recounts_in_status', 'value' => $maxRecounts];
        }
        if ($maxAge > 0) {
            $conditions[] = ['type' => 'max_age_in_status_minutes', 'value' => $maxAge];
        }

        $policy->fill([
            'is_enabled' => 1,
            'priority' => 10,
            'stop_further' => 1,
            'title' => 'Currency recount',
            'trigger_types' => ['status-change', 'cron', 'manual'],
            'conditions' => $conditions,
            'actions' => [['type' => 'recount', 'at_rate' => 1]],
        ])->save();
    }

    private function loadCurrencyRecountSettings(Currency $currency): array
    {
        $defaults = [
            'is_unique_recount_order' => '0',
            'recount_statusses' => [],
            'is_enable_auto_recount_order' => 0,
            'recount_time_minutes' => 0,
            'unique_recount_percent' => 0,
            'max_recounts_in_status' => 0,
            'max_age_in_status_minutes' => 0,
            'recount_course_text' => $currency->getTranslations('recount_course_text') ?? [],

            'only_if_rate_changed' => 0,
            'min_give_amount' => 0,
        ];

        $policy = OrderRecountPolicy::query()
            ->where('scope_type', 'currency')
            ->where('scope_id', (int) $currency->id)
            ->first();

        if (!$policy || (int) $policy->is_enabled !== 1) {
            return $defaults;
        }

        $mode = 1;
        $percent = 0.0;
        $minutes = 0;
        $statuses = [];
        $maxRecounts = 0;
        $maxAge = 0;

        $onlyIfRateChanged = 0;
        $minGiveAmount = 0.0;

        foreach ((array) $policy->conditions as $cond) {
            $type = (string) ($cond['type'] ?? '');

            if ($type === 'status_in') {
                $statuses = (array) ($cond['value'] ?? []);
                continue;
            }

            if ($type === 'rate_changed_required') {
                $onlyIfRateChanged = ((int) ($cond['value'] ?? 0) === 1) ? 1 : 0;
                continue;
            }

            if ($type === 'min_give_amount') {
                $minGiveAmount = max(0.0, (float) ($cond['value'] ?? 0));
                continue;
            }

            if ($type === 'any_of') {
                $mode = 2;

                foreach ((array) ($cond['value'] ?? []) as $it) {
                    if (($it['type'] ?? '') === 'min_interval_minutes') {
                        if (isset($it['value'])) {
                            $minutes = (int) ($it['value'] ?? 0);
                        }
                        if (isset($it['cron'])) {
                            $minutes = (int) ($it['cron'] ?? 0);
                        }
                    }

                    if (($it['type'] ?? '') === 'rate_percent_change_gte') {
                        $percent = (float) ($it['value'] ?? 0);
                    }
                }

                continue;
            }

            if ($type === 'max_recounts_in_status') {
                $maxRecounts = (int) ($cond['value'] ?? 0);
                continue;
            }

            if ($type === 'max_age_in_status_minutes') {
                $maxAge = (int) ($cond['value'] ?? 0);
                continue;
            }
        }

        return [
            'is_unique_recount_order' => '1',
            'recount_statusses' => $this->normalizeIds($statuses),
            'is_enable_auto_recount_order' => $mode,
            'recount_time_minutes' => $minutes,
            'unique_recount_percent' => $percent,
            'max_recounts_in_status' => $maxRecounts,
            'max_age_in_status_minutes' => $maxAge,
            'recount_course_text' => $currency->getTranslations('recount_course_text') ?? [],

            'only_if_rate_changed' => $onlyIfRateChanged,
            'min_give_amount' => $minGiveAmount,
        ];
    }

    private function disabledPolicyPayload(): array
    {
        return [
            'is_enabled' => 0,
            'priority' => 10,
            'stop_further' => 1,
            'title' => 'Currency recount',
            'trigger_types' => ['status-change', 'cron', 'manual'],
            'conditions' => [],
            'actions' => [['type' => 'recount', 'at_rate' => 1]],
        ];
    }

    private function normalizeIds(mixed $raw): array
    {
        if ($raw instanceof \Illuminate\Support\Collection) {
            $raw = $raw->all();
        }

        if (is_string($raw)) {
            $raw = explode(',', $raw);
        } elseif (!is_array($raw)) {
            $raw = [];
        }

        $out = [];
        foreach ($raw as $v) {
            if (is_array($v) && array_key_exists('id', $v)) {
                $v = $v['id'];
            }
            if (is_object($v) && isset($v->id)) {
                $v = $v->id;
            }

            $i = (int) $v;
            if ($i > 0) {
                $out[$i] = true;
            }
        }

        return array_keys($out);
    }
}
