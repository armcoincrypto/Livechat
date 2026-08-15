<?php
declare(strict_types=1);

namespace iEXPackages\BinInspector\Http\Controllers;

use App\Models\Currency;
use App\Models\CurrencyBinBankRule;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Контроллер управления правилами банков для конкретной валюты
 * в модуле проверки BIN (BinInspector).
 *
 * Логика:
 *  - для каждой валюты можно задать:
 *      * разрешённые банки для входящих операций (allowed_in),
 *      * разрешённые банки для исходящих операций (allowed_out),
 *      * запрещённые банки для входящих (blocked_in),
 *      * запрещённые банки для исходящих (blocked_out);
 *  - при сохранении мы полностью перезаписываем правила для валюты.
 */
class BinBankRulesController extends Controller
{
    /**
     * Получить список правил по банкам для указанной валюты.
     *
     * @param  Currency  $currency
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Currency $currency)
    {
        $rules = $currency->binBankRules()
            ->orderBy('mode')
            ->orderBy('direction')
            ->orderBy('bank_name')
            ->get(['id', 'direction', 'mode', 'bank_name']);

        // Для удобства фронта сразу раскладываем по четырём массивам
        $allowedIn   = [];
        $allowedOut  = [];
        $blockedIn   = [];
        $blockedOut  = [];

        foreach ($rules as $rule) {
            if ($rule->mode === 'allow' && $rule->direction === 'in') {
                $allowedIn[] = $rule->bank_name;
            } elseif ($rule->mode === 'allow' && $rule->direction === 'out') {
                $allowedOut[] = $rule->bank_name;
            } elseif ($rule->mode === 'block' && $rule->direction === 'in') {
                $blockedIn[] = $rule->bank_name;
            } elseif ($rule->mode === 'block' && $rule->direction === 'out') {
                $blockedOut[] = $rule->bank_name;
            }
        }

        return response()->json([
            'currency_id' => $currency->id,
            'allowed_in'  => $allowedIn,
            'allowed_out' => $allowedOut,
            'blocked_in'  => $blockedIn,
            'blocked_out' => $blockedOut,
        ]);
    }

    /**
     * Сохранить правила по банкам для выбранной валюты.
     *
     * Ожидается структура:
     *  - allowed_in:  массив названий банков
     *  - allowed_out: массив названий банков
     *  - blocked_in:  массив названий банков
     *  - blocked_out: массив названий банков
     *
     * При сохранении все старые записи для валюты удаляются
     * и записываются новые.
     *
     * @param  Request  $request
     * @param  Currency $currency
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Currency $currency)
    {
        $data = $request->validate([
            'allowed_in'    => ['array'],
            'allowed_in.*'  => ['string', 'max:255'],
            'allowed_out'   => ['array'],
            'allowed_out.*' => ['string', 'max:255'],
            'blocked_in'    => ['array'],
            'blocked_in.*'  => ['string', 'max:255'],
            'blocked_out'   => ['array'],
            'blocked_out.*' => ['string', 'max:255'],
        ]);

        // Удаляем старые правила валюты
        $currency->binBankRules()->delete();

        $insert = [];
        $now    = now();

        foreach ($data['allowed_in'] ?? [] as $bankName) {
            $insert[] = [
                'currency_id' => $currency->id,
                'direction'   => 'in',
                'mode'        => 'allow',
                'bank_name'   => $bankName,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        foreach ($data['allowed_out'] ?? [] as $bankName) {
            $insert[] = [
                'currency_id' => $currency->id,
                'direction'   => 'out',
                'mode'        => 'allow',
                'bank_name'   => $bankName,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        foreach ($data['blocked_in'] ?? [] as $bankName) {
            $insert[] = [
                'currency_id' => $currency->id,
                'direction'   => 'in',
                'mode'        => 'block',
                'bank_name'   => $bankName,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        foreach ($data['blocked_out'] ?? [] as $bankName) {
            $insert[] = [
                'currency_id' => $currency->id,
                'direction'   => 'out',
                'mode'        => 'block',
                'bank_name'   => $bankName,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        if (! empty($insert)) {
            CurrencyBinBankRule::insert($insert);
        }

        return response()->json([
            'status'  => 0,
            'message' => 'Настройки банков для валюты сохранены',
        ]);
    }
}
