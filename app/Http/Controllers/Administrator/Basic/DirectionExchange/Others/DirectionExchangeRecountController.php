<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Basic\DirectionExchange\Others;

use App\Models\DirectionExchange;
use App\Models\TaskStatus;
use App\Services\Rates\CanonicalDirectionRateCalculator;
use App\Services\Rates\RateChannel;
use App\Services\Rates\RateMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DirectionExchangeRecountController
 *
 * Вкладка «Пересчёт заявок» для направления.
 *
 * Важно:
 * - Эти настройки НЕ являются policies. Они остаются “направленческими” и используются
 *   строго для механики FIX/FLOATING внутри direction_exchange.
 * - В OrderRecountEngine используется только FLOATING-ветка (если заявка реально плавающая),
 *   параметры читаются из direction_exchange.* (floating_*).
 * - Для автоматических направлений floating_fee/fix_fee — единственная коммерческая %‑корректировка
 *   поверх рыночного BASE (course_value).
 */
final class DirectionExchangeRecountController
{
    /**
     * GET /admin/directions/{id}/recount
     *
     * Возвращает:
     * - options: статусы заявок (для multiselect/select) + rateAuthority panel metadata
     * - attributes: поля из direction_exchange для вкладки recount
     */
    public function edit(int $id): JsonResponse
    {
        $item = DirectionExchange::query()->findOrFail($id);

        $statusOrders = TaskStatus::query()
            ->whereNotIn('id', [4])
            ->pluck('name', 'id')
            ->map(fn ($name, $sid) => ['id' => (int) $sid, 'value' => (string) $name])
            ->values();

        $calc = CanonicalDirectionRateCalculator::make();
        $float = $calc->calculate($item, RateMode::Floating, RateChannel::Website);
        $fixed = $calc->calculate($item, RateMode::Fixed, RateChannel::Website);
        $parser = (string) ($item->parser_source_name ?? '');
        $automatic = $parser !== '' && ! preg_match('/ручн|manual/iu', $parser);

        return response()->json([
            'options' => [
                'orderStatus' => $statusOrders,
                'rateAuthority' => [
                    'source' => $automatic ? 'automatic' : 'manual',
                    'source_label' => $automatic ? 'Автоматический' : 'Ручной',
                    'parser_source_name' => $parser,
                    'automatic_base_rate' => (string) ($item->course_value ?? '0'),
                    'calculated_floating' => $float->finalRate,
                    'calculated_fixed' => $fixed->finalRate,
                    'floating_field' => 'floating_fee',
                    'fixed_field' => 'fix_fee',
                    'manual_rate_is_authority' => ! $automatic,
                    'help' => $automatic
                        ? 'Рыночный BASE обновляется автоматически. Меняйте только проценты floating_fee/fix_fee.'
                        : 'Для ручных направлений market BASE не гарантирован.',
                ],
            ],
            'attributes' => [
                'is_type_rate' => (int) ($item->is_type_rate ?? 0),
                'type_rate_description' => $item->getTranslations('type_rate_description'),
                'fix_fee' => (string) ($item->fix_fee ?? '0'),
                'fix_fee_display' => (int) ($item->fix_fee_display ?? 0),
                'fix_is_enable_recount' => (string) ($item->fix_is_enable_recount ?? '0'),
                'fix_fee_statuses' => normalize_ids($item->fix_fee_statuses ?? []),
                'fix_fee_time' => (int) ($item->fix_fee_time ?? 0),
                'floating_fee' => (string) ($item->floating_fee ?? '0'),
                'floating_fee_display' => (int) ($item->floating_fee_display ?? 0),
                'floating_fee_statuses' => normalize_ids($item->floating_fee_statuses ?? []),
                'floating_fee_time' => (int) ($item->floating_fee_time ?? 0),
                'floating_threshold_recount_up' => (float) ($item->floating_threshold_recount_up ?? 0),
                'floating_threshold_recount_down' => (float) ($item->floating_threshold_recount_down ?? 0),
                'file_rate_source' => (string) ($item->file_rate_source ?? 'fix'),
                'automatic_base_rate' => (string) ($item->course_value ?? '0'),
                'parser_source_name' => $parser,
            ],
            'default' => [
                'tech_name' => (string) ($item->tech_name ?? ''),
            ],
        ]);
    }

