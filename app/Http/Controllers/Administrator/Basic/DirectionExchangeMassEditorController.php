<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Basic\DirectionExchangeMassEditorResources;
use App\Models\DirectionExchange;
use App\Services\Rates\AdminBulkActionAuditLogger;
use App\Services\Rates\AdminPricingFinancialAuditLogger;
use App\Services\Rates\AdminPricingOperatorSafety;
use App\Services\Rates\CommercialAdjustmentResolver;
use App\Services\Rates\CommercialAdjustmentWriteGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DirectionExchangeMassEditorController extends Controller
{
    private const OWNERSHIP_FIELDS = [
        'parser_source_name',
        'ownership',
        'owner',
        'rate_mode',
        'course_value',
        'manual_rate_value',
    ];

    private const FINANCIAL_TEMPLATES = [
        'amounts',
        'oth-fee',
        'pay-fee',
        'profit',
        'partner-profit',
        'fix_fee',
        'floating_fee',
        'profit_s',
    ];

    /**
     * Массивное редактирование направление обмена
     */
    public function index(Request $request): JsonResponse
    {
        $directionExchange = DirectionExchange::with([
            'currency2',
            'currency1',
            'currency2.payment',
            'currency1.payment',
            'currency2.code_currency',
            'currency1.code_currency'])
            ->filter($request->all())
            ->paginate(20);

        return response()->json([
            'items' => new DirectionExchangeMassEditorResources($directionExchange)
        ]);
    }

    /**
     * Обновляем данные — requires confirm for financial templates.
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'details' => ['required'],
            'template' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $template = (string) $request->input('template');
        $details = $request->input('details');
        if (!is_array($details)) {
            return response()->json([
                'status' => 1,
                'message' => 'details must be an object/array',
            ]);
        }

        if (in_array($template, self::OWNERSHIP_FIELDS, true)) {
            return response()->json([
                'status' => 1,
                'code' => 'OWNERSHIP_SWITCH_REQUIRES_DELIBERATE_ACTION',
                'message' => 'OWNER/rate-mode/course cannot be changed via Mass Editor profit/position path. Use a deliberate ownership action.',
            ], 422);
        }

        foreach (array_keys($details) as $field) {
            if (in_array((string) $field, self::OWNERSHIP_FIELDS, true)) {
                return response()->json([
                    'status' => 1,
                    'code' => 'OWNERSHIP_PIGGYBACK_BLOCKED',
                    'message' => 'Field '.$field.' cannot piggyback on mass profit/position edits.',
                ], 422);
            }
        }

        $isFinancial = in_array($template, self::FINANCIAL_TEMPLATES, true)
            || in_array($template, ['min_price1', 'min_price2', 'max_price1', 'max_price2'], true);

        if ($isFinancial) {
            $preview = AdminPricingOperatorSafety::massPreview([
                'template' => $template,
                'details' => $details,
            ]);
            if (empty($preview['ok'])) {
                return response()->json([
                    'status' => 1,
                    'code' => 'MASS_PREVIEW_BLOCKED',
                    'message' => $preview['error'] ?? 'blocked',
                    'data' => $preview,
                ], 422);
            }

            $expectedToken = hash('sha256', json_encode([$template, $details], JSON_UNESCAPED_UNICODE));
            $preview['confirm_token'] = $expectedToken;

            if (!$request->boolean('confirm')) {
                return response()->json([
                    'status' => 1,
                    'code' => 'CONFIRM_REQUIRED',
                    'message' => 'Mass financial edit requires confirm=1 and confirm_token from preview. Affected: '
                        .(int) ($preview['affected_count'] ?? 0),
                    'data' => $preview,
                ], 422);
            }

            $token = (string) $request->input('confirm_token', '');
            if ($token === '' || !hash_equals($expectedToken, $token)) {
                return response()->json([
                    'status' => 1,
                    'code' => 'CONFIRM_TOKEN_MISMATCH',
                    'message' => 'confirm_token does not match this template/details payload. Run preview first.',
                    'data' => ['confirm_token' => $expectedToken, 'affected_count' => $preview['affected_count'] ?? 0],
                ], 422);
            }
        }

        $affectedIds = [];
        $beforeSamples = [];

        if (in_array($template, ['amounts', 'oth-fee', 'pay-fee', 'profit', 'partner-profit'], true)) {
            foreach ($details as $key_field => $values) {
                if (!is_array($values)) {
                    continue;
                }
                if (in_array((string) $key_field, self::OWNERSHIP_FIELDS, true)) {
                    return response()->json([
                        'status' => 1,
                        'code' => 'OWNERSHIP_PIGGYBACK_BLOCKED',
                        'message' => 'Field '.$key_field.' cannot piggyback on mass edits.',
                    ], 422);
                }
                foreach ($values as $key => $value) {
                    $directionExchange = DirectionExchange::find($key);
                    if (!$directionExchange) {
                        continue;
                    }
                    $affectedIds[] = (int) $directionExchange->id;
                    if (count($beforeSamples) < 5) {
                        $resolver = new CommercialAdjustmentResolver();
                        $beforeSamples[] = [
                            'id' => (int) $directionExchange->id,
                            'profit' => $resolver->displayProfitPercent($directionExchange),
                            'field' => (string) $key_field,
                            'old' => $directionExchange->{$key_field} ?? null,
                            'new' => $value,
                        ];
                    }
                    $field = (string) $key_field;
                    CommercialAdjustmentWriteGate::run('admin:DirectionExchangeMassEditor', function () use ($directionExchange, $field, $value): void {
                        $directionExchange->update([
                            $field => is_numeric($value) ? (string) $value : $value,
                        ]);
                    });
                }
            }
        } else {
            foreach ($details as $key => $value) {
                $directionExchange = DirectionExchange::find($key);
                if (!$directionExchange) {
                    continue;
                }
                $affectedIds[] = (int) $directionExchange->id;
                CommercialAdjustmentWriteGate::run('admin:DirectionExchangeMassEditor', function () use ($directionExchange, $template, $value): void {
                    $directionExchange->update([
                        $template => $value,
                    ]);
                });
            }
        }

        $changeId = AdminPricingFinancialAuditLogger::record([
            'event' => 'admin_mass_pricing_change',
            'writer' => 'admin:DirectionExchangeMassEditorController',
            'operator_id' => Auth::id(),
            'operator_email' => Auth::user()->email ?? null,
            'request_id' => $request->headers->get('X-Request-Id'),
            'ip' => $request->ip(),
            'route' => 'mass-direction-editor.update',
            'notes' => 'template='.$template,
            'affected_count' => count(array_unique($affectedIds)),
            'new_profit' => $template === 'profit' ? 'mass' : null,
        ]);

        AdminBulkActionAuditLogger::record([
            'event' => 'mass_direction_editor',
            'action' => $template,
            'result' => 'applied',
            'admin_user_id' => Auth::id(),
            'admin_email' => Auth::user()->email ?? null,
            'ip' => $request->ip(),
            'request_id' => $request->headers->get('X-Request-Id') ?? $changeId,
            'route' => 'mass-direction-editor.update',
            'row_count' => count(array_unique($affectedIds)),
            'ids' => array_values(array_unique($affectedIds)),
            'message' => json_encode(['samples' => $beforeSamples], JSON_UNESCAPED_UNICODE),
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Данные обновлены',
            'SAVE_ACCEPTED' => true,
            'change_id' => $changeId,
            'affected_count' => count(array_unique($affectedIds)),
        ]);
    }
}
