<?php

namespace App\Http\Controllers\Administrator\Basic\Currency\Automation;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\GatewayMerchant;
use App\Support\ValidatorWallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyMerchantsController extends Controller
{
    /**
     * GET /admin/currencies/{id}/automation/merchants (alias: edit)
     * Возвращает options + attributes для вкладки «Мерчанты»
     */
    public function edit(int $id): JsonResponse
    {
        $item = Currency::query()->with(['merchants'])->findOrFail($id);

        // Список мерчантов (options)
        $merchants = GatewayMerchant::query()
            ->select('id', 'name', 'alias', 'status')
            ->orderByDesc('status')
            ->orderBy('name')
            ->get()
            ->map(function ($m) {
                $statusText = ((int)$m->status === 1) ? __('активна') : __('не активна');
                $aliasPart  = $m->alias ? ' — ' . $m->alias : '';
                return [
                    'id'     => (int)$m->id,
                    'name'   => (string)$m->name,
                    'alias'  => (string)$m->alias,
                    'status' => (int)$m->status,
                    'value'  => sprintf('%s%s • (%s)', $m->name, $aliasPart, $statusText),
                ];
            })
            ->values();


        $validatorLabels = app(ValidatorWallet::class)->getTypeLabels();

        $validators = collect($validatorLabels)
            ->map(function (string $label, string $type) {
                return [
                    'id' => $type,
                    'value' => $label,
                ];
            })
            ->sortBy('value')
            ->values();


        // Attributes текущего Currency
        $attributes = [
            'ids_merchant'               => $item->merchants->pluck('id')->map(fn ($v) => (int)$v)->values(),
            'merchant_networks'          => $item->merchants
                ->mapWithKeys(function ($m) {
                    $code = isset($m->pivot) ? $m->pivot->network_code : null;
                    return [(int)$m->id => (string)($code ?? '')];
                })->toArray(),
            'merchant_validators'        => $item->merchants
                ->mapWithKeys(function ($m) {
                    $type = isset($m->pivot) ? ($m->pivot->validator_type ?? null) : null;
                    return [(int)$m->id => (string)($type ?? '')];
                })->toArray(),
            'merchant_validator_fail_strategy' => (int)($item->merchant_validator_fail_strategy ?? 1),
            'network_code'               => (string)($item->network_code ?? ''),
            'merchant_day_limit_amount'  => (float)($item->merchant_day_limit_amount ?? 0),
            'merchant_month_limit_amount'=> (float)($item->merchant_month_limit_amount ?? 0),
            'merchant_min_amount_for_order'=> (float)($item->merchant_min_amount_for_order ?? 0),
            'merchant_max_amount_for_order'=> (float)($item->merchant_max_amount_for_order ?? 0),
            'merchant_day_limit'         => (int)($item->merchant_day_limit ?? 0),
            'merchant_month_limit'       => (int)($item->merchant_month_limit ?? 0),
        ];

        // Доп. блок для удобства фронта (если требуется)
        $default = [
            'tech_name' => (string)($item->tech_name ?? ''),
        ];

        return response()->json([
            'options'    => ['merchants' => $merchants, 'validators' => $validators],
            'attributes' => $attributes,
            'default'    => $default,
        ]);
    }

    /**
     * PUT /admin/currencies/{id}/automation/merchants (alias: update)
     * Сохраняет выбор мерчантов + pivot.network_code и скалярные лимиты.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = Currency::query()->findOrFail($id);

        // Валидация на месте (можно вынести в FormRequest при желании)
        $validated = $request->validate([
            'ids_merchant'                  => ['nullable','array'],
            'ids_merchant.*'                => ['integer'],
            'merchant_networks'             => ['nullable','array'],
            'merchant_networks.*'           => ['nullable','string','max:64'],
            'merchant_validators'           => ['nullable','array'],
            'merchant_validators.*'         => ['nullable','string','max:64'],
            'merchant_validator_fail_strategy'   => ['nullable','integer','in:0,1'],
            'network_code'                  => ['nullable','string','max:64'],
            'merchant_day_limit_amount'     => ['nullable','numeric','min:0'],
            'merchant_month_limit_amount'   => ['nullable','numeric','min:0'],
            'merchant_min_amount_for_order' => ['nullable','numeric','min:0'],
            'merchant_max_amount_for_order' => ['nullable','numeric','min:0'],
            'merchant_day_limit'            => ['nullable','integer','min:0'],
            'merchant_month_limit'          => ['nullable','integer','min:0'],
        ]);

        // 1) Синхронизация мерчантов с pivot.network_code
        $ids        = array_map('intval', (array)($validated['ids_merchant'] ?? []));
        $overrides  = (array)($validated['merchant_networks'] ?? []);
        $validators = (array)($validated['merchant_validators'] ?? []);

        // Построение pivot-массива: [merchant_id => ['network_code' => 'TRC20', 'validator_type' => ...]]
        $pivot = [];
        foreach ($ids as $mid) {
            $raw  = $overrides[$mid] ?? ($overrides[(string)$mid] ?? '');
            $code = is_string($raw) ? trim($raw) : '';
            $rawV = $validators[$mid] ?? ($validators[(string)$mid] ?? '');
            $val  = is_string($rawV) ? trim($rawV) : '';
            $pivot[(int)$mid] = [
                'network_code'   => ($code !== '' ? $code : null),
                'validator_type' => ($val !== '' ? $val : null),
            ];
        }
        $item->merchants()->sync($pivot);

        // 2) Обновление скалярных полей Currency (если пришли)
        $scalarFields = [
            'network_code',
            'merchant_validator_fail_strategy',
            'merchant_day_limit_amount',
            'merchant_month_limit_amount',
            'merchant_min_amount_for_order',
            'merchant_max_amount_for_order',
            'merchant_day_limit',
            'merchant_month_limit',
        ];

        $updateData = [];
        foreach ($scalarFields as $field) {
            if (array_key_exists($field, $validated)) {
                $updateData[$field] = $validated[$field];
            }
        }

        if (!empty($updateData)) {
            $item->update($updateData);
        }

        return response()->json([
            'status'  => 0,
            'message' => __('Мерчанты успешно обновлены'),
        ]);
    }
}
