<?php

namespace App\Http\Controllers\Administrator\Basic\Currency\Automation;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\GatewayPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyPaysController extends Controller
{
    /**
     * GET /admin/currencies/{id}/automation/pays (alias: edit)
     * Возвращает options + attributes для вкладки «Выплаты»
     */
    public function edit(int $id): JsonResponse
    {
        $item = Currency::query()->with(['gateway_payments'])->findOrFail($id);

        // Список провайдеров выплат (options)
        $pays = GatewayPayment::query()
            ->select('id', 'name', 'alias', 'status')
            ->orderByDesc('status')
            ->orderBy('name')
            ->get()
            ->map(function ($p) {
                $statusText = ((int)$p->status === 1) ? __('активна') : __('не активна');
                $aliasPart  = $p->alias ? ' — ' . $p->alias : '';
                return [
                    'id'     => (int)$p->id,
                    'name'   => (string)$p->name,
                    'alias'  => (string)$p->alias,
                    'status' => (int)$p->status,
                    'value'  => sprintf('%s%s • (%s)', $p->name, $aliasPart, $statusText),
                ];
            })
            ->values();

        // Attributes текущего Currency
        $attributes = [
            'ids_pay'                      => $item->gateway_payments->pluck('id')->map(fn ($v) => (int)$v)->values(),
            'pays_networks'                => $item->gateway_payments
                ->mapWithKeys(function ($g) {
                    $code = isset($g->pivot) ? $g->pivot->network_code : null;
                    return [(int)$g->id => (string)($code ?? '')];
                })->toArray(),
            'network_code_out'             => (string)($item->network_code_out ?? ''),
            'pay_day_limit_amount'         => (float)($item->pay_day_limit_amount ?? 0),
            'pay_month_limit_amount'       => (float)($item->pay_month_limit_amount ?? 0),
            'pay_min_amount_for_order'     => (float)($item->pay_min_amount_for_order ?? 0),
            'pay_max_amount_for_order'     => (float)($item->pay_max_amount_for_order ?? 0),
            'pay_day_limit'                => (int)($item->pay_day_limit ?? 0),
            'pay_month_limit'              => (int)($item->pay_month_limit ?? 0),
            'allow_autopay'                => (int)($item->allow_autopay ?? 0),
        ];

        // Доп. блок для удобства фронта (если требуется)
        $default = [
            'tech_name' => (string)($item->tech_name ?? ''),
        ];

        return response()->json([
            'options'    => ['pays' => $pays],
            'attributes' => $attributes,
            'default'    => $default,
        ]);
    }

    /**
     * PUT /admin/currencies/{id}/automation/pays (alias: update)
     * Сохраняет выбор payout-провайдеров + pivot.network_code и скалярные поля.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = Currency::query()->findOrFail($id);

        // Валидация (можно вынести в FormRequest при необходимости)
        $validated = $request->validate([
            'ids_pay'                    => ['nullable','array'],
            'ids_pay.*'                  => ['integer'],
            'pays_networks'              => ['nullable','array'],
            'pays_networks.*'            => ['nullable','string','max:64'],
            'network_code_out'           => ['nullable','string','max:64'],
            'pay_day_limit_amount'       => ['nullable','numeric','min:0'],
            'pay_month_limit_amount'     => ['nullable','numeric','min:0'],
            'pay_min_amount_for_order'   => ['nullable','numeric','min:0'],
            'pay_max_amount_for_order'   => ['nullable','numeric','min:0'],
            'pay_day_limit'              => ['nullable','integer','min:0'],
            'pay_month_limit'            => ['nullable','integer','min:0'],
            'allow_autopay'              => ['nullable','integer','in:0,1,2'],
        ]);

        // 1) Синхронизация payout-провайдеров с pivot.network_code
        $ids       = array_map('intval', (array)($validated['ids_pay'] ?? []));
        $overrides = (array)($validated['pays_networks'] ?? []);

        $pivot = [];
        foreach ($ids as $pid) {
            $raw  = $overrides[$pid] ?? ($overrides[(string)$pid] ?? '');
            $code = is_string($raw) ? trim($raw) : '';
            $pivot[(int)$pid] = ['network_code' => ($code !== '' ? $code : null)];
        }
        $item->gateway_payments()->sync($pivot);

        // 2) Обновление скалярных полей Currency (если пришли)
        $scalarFields = [
            'network_code_out',
            'pay_day_limit_amount',
            'pay_month_limit_amount',
            'pay_min_amount_for_order',
            'pay_max_amount_for_order',
            'pay_day_limit',
            'pay_month_limit',
            'allow_autopay',
        ];

        $updateData = [];
        foreach ($scalarFields as $field) {
            if (array_key_exists($field, $validated)) {
                $updateData[$field] = $validated[$field];
            }
        }

        if (array_key_exists('allow_autopay', $updateData) && $updateData['allow_autopay'] !== null) {
            $updateData['allow_autopay'] = (int)$updateData['allow_autopay'];
        }

        if (!empty($updateData)) {
            $item->update($updateData);
        }

        return response()->json([
            'status'  => 0,
            'message' => __('Выплаты успешно обновлены'),
        ]);
    }
}
