<?php

namespace App\Http\Controllers\Administrator\Basic\DirectionExchange\Fees;

use App\Http\Controllers\Controller;
use App\Models\DirectionExchange;
use App\Models\GroupCommission;
use App\Models\ProfitProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DirectionExchangeProfitController extends Controller
{
    public function edit(int $id): JsonResponse
    {
        $item = DirectionExchange::query()->findOrFail($id);

        $attributes = [
            'type_profit_field'   => $item->type_profit_field ?? 0,
            'profit'              => $item->profit ?? 0,
            'profit_s'            => $item->profit_s ?? 0,
            'ids_group_commissions' => $item->groupCommissions->pluck('id')->toArray(),
            'profit_profile_id'   => $item->profit_profile_id,
        ];

        $group_commission = GroupCommission::orderByDesc('id')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'value' => $item->name . ' (Комиссия: '. $item->receiving.')',
            ];
        });

        $profitProfiles = ProfitProfile::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('scope')
                  ->orWhere('scope', 'direction')
                  ->orWhere('scope', 'global');
            })
            ->orderBy('name')
            ->get()
            ->map(function (ProfitProfile $profile) {
                return [
                    'id'        => $profile->id,
                    'name'      => $profile->name,
                    'profit'    => $profile->profit,
                    'profit_s'  => $profile->profit_s,
                    'scope'     => $profile->scope,
                    'is_active' => (bool) $profile->is_active,
                ];
            });

        $parser = (string) ($item->parser_source_name ?? '');
        $derived = $parser === 'DERIVED_MARKET_BASELINE';

        return response()->json([
            'options'    => [
                'groupFees'      => $group_commission,
                'profitProfiles' => $profitProfiles,
                'rateAuthority'  => [
                    'parser_source_name' => $parser,
                    'commercial_field' => $derived ? 'profit' : 'profit_or_floating_fee',
                    'help' => $derived
                        ? 'Автоматический BASE. «Прибыль»: +5 = больше маржа (хуже клиенту), -5 = конкурентнее (лучше клиенту). Рынок обновляется сам.'
                        : '«Прибыль» % для направления. Для BestChange учитывается в курсе; знак + = маржа, − = конкурентнее.',
                ],
            ],
            'attributes' => $attributes,
            'default'    => [],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = DirectionExchange::query()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'profit'                => ['nullable'],
            'profit_s'              => ['nullable'],
            'ids_group_commissions' => ['array'],
            'ids_group_commissions.*' => ['integer', 'exists:group_commission,id'],
            'profit_profile_id'     => ['nullable', 'integer', 'exists:profit_profiles,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        // Сохраняем группы комиссий
        $idsGroupCommissions = $request->input('ids_group_commissions', []);
        if (is_array($idsGroupCommissions)) {
            $item->groupCommissions()->sync($idsGroupCommissions);
        }

        $profit = $this->normalizeDecimalString($request->input('profit'));
        $profitS = $this->normalizeDecimalString($request->input('profit_s'));

        $payload = [
            'profit' => $profit,
            'profit_s' => $profitS,
            'profit_profile_id' => $request->input('profit_profile_id'),
        ];

        // DERIVED: Прибыль drives public/XML via Canonical (fee = -profit).
        // Enable type_rate so website/order apply the same commercial %.
        if ((string) ($item->parser_source_name ?? '') === 'DERIVED_MARKET_BASELINE') {
            $payload['is_type_rate'] = 1;
            // Clear floating_fee so Прибыль is the single commercial knob.
            $payload['floating_fee'] = '0';
        }

        $item->update($payload);
        $item->refresh();

        // Refresh admin «Курс» label to commercial rate (BASE ± Прибыль) so it
        // matches currencies.xml / BestChange after save — not stale BASE.
        $commercialLabel = null;
        if ((string) ($item->parser_source_name ?? '') === 'DERIVED_MARKET_BASELINE') {
            $commercialLabel = $this->refreshDerivedExchangeRateLabel($item);
        }

        return response()->json([
            'status'  => 0,
            'message' => __('Настройки прибыли направления успешно обновлены'),
            'exchange_rate' => $commercialLabel ?? $item->exchange_rate,
        ]);
    }

    /**
     * Write admin exchange_rate from Canonical floating (Прибыль-aware) rate.
     */
    private function refreshDerivedExchangeRateLabel(DirectionExchange $item): ?string
    {
        try {
            $base = (string) ($item->course_value ?? '0');
            if (!is_numeric($base) || bccomp($base, '0', 18) !== 1) {
                return null;
            }

            $calc = \App\Services\Rates\CanonicalDirectionRateCalculator::make();
            $commercial = $calc->calculateForExport($item, $base);
            $rate = ($commercial->eligible && bccomp($commercial->finalRate, '0', 18) === 1)
                ? $commercial->finalRate
                : $base;

            $dir = DirectionExchange::query()
                ->with([
                    'currency1:id,number_format,id_code_currency',
                    'currency1.code_currency:id,name',
                    'currency2:id,number_format,id_code_currency',
                    'currency2.code_currency:id,name',
                ])
                ->find($item->id);
            if ($dir === null) {
                return null;
            }

            $dir->course_value = $rate;
            $dir->parser_source_name = 'DERIVED_MARKET_BASELINE';
            $dir->profit = 0;
            $dir->profit_s = 0;

            $label = (string) \iEXPackages\Calculator\CalculatorFacade::setDirectionExchange($dir)
                ->withoutOptions()
                ->calculate()
                ->getFullRate();

            DB::table('direction_exchange')->where('id', $item->id)->update([
                'exchange_rate' => $label,
                'updated_at' => now(),
            ]);

            return $label;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Нормализует десятичное число, переданное строкой (допускает −%).
     */
    private function normalizeDecimalString($value): string
    {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            return '0';
        }

        $raw = str_replace([',', ' '], ['.', ''], $raw);

        if (str_starts_with($raw, '.')) {
            $raw = '0' . $raw;
        }
        if (str_starts_with($raw, '-.')) {
            $raw = '-0' . substr($raw, 1);
        }
        if (str_starts_with($raw, '+.')) {
            $raw = '+0' . substr($raw, 1);
        }

        // Allow signed percent: 5, -5, +2.5
        if (!preg_match('/^[+-]?\d+(?:\.\d+)?$/', $raw)) {
            return '0';
        }

        if (str_starts_with($raw, '+')) {
            $raw = substr($raw, 1);
        }

        $raw = rtrim(rtrim($raw, '0'), '.');
        if ($raw === '' || $raw === '-') {
            return '0';
        }

        return $raw;
    }
}
