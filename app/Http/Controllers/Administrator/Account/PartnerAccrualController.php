<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Account;

use App\Http\Controllers\Controller;
use App\Models\PartnerAccrualPlan;
use App\Models\User;
use App\Services\PartnerAccrual\Exceptions\CurrencyNotSupported;
use App\Services\PartnerAccrual\Exceptions\GeneratorConstraintViolation;
use App\Services\PartnerAccrual\Exceptions\OverlappingPlanExists;
use App\Services\PartnerAccrual\Exceptions\PartnerAccrualException;
use App\Services\PartnerAccrual\PartnerAccrualAdminPresenter;
use App\Services\PartnerAccrual\PartnerAccrualDueQuery;
use App\Services\PartnerAccrual\PartnerAccrualPlanLifecycleService;
use App\Services\PartnerAccrual\PartnerAccrualPlanPreviewService;
use App\Services\PartnerAccrual\PartnerAccrualRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Admin control surface for partner monthly accrual.
 * All money math delegated to Wave A/B services. Feature-flag fail-closed.
 */
final class PartnerAccrualController extends Controller
{
    public function __construct(
        private readonly PartnerAccrualAdminPresenter $presenter = new PartnerAccrualAdminPresenter(),
        private readonly PartnerAccrualPlanPreviewService $previewService = new PartnerAccrualPlanPreviewService(),
        private readonly PartnerAccrualPlanLifecycleService $lifecycle = new PartnerAccrualPlanLifecycleService(),
        private readonly PartnerAccrualDueQuery $dueQuery = new PartnerAccrualDueQuery(),
        private readonly PartnerAccrualRunner $runner = new PartnerAccrualRunner(),
    ) {}

    public function userSummary(int $userId): JsonResponse
    {
        if ($deny = $this->featureGate()) {
            return $deny;
        }
        if (! User::query()->whereKey($userId)->exists()) {
            return $this->fail('USER_NOT_FOUND', 'Пользователь не найден', 404);
        }

        return response()->json([
            'status' => 0,
            'data' => $this->presenter->userSummary($userId),
        ]);
    }

    public function preview(Request $request, int $userId): JsonResponse
    {
        if ($deny = $this->featureGate()) {
            return $deny;
        }

        $validator = Validator::make($request->all(), [
            'total_amount' => 'required|numeric|min:0.01',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'currency_code' => 'nullable|string|in:USD',
        ]);
        if ($validator->fails()) {
            return $this->fail('VALIDATION', $validator->errors()->first(), 422);
        }
        if ($request->filled('currency_code') && strtoupper((string) $request->currency_code) !== 'USD') {
            return $this->fail('CURRENCY_NOT_SUPPORTED', 'v1 поддерживает только USD', 422);
        }

        try {
            $raw = $this->previewService->preview([
                'user_id' => $userId,
                'currency_code' => 'USD',
                'total_amount' => (string) $request->input('total_amount'),
                'start_date' => (string) $request->input('start_date'),
                'end_date' => (string) $request->input('end_date'),
            ]);
            $formatted = $this->presenter->formatPreview($raw);
            $formatted['start_date'] = (string) $request->input('start_date');
            $formatted['end_date'] = (string) $request->input('end_date');

            return response()->json(['status' => 0, 'data' => $formatted]);
        } catch (CurrencyNotSupported|GeneratorConstraintViolation $e) {
            return $this->fail($e->codeName(), $e->getMessage(), 422);
        }
    }

    public function store(Request $request, int $userId): JsonResponse
    {
        if ($deny = $this->featureGate()) {
            return $deny;
        }

        $validator = Validator::make($request->all(), [
            'total_amount' => 'required|numeric|min:0.01',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            // Explicitly reject installment payloads from client.
            'installments' => 'prohibited',
            'schedule' => 'prohibited',
            'amounts' => 'prohibited',
        ]);
        if ($validator->fails()) {
            return $this->fail('VALIDATION', $validator->errors()->first(), 422);
        }

        $user = User::query()->find($userId);
        if (! $user) {
            return $this->fail('USER_NOT_FOUND', 'Пользователь не найден', 404);
        }
        if (! $this->dueQuery->userEligible($userId)) {
            return $this->fail(
                'USER_NOT_ELIGIBLE',
                'Начисление недоступно для заблокированных или деактивированных пользователей',
                422
            );
        }

        try {
            $plan = $this->lifecycle->create([
                'user_id' => $userId,
                'currency_code' => 'USD',
                'currency_code_id' => 8,
                'total_amount' => (string) $request->input('total_amount'),
                'start_date' => (string) $request->input('start_date'),
                'end_date' => (string) $request->input('end_date'),
                'created_by_admin_id' => (int) $request->user()->id,
                'metadata' => [
                    'created_via' => 'admin_partner_accrual',
                    'ip' => $request->ip(),
                ],
            ], activate: true);

            return response()->json([
                'status' => 0,
                'message' => 'План ежемесячного начисления создан',
                'data' => $this->presenter->planDetail($plan),
            ], 201);
        } catch (OverlappingPlanExists $e) {
            return $this->fail($e->codeName(), $e->getMessage(), 409);
        } catch (CurrencyNotSupported|GeneratorConstraintViolation $e) {
            return $this->fail($e->codeName(), $e->getMessage(), 422);
        } catch (PartnerAccrualException $e) {
            return $this->fail($e->codeName(), $e->getMessage(), 422);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('UNEXPECTED', 'Не удалось создать план', 500);
        }
    }

