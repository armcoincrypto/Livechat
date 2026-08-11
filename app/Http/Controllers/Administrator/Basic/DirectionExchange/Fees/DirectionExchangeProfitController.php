<?php

namespace App\Http\Controllers\Administrator\Basic\DirectionExchange\Fees;

use App\Http\Controllers\Controller;
use App\Models\DirectionExchange;
use App\Models\GroupCommission;
use App\Models\ProfitProfile;
use App\Services\Rates\CommercialAdjustmentResolver;
use App\Services\Rates\CommercialAdjustmentWriteGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DirectionExchangeProfitController extends Controller
{
    public function edit(int $id): JsonResponse
    {
        $item = DirectionExchange::query()->findOrFail($id);
        $resolver = new CommercialAdjustmentResolver();
        $family = $resolver->family($item);

        $attributes = [
            'type_profit_field'   => $item->type_profit_field ?? 0,
            // Universal owner control: always expose margin % as «Прибыль».
            'profit'              => $resolver->displayProfitPercent($item),
            'profit_s'            => $item->profit_s ?? 0,
            'ids_group_commissions' => $item->groupCommissions->pluck('id')->toArray(),
            'profit_profile_id'   => $item->profit_profile_id,
            // Read-only context for admin UX (BASE / fees remain on other panels).
            'commercial_storage_field' => $resolver->storageField($item),
            'floating_fee' => $item->floating_fee ?? 0,
            'fix_fee' => $item->fix_fee ?? 0,
            'course_value' => $item->course_value,
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

        return response()->json([
            'options'    => [
                'groupFees'      => $group_commission,
                'profitProfiles' => $profitProfiles,
                'rateAuthority'  => [
                    'parser_source_name' => (string) ($item->parser_source_name ?? ''),
                    'family' => $family,
                    'commercial_field' => 'profit',
                    'storage_field' => $resolver->storageField($item),
                    'help' => $resolver->helpText($item),
                ],
            ],
            'attributes' => $attributes,
            'default'    => [],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = DirectionExchange::query()->findOrFail($id);
        $resolver = new CommercialAdjustmentResolver();

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

        $displayProfit = $this->normalizeDecimalString($request->input('profit'));
        $profitS = $this->normalizeDecimalString($request->input('profit_s'));

        $payload = array_merge(
            $resolver->buildUpdatePayload($item, $displayProfit),
            [
                'profit_s' => $profitS,
                'profit_profile_id' => $request->input('profit_profile_id'),
            ]
        );

        CommercialAdjustmentWriteGate::run('admin:DirectionExchangeProfitController', function () use ($item, $payload): void {
            $item->update($payload);
        });
        $item->refresh();

        $family = $resolver->family($item);
        $commercialLabel = null;

        // BestChange: profit is applied in Calculator base. Rewrite course_value so
        // XML (export override = course_value) stays aligned with website/order.
        if ($family === 'BESTCHANGE') {
            $commercialLabel = $this->refreshBestChangeCommercialCourse($item);
            $item->refresh();
        } elseif (in_array($family, ['DERIVED', 'ZELLE'], true)) {
            // Refresh admin «Курс» label; BASE (course_value) stays compiler-owned.
            $commercialLabel = $this->refreshCommercialExchangeRateLabel($item, $family);
        }

        return response()->json([
            'status'  => 0,
            'message' => __('Настройки прибыли направления успешно обновлены'),
            'exchange_rate' => $commercialLabel ?? $item->exchange_rate,
            'attributes' => [
                'profit' => $resolver->displayProfitPercent($item),
                'floating_fee' => $item->floating_fee,
                'fix_fee' => $item->fix_fee,
                'course_value' => $item->course_value,
            ],
        ]);
    }

    /**
     * Persist Calculator commercial rate into course_value for BestChange-owned pairs.
     * Does not invent a second floating_fee adjustment.
     */
    private function refreshBestChangeCommercialCourse(DirectionExchange $item): ?string
    {
        try {
            $payload = \iEXPackages\Calculator\CalculatorFacade::setDirectionExchange($item)
                ->withoutOptions()
                ->calculate()
                ->toArray();
            $rate = (string) ($payload['rate'] ?? '0');
            if (!is_numeric($rate) || bccomp($rate, '0', 18) !== 1) {
                return null;
            }

            $label = (string) \iEXPackages\Calculator\CalculatorFacade::setDirectionExchange($item)
                ->withoutOptions()
                ->calculate()
                ->getFullRate();

            DB::table('direction_exchange')->where('id', $item->id)->update([
                'course_value' => $rate,
                'manual_rate_value' => $rate,
                'exchange_rate' => $label !== '' ? $label : $rate,
                'updated_at' => now(),
            ]);

            return $label !== '' ? $label : $rate;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Write admin exchange_rate from Canonical floating (Прибыль-aware) rate.
     */
    private function refreshCommercialExchangeRateLabel(DirectionExchange $item, string $family): ?string
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
            // Avoid double commercial % inside Calculator label path.
            $dir->profit = 0;
            $dir->profit_s = 0;
            if ($family === 'ZELLE') {
                $dir->floating_fee = 0;
                $dir->fix_fee = 0;
            }

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
