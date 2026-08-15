<?php

namespace App\Http\Controllers\Administrator\Account;

use App\Http\Controllers\Controller;
use App\Models\CodeCurrency;
use App\Models\Currency;
use App\Models\User;
use App\Models\UserBalance;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PayoutsController extends Controller
{
    /**
     * Создаем заявку на выплату
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_currency' => 'required',
            'score' => 'required|max:1000',
            'purpose' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withInput();
        } else {
            $user = User::find($request->id);
            $balance = $this->getBalance($request->id_currency, $user);

            WithdrawalRequest::create([
                'id_user' => $request->id,
                'id_currency' => $request->id_currency,
                'score' => $request->score,
                'balance_referral' => $balance['referral'],
                'base_referral' => $balance['base_referral'],
                'ip' => $request->ip(),
            ]);

            $this->cleanBalance($request->id);

            return redirect()->back();
        }
    }

    /**
     * Обнуляем баланс
     */
    protected function cleanBalance($id)
    {
        $balance_array = [];
        $balance_array['balance'] = 0;

        //Обновление цен
        UserBalance::where('id_user', $id)
            ->update($balance_array);
    }

    /**
     * Получение баланса
     *
     * @return array
     */
    protected function getBalance($id, User $user)
    {
        $currency = Currency::find($id);
        $code_currency = CodeCurrency::find((int) iEXSetting('id_referral_code_currency'));

        if (\Str::lower($currency->code_currency->id) == (int) iEXSetting('id_referral_code_currency')) {
            $price_referral = (float) $user->user_balance->balance;
        } else {
            $price_referral = calculator_converter($code_currency->name, $currency->code_currency->name, $user->user_balance->balance);
        }
        $base_referral = (float) $user->user_balance->balance;

        return [
            'referral' => $price_referral,
            'base_referral' => $base_referral,
        ];
    }
}