    /**
     * PUT /admin/directions/{id}/recount
     *
     * Сохраняет настройки вкладки recount в direction_exchange.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = DirectionExchange::query()->findOrFail($id);

        $data = $request->validate([
            'is_type_rate' => ['nullable', 'integer'],

            'type_rate_description' => ['nullable', 'array'],

            // FIX
            'fix_fee' => ['nullable', 'string'],
            'fix_fee_display' => ['nullable'],
            'fix_is_enable_recount' => ['nullable'],
            'fix_fee_statuses' => ['nullable'],
            'fix_fee_time' => ['nullable'],

            // FLOATING
            'floating_fee' => ['nullable', 'string'],
            'floating_fee_display' => ['nullable'],
            'floating_fee_statuses' => ['nullable'],
            'floating_fee_time' => ['nullable'],
            'floating_threshold_recount_up' => ['nullable'],
            'floating_threshold_recount_down' => ['nullable'],

            // file
            'file_rate_source' => ['nullable', 'string'],
        ]);

        // Приведение типов + нормализация массивов статусов (важно: int[])
        $update = [
            'is_type_rate' => (int) ($data['is_type_rate'] ?? ($item->is_type_rate ?? 0)),

            'type_rate_description' => normalizeHtmlFieldValue($data['type_rate_description'] ?? []) ?? [],

            // FIX
            'fix_fee' => (string) ($data['fix_fee'] ?? ($item->fix_fee ?? '0')),
            'fix_fee_display' => (int) ($data['fix_fee_display'] ?? ($item->fix_fee_display ?? 0)),
            'fix_is_enable_recount' => (string) ($data['fix_is_enable_recount'] ?? ($item->fix_is_enable_recount ?? '0')),
            'fix_fee_statuses' => normalize_ids($data['fix_fee_statuses'] ?? ($item->fix_fee_statuses ?? [])),
            'fix_fee_time' => max(0, (int) ($data['fix_fee_time'] ?? ($item->fix_fee_time ?? 0))),

            // FLOATING
            'floating_fee' => (string) ($data['floating_fee'] ?? ($item->floating_fee ?? '0')),
            'floating_fee_display' => (int) ($data['floating_fee_display'] ?? ($item->floating_fee_display ?? 0)),
            'floating_fee_statuses' => normalize_ids($data['floating_fee_statuses'] ?? ($item->floating_fee_statuses ?? [])),
            'floating_fee_time' => max(0, (int) ($data['floating_fee_time'] ?? ($item->floating_fee_time ?? 0))),
            'floating_threshold_recount_up' => (float) ($data['floating_threshold_recount_up'] ?? ($item->floating_threshold_recount_up ?? 0)),
            'floating_threshold_recount_down' => (float) ($data['floating_threshold_recount_down'] ?? ($item->floating_threshold_recount_down ?? 0)),
            'file_rate_source' => (string) ($data['file_rate_source'] ?? ($item->file_rate_source ?? 'fix')),
        ];

        // Маленькая защита от “кривых порогов”
        // up не должен быть отрицательным, down обычно отрицательный
        if ($update['floating_threshold_recount_up'] < 0) {
            $update['floating_threshold_recount_up'] = abs($update['floating_threshold_recount_up']);
        }
        if ($update['floating_threshold_recount_down'] > 0) {
            $update['floating_threshold_recount_down'] = -abs($update['floating_threshold_recount_down']);
        }

        $item->update($update);

        return response()->json([
            'status' => 0,
            'message' => __('Настройки пересчёта заявок для направления успешно обновлены'),
        ]);
    }
}