    public function show(int $planId): JsonResponse
    {
        if ($deny = $this->featureGate()) {
            return $deny;
        }
        $plan = PartnerAccrualPlan::query()->find($planId);
        if (! $plan) {
            return $this->fail('PLAN_NOT_FOUND', 'План не найден', 404);
        }

        return response()->json([
            'status' => 0,
            'data' => $this->presenter->planDetail($plan),
        ]);
    }

    public function pause(Request $request, int $planId): JsonResponse
    {
        if ($deny = $this->featureGate()) {
            return $deny;
        }
        try {
            $plan = $this->lifecycle->pause($planId, (int) $request->user()->id, 'admin_manual');

            return response()->json([
                'status' => 0,
                'message' => 'План приостановлен. Уже начисленные средства не отменяются.',
                'data' => $this->presenter->planSummary($plan),
            ]);
        } catch (PartnerAccrualException $e) {
            return $this->fail($e->codeName(), $e->getMessage(), 422);
        }
    }

    public function resume(Request $request, int $planId): JsonResponse
    {
        if ($deny = $this->featureGate()) {
            return $deny;
        }
        $plan = PartnerAccrualPlan::query()->find($planId);
        if (! $plan) {
            return $this->fail('PLAN_NOT_FOUND', 'План не найден', 404);
        }
        if (! $this->dueQuery->userEligible((int) $plan->user_id)) {
            return $this->fail(
                'USER_NOT_ELIGIBLE',
                'Нельзя возобновить план для заблокированного или деактивированного пользователя',
                422
            );
        }
        try {
            $plan = $this->lifecycle->resume($planId, (int) $request->user()->id);

            return response()->json([
                'status' => 0,
                'message' => 'План возобновлён. Расписание не пересчитывается; пропущенные дни могут доначислиться после активации планировщика.',
                'data' => $this->presenter->planSummary($plan),
            ]);
        } catch (PartnerAccrualException $e) {
            return $this->fail($e->codeName(), $e->getMessage(), 422);
        }
    }

    public function cancel(Request $request, int $planId): JsonResponse
    {
        if ($deny = $this->featureGate()) {
            return $deny;
        }
        $confirm = (string) $request->input('confirm', '');
        if ($confirm !== 'CANCEL') {
            return $this->fail('CONFIRM_REQUIRED', 'Требуется confirm=CANCEL', 422);
        }
        try {
            $plan = $this->lifecycle->cancel($planId, (int) $request->user()->id);

            return response()->json([
                'status' => 0,
                'message' => 'План отменён. Уже начисленные средства остаются на балансе.',
                'data' => $this->presenter->planSummary($plan),
            ]);
        } catch (PartnerAccrualException $e) {
            return $this->fail($e->codeName(), $e->getMessage(), 422);
        }
    }

    /** Read-only dry-run of due installments. Never credits. */
    public function dryRunDue(Request $request, int $userId): JsonResponse
    {
        if ($deny = $this->featureGate()) {
            return $deny;
        }
        $plan = PartnerAccrualPlan::query()
            ->where('user_id', $userId)
            ->where('status', 'ACTIVE')
            ->orderByDesc('id')
            ->first();

        $result = $this->runner->run(
            dryRun: true,
            planId: $plan?->id,
            businessDate: $request->input('date') ? (string) $request->input('date') : null,
        );
        $result['label'] = 'Dry run — no balance changes';
        $result['balance_mutations'] = 0;

        return response()->json(['status' => 0, 'data' => $result]);
    }

    private function featureGate(): ?JsonResponse
    {
        if (! config('partner_accrual.enabled')) {
            return response()->json([
                'status' => 1,
                'code' => 'FEATURE_DISABLED',
                'message' => 'Ежемесячное партнёрское начисление отключено (PARTNER_ACCRUAL_ENABLED=false)',
            ], 503);
        }

        return null;
    }

    private function fail(string $code, string $message, int $http): JsonResponse
    {
        return response()->json([
            'status' => 1,
            'code' => $code,
            'message' => $message,
        ], $http);
    }
}
