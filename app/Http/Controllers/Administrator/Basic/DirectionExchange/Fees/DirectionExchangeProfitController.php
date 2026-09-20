<?php

namespace App\Http\Controllers\Administrator\Basic\DirectionExchange\Fees;

use App\Http\Controllers\Controller;
use App\Models\DirectionExchange;
use App\Models\GroupCommission;
use App\Models\ProfitProfile;
use App\Services\Rates\AdminPricingFinancialAuditLogger;
use App\Services\Rates\AdminPricingOperatorSafety;
use App\Services\Rates\AdminPricingTransparency;
use App\Services\Rates\AdminRateSourcePresenter;
use App\Services\Rates\CommercialAdjustmentResolver;
use App\Services\Rates\CommercialAdjustmentWriteGate;
use App\Services\Rates\RateWriteAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DirectionExchangeProfitController extends Controller
{
    public function edit(int $id): JsonResponse
    {
        $item = DirectionExchange::query()->with('bestchange_directions')->findOrFail($id);
        $resolver = new CommercialAdjustmentResolver();
        $family = $resolver->family($item);
        $pricing = $this->pricingContext($item, $resolver);

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
            'base_rate' => $pricing['base'],
            'final_floating_rate' => $pricing['final_floating'],
            'final_fixed_rate' => $pricing['final_fixed'],
            'base_source' => $pricing['base_source'],
            'source_status' => $pricing['source_status'],
            'bestchange_position' => $pricing['bestchange_position'] ?? ($pricing['position']['effective'] ?? null),
            'source_updated_at' => $pricing['source_updated_at'] ?? ($pricing['freshness']['updated_at'] ?? null),
            'ownership' => 'MANUAL_PROFIT',
            'pricing_explanation' => $pricing,
            'global_bestchange_position' => $pricing['position']['global'] ?? null,
            'pair_bestchange_position' => $pricing['position']['pair_override'] ?? null,
            'effective_bestchange_position' => $pricing['position']['effective'] ?? null,
            'freshness_state' => $pricing['freshness']['state'] ?? null,
            'freshness_label' => $pricing['freshness']['state_label'] ?? null,
            'freshness_human' => $pricing['freshness']['human'] ?? null,
            'zero_profit_wording' => $pricing['commercial']['zero_profit_wording'] ?? null,
            'profit_survives_hint' => $pricing['ownership']['profit_survives_hint'] ?? null,
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
                    'rate_source_label' => AdminRateSourcePresenter::label($item),
                    'family' => $family,
                    'commercial_field' => 'profit',
                    'storage_field' => $resolver->storageField($item),
                    'help' => $resolver->helpText($item).' '.($pricing['ownership']['profit_survives_hint'] ?? 'Коммерческая настройка сохраняется при автообновлении курса.').' Источник: '.$pricing['base_source'].' ('.$pricing['source_status'].'). Курс источника='.$pricing['base'].' → плавающий клиенту='.$pricing['final_floating'].'.',
                    'pricing' => $pricing,
                    'ownership' => 'MANUAL_PROFIT',
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

        // Phase 3: never silently switch ownership / rate authority via profit save.
        foreach (['parser_source_name', 'ownership', 'owner', 'rate_mode', 'promote_manual'] as $blocked) {
            if ($request->exists($blocked)) {
                return response()->json([
                    'status' => 1,
                    'code' => 'OWNERSHIP_SWITCH_REQUIRES_DELIBERATE_ACTION',
                    'message' => 'Field '.$blocked.' cannot be changed via Profit update. Use a deliberate ownership/rate-mode action.',
                ], 422);
            }
        }

        $validator = Validator::make($request->all(), [
            'profit'                => ['nullable'],
            'profit_s'              => ['nullable'],
            'ids_group_commissions' => ['array'],
            'ids_group_commissions.*' => ['integer', 'exists:group_commission,id'],
            'profit_profile_id'     => ['nullable', 'integer', 'exists:profit_profiles,id'],
            'confirm_preview'       => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $item->loadMissing(['bestchange_directions', 'currency1', 'currency2']);
        $beforeTruth = AdminPricingOperatorSafety::truthPanel($item);
        $preview = AdminPricingOperatorSafety::preview($item, [
            'profit' => $request->input('profit'),
        ]);

        // Optional operator confirmation gate when client sends preview_required=1.
        if ($request->boolean('preview_required') && !$request->boolean('confirm_preview')) {
            return response()->json([
                'status' => 1,
                'code' => 'PREVIEW_CONFIRM_REQUIRED',
                'message' => 'Confirm Position/Profit preview before save.',
                'data' => $preview,
            ], 422);
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

        $profitBefore = $resolver->displayProfitPercent($item);
        $fixBefore = (string) ($item->fix_fee ?? '0');
        $changeId = (string) Str::uuid();

        CommercialAdjustmentWriteGate::run('admin:DirectionExchangeProfitController', function () use ($item, $payload): void {
            $item->update($payload);
        });
        $item->refresh();

        $family = $resolver->family($item);
        $commercialLabel = null;

        // BestChange: profit is applied in Calculator base. Rewrite course_value so
        // XML (export override = course_value) stays aligned with website/order.
        // Ownership stays BestChange — manual_rate_value mirror is non-authoritative.
        if ($family === 'BESTCHANGE') {
            $commercialLabel = $this->refreshBestChangeCommercialCourse($item);
            $item->refresh();
        } elseif (in_array($family, ['DERIVED', 'ZELLE'], true)) {
            // Refresh admin «Курс» label; BASE (course_value) stays compiler-owned.
            $commercialLabel = $this->refreshCommercialExchangeRateLabel($item, $family);
        }

        $pricing = $this->pricingContext($item, $resolver);
        $item->loadMissing(['bestchange_directions', 'currency1', 'currency2']);
        $convergence = AdminPricingOperatorSafety::convergence($item, [
            'EXPECTED_NEW_CUSTOMER_RATE' => $preview['EXPECTED_NEW_CUSTOMER_RATE'] ?? null,
        ]);

        RateWriteAuditLogger::record([
            'event' => 'admin_profit_change',
            'direction_id' => (int) $item->id,
            'actor' => Auth::id(),
            'writer' => 'admin:DirectionExchangeProfitController',
            'old_value' => $profitBefore ?? null,
            'new_value' => $resolver->displayProfitPercent($item),
            'fix_fee_before' => $fixBefore ?? null,
            'fix_fee_after' => (string) ($item->fix_fee ?? '0'),
            'base' => $pricing['base'],
            'final_floating' => $pricing['final_floating'],
            'base_source' => $pricing['base_source'],
            'job_run_id' => $changeId,
        ]);

        AdminPricingFinancialAuditLogger::record([
            'change_id' => $changeId,
            'request_id' => $request->headers->get('X-Request-Id') ?? $changeId,
            'event' => 'admin_profit_change',
            'writer' => 'admin:DirectionExchangeProfitController',
            'operator_id' => Auth::id(),
            'operator_email' => Auth::user()->email ?? null,
            'direction_id' => (int) $item->id,
            'pair' => $beforeTruth['BESTCHANGE_PAIR'] ?? null,
            'old_profit' => $profitBefore,
            'new_profit' => $resolver->displayProfitPercent($item),
            'old_fix_fee' => $fixBefore,
            'new_fix_fee' => (string) ($item->fix_fee ?? '0'),
            'old_owner' => $beforeTruth['OWNER'] ?? null,
            'new_owner' => $family,
            'old_position' => $beforeTruth['EFFECTIVE_POSITION'] ?? null,
            'new_position' => $beforeTruth['EFFECTIVE_POSITION'] ?? null,
            'before_base' => $beforeTruth['BASE'] ?? null,
            'after_base' => $pricing['base'] ?? null,
            'before_customer_rate' => $beforeTruth['CALCULATOR_FINAL_RATE'] ?? null,
            'after_customer_rate' => $pricing['final_floating'] ?? null,
            'ip' => $request->ip(),
            'route' => 'direction_exchange.fees.profit.update',
        ]);

        Log::info('admin_direction_profit_updated', [
            'direction_id' => (int) $item->id,
            'actor' => Auth::id(),
            'profit_after' => $resolver->displayProfitPercent($item),
            'change_id' => $changeId,
        ]);

        return response()->json([
            'status'  => 0,
            'message' => __('Настройки прибыли направления успешно обновлены'),
            'SAVE_ACCEPTED' => true,
            'change_id' => $changeId,
            'exchange_rate' => $commercialLabel ?? $item->exchange_rate,
            'pricing' => $pricing,
            'preview' => $preview,
            'convergence' => $convergence,
            'warnings' => array_values(array_unique(array_merge(
                $beforeTruth['warnings'] ?? [],
                $convergence['warnings'] ?? []
            ))),
            'attributes' => [
                'profit' => $resolver->displayProfitPercent($item),
                'floating_fee' => $item->floating_fee,
                'fix_fee' => $item->fix_fee,
                'course_value' => $item->course_value,
                'base_rate' => $pricing['base'],
                'final_floating_rate' => $pricing['final_floating'],
                'base_source' => $pricing['base_source'],
                'source_status' => $pricing['source_status'],
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


    private function pricingContext(DirectionExchange $item, CommercialAdjustmentResolver $resolver): array
    {
        $explain = AdminPricingTransparency::explain($item);
        $band = null;
        try {
            $item->loadMissing('currency2');
            $to = (string) ($item->currency2?->designation_xml ?? '');
            $band = \App\Services\Rates\RubFamilyPremiumPolicy::fromStorageApp()->preferredCustomerFloatingBand($to);
        } catch (\Throwable) {
            $band = null;
        }
        $explain['preferred_band'] = $band;
        // Back-compat: legacy panel key for profit control durability.
        $explain['commercial_control'] = 'MANUAL_PROFIT';

        return $explain;
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
