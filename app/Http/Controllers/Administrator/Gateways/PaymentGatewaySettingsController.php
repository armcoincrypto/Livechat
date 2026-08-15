<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Gateways;

use App\Http\Controllers\Controller;
use App\Settings\GatewayConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentGatewaySettingsController extends Controller
{
    /**
     * Получить текущие настройки логирования.
     * Можно фильтровать по type=merchant|payout.
     */
    public function index(Request $request, GatewayConfig $config): JsonResponse
    {
        $type = (string) $request->query('type', '');

        $data = [
            'disable_merchant_logs' => $config->isMerchantLogDisabled(),
            'disable_payout_logs'   => $config->isPayoutLogDisabled(),
        ];

        if ($type === 'merchant') {
            return response()->json([
                'type' => 'merchant',
                'data' => ['disable_merchant_logs' => $data['disable_merchant_logs']],
            ]);
        }

        if ($type === 'payout') {
            return response()->json([
                'type' => 'payout',
                'data' => ['disable_payout_logs' => $data['disable_payout_logs']],
            ]);
        }

        return response()->json([
            'type' => 'all',
            'data' => $data,
        ]);
    }

    /**
     * Обновить настройку логирования.
     *
     * Ожидает:
     *  - type=merchant + disable_merchant_logs (bool)
     *  - type=payout   + disable_payout_logs   (bool)
     */
    public function update(Request $request, GatewayConfig $config): JsonResponse
    {
        $type = (string) $request->input('type', '');

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:merchant,payout'],

            // При merchant разрешаем/ожидаем только disable_merchant_logs
            'disable_merchant_logs' => ['sometimes', 'boolean', 'required_if:type,merchant'],
            // При payout разрешаем/ожидаем только disable_payout_logs
            'disable_payout_logs'   => ['sometimes', 'boolean', 'required_if:type,payout'],
        ]);

        $payload = [];

        if ($type === 'merchant') {
            $payload['disable_merchant_logs'] = (bool) $validated['disable_merchant_logs'];
        } else {
            $payload['disable_payout_logs'] = (bool) $validated['disable_payout_logs'];
        }

        $config->update($payload);

        return response()->json([
            'status'  => 1,
            'message' => __('Настройки логирования успешно обновлены'),
            'type'    => $type,
            'data'    => [
                'disable_merchant_logs' => $config->isMerchantLogDisabled(),
                'disable_payout_logs'   => $config->isPayoutLogDisabled(),
            ],
        ]);
    }
}
