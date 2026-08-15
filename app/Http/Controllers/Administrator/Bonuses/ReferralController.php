<?php

namespace App\Http\Controllers\Administrator\Bonuses;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Bonuses\ReferralResources;
use App\Models\CodeCurrency;
use App\Models\ReferralSettingsCodeAudit;
use App\Settings\ReferralConfig;
use iEXPackages\ReferralSystem\Models\ReferralLink;
use iEXPackages\ReferralSystem\Models\ReferralProgram;
use iEXPackages\ReferralSystem\Services\PartnerRateDiagnosticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ReferralController extends Controller
{
    /**
     * Список реферальных программ
     */
    public function index(): JsonResponse
    {
        $referrals = ReferralProgram::orderBy('id')->paginate(20);

        // Последние 30 записей истории выбора кодов партнёрских расчётов
        $codeAuditLogs = ReferralSettingsCodeAudit::query()
            ->with([
                'actor:id,email',
                'beforeCodeCurrency:id,name',
                'afterCodeCurrency:id,name',
            ])
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return response()->json([
            'items' => new ReferralResources($referrals),
            'code_audit_logs' => $codeAuditLogs,
        ]);
    }

    /**
     * Создание программы (name генерируется автоматически по ID)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'percent' => 'required|numeric',
            'lifetime_minutes' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $item = null;

        DB::transaction(function () use ($request, &$item) {
            // Важно: name NOT NULL → ставим временный, потом заменим на referral_{id}
            $tempName = 'referral_tmp_' . Str::lower(Str::random(16));

            $item = ReferralProgram::create([
                'name' => $tempName,
                'percent' => $request->get('percent'),
                'lifetime_minutes' => $request->has('lifetime_minutes')
                    ? (int) $request->get('lifetime_minutes')
                    : null,
                'title' => $request->title,
                'description' => $request->description,
            ]);

            $item->update([
                'name' => 'referral_' . $item->id,
            ]);
        });

        cache()->forget('static-page-referral');

        return response()->json([
            'status' => 0,
            'message' => $item->title . ' успешно добавлена',
        ]);
    }

    /**
     * Форма редактирования программы
     */
    public function edit(int $id): JsonResponse
    {
        $item = ReferralProgram::findOrFail($id);

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'locales' => $item->getTranslations(),
                // name можно не отдавать вообще — это тех. поле
                'title' => $item->title,
                'description' => $item->description,
                'percent' => $item->percent,
                'lifetime_minutes' => $item->lifetime_minutes
            ],
        ]);
    }

    /**
     * Обновление программы (name не меняем)
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'percent' => 'required|numeric',
            'lifetime_minutes' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $item = ReferralProgram::findOrFail($id);

        $item->update([
            'percent' => $request->get('percent'),
            'lifetime_minutes' => $request->has('lifetime_minutes')
                ? (int) $request->get('lifetime_minutes')
                : null,
            'title' => $request->title,
            'description' => $request->description,
        ]);

        cache()->forget('static-page-referral');

        return response()->json([
            'status' => 0,
            'message' => $item->title . ' успешно обновлена',
        ]);
    }

    /**
     * Удалить программу
     */
    public function destroy(int $id): JsonResponse
    {
        $item = ReferralProgram::findOrFail($id);

        $exists = ReferralLink::whereReferralProgramId($id)->exists();
        if ($exists) {
            return response()->json([
                'status' => 1,
                'message' => $item->title . ' невозможно удалить: к ней привязаны пользователи',
            ]);
        }

        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $item->title . ' успешно удалена',
        ]);
    }

    /**
     * Настройки партнерской программы
     */
    public function settings(ReferralConfig $settings): JsonResponse
    {
        $codes = CodeCurrency::select('id', 'name')->get();

        $programs = ReferralProgram::query()
            ->orderBy('id')
            ->get(['id', 'title', 'percent']);

        $programOptions = $programs->map(static function (ReferralProgram $p): array {
            $title = $p->title;
            if (is_array($title)) {
                $title = $title['ru'] ?? ($title['en'] ?? reset($title) ?? '');
            }
            $title = (string) $title;

            return [
                'id' => (int) $p->id,
                'title' => $title,
                'percent' => (string) $p->percent,
            ];
        })->values();

        return response()->json([
            'settings' => $settings->toArray(),
            'codes' => $codes,
            'programs' => $programOptions,
        ]);
    }

    /**
     * Сохранение настроек (включая default_referral_program_id)
     */
    public function settingsUpdate(Request $request, ReferralConfig $settings): JsonResponse
    {
        $payload = $request->only([
            'enabled_referral_system',
            'referral_storage_periods',
            'referral_lifetime_day',
            'minimum_bonus_payout',
            'is_enabled_referral_logs',
            'type_partner_deductions',
            'id_referral_code_currency',
            'referral_fallback_code_currencies',
            'default_referral_program_id',
        ]);

        // Снимок до изменений
        $before = $settings->toArray();

        $settings->update($payload);

        try {
            $beforeMain = (int) ($before['id_referral_code_currency'] ?? 0);
            $afterMain = (int) ($payload['id_referral_code_currency'] ?? ($settings->toArray()['id_referral_code_currency'] ?? 0));

            $beforeFallback = (array) ($before['referral_fallback_code_currencies'] ?? []);
            $afterFallback = (array) ($payload['referral_fallback_code_currencies'] ?? []);

            sort($beforeFallback);
            sort($afterFallback);

            $isAuditEmpty = !ReferralSettingsCodeAudit::query()->exists();

            if ($isAuditEmpty || $beforeMain !== $afterMain || $beforeFallback !== $afterFallback) {
                $user = $request->user();

                ReferralSettingsCodeAudit::create([
                    'actor_id' => $user?->id,
                    'actor_email' => $user?->email,
                    'before_code_currency_id' => $isAuditEmpty ? null : ($beforeMain ?: null),
                    'after_code_currency_id' => $afterMain ?: null,
                    'before_fallback_codes' => $isAuditEmpty ? null : $beforeFallback,
                    'after_fallback_codes' => $afterFallback,
                    'ip' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Referral settings audit failed', [
                'exception' => $e,
            ]);
        }

        return response()->json([
            'status' => 0,
            'message' => 'Настройки успешно сохранены',
        ]);
    }

    public function rateDiagnosticsAll(Request $request, PartnerRateDiagnosticsService $service): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ], 422);
        }

        $bonusCode = Str::upper(config('partners-bonus.name'));
        $amount = (float) ($request->input('amount') ?? 1.0);

        return response()->json([
            'status' => 0,
            'data' => $service->diagnoseAll($bonusCode, $amount),
        ]);
    }
}
