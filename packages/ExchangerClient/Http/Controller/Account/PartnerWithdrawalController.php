<?php
namespace iEXPackages\ExchangerClient\Http\Controller\Account;

use App\Http\Controllers\AbstractController;
use App\Models\CodeCurrency;
use App\Models\Currency;
use App\Models\WithdrawalRequest;
use App\Support\Facades\iEXApp;
use Carbon\Carbon;
use iEXPackages\ExchangerClient\Http\Resources\Account\PartnerWithdrawalCurrencyResponses;
use iEXPackages\ExchangerClient\Http\Resources\Account\PartnerWithdrawalResponses;
use iEXPackages\SmartMailer\Facades\SmartMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class PartnerWithdrawalController extends AbstractController
{
    /**
     * Информация о выводе средств
     */
    public function withdrawalInfo(Request $request): JsonResponse
    {
        // Информация о пользователе
        $user = $request->user();

        if ($user->is_unique_user == 1) {
            $currency = Currency::where('is_payment_unique', '=', 1)->where('status', 0)->get();
        } else {
            $currency = Currency::where('is_payment_default', '=', 1)->where('status', 0)->get();
        }

        // Список выводов
        $withdrawalRequest = WithdrawalRequest::where([
            ['id_user', $user->id],
        ])->orderBy('id', 'desc');

        $withdrawal_histories = new PartnerWithdrawalResponses(
            $withdrawalRequest->simplePaginate(10)
        );
        $minimum_bonus_payout = (float)iEXSetting('minimum_bonus_payout', 0);

        return response()->json([
                'payments' => new PartnerWithdrawalCurrencyResponses($currency),
                'withdrawal_histories' => $withdrawal_histories,
                'user' => [
                    'balance' => $user->user_balance->balance,
                    'code_sign' => config('partners-bonus.name') ?? '',
                    'min_withdrawal' => iex_number_format($minimum_bonus_payout, 0, true),
                    'is_withdrawal' => $user->user_balance->balance > $minimum_bonus_payout,
                ],
            ]
        );
    }

    /**
     * История вывода бонусов
     *
     * @return PartnerWithdrawalResponses
     */
    public function histories(Request $request)
    {
        // Список выводов
        $withdrawalRequest = WithdrawalRequest::where([
            ['id_user', $request->user()->id],
        ])->orderBy('id', 'desc');

        return new PartnerWithdrawalResponses(
            $withdrawalRequest->simplePaginate(10)
        );
    }

    /**
     * Создаем заявку на вывод средств.
     *
     * Financial safety: one InnoDB transaction locks user_balance FOR UPDATE,
     * re-checks unpaid pending rows and balance under that lock, then creates
     * at most one unpaid withdrawal and drains available balance to 0.
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_currency' => 'required|numeric',
            'score' => 'required|min:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $code_currency = [];
        if ((int) iEXSetting('id_referral_code_currency') > 0) {
            $found = CodeCurrency::find((int) iEXSetting('id_referral_code_currency'));
            if ($found) {
                $code_currency = $found->toArray();
            }
        }

        $currency = Currency::find(intval($request->get('id_currency')));
        if (! $currency) {
            return response()->json([
                'status' => 1,
                'message' => 'Указана некорректная валюта выплаты',
            ], 422);
        }

        $client_wallet = security_xss($request->get('score'));
        $user_id = (int) Auth::id();
        $code_name = $code_currency['name'] ?? '';

        // Wallet destination validation does not mutate financial state — keep outside lock.
        if ($currency->validation_account_from && ! wallet_validator($currency->validation_account_from, $client_wallet)) {
            return response()->json([
                'status' => 1,
                'message' => 'Указан неправильный номер кошелька '.$currency->payment->name,
            ], 422);
        }

        if (empty($currency->validation_account_from) && $currency->min_char > 0) {
            if (strlen($request->score) < $currency->min_char || strlen($request->score) > $currency->max_char) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Указан неправильный номер кошелька '.$currency->payment->name,
                ], 422);
            }
        }

        try {
            $outcome = DB::transaction(function () use ($request, $currency, $client_wallet, $user_id, $code_currency, $code_name) {
                // Account-specific authority lock — serializes concurrent creates for this user.
                $balanceRow = DB::table('user_balance')
                    ->where('id_user', $user_id)
                    ->lockForUpdate()
                    ->first();

                if (! $balanceRow) {
                    return [
                        'http' => 422,
                        'body' => [
                            'status' => 1,
                            'message' => 'Баланс партнёра не найден',
                        ],
                    ];
                }

                // SoftDeletes excludes rejected/cancelled rows that were soft-deleted.
                $pendingCount = WithdrawalRequest::query()
                    ->where('id_user', $user_id)
                    ->where('status', 0)
                    ->count();

                if ($pendingCount > 0) {
                    return [
                        'http' => 409,
                        'body' => [
                            'status' => 1,
                            'code' => 'PARTNER_WITHDRAWAL_ALREADY_PENDING',
                            'message' => 'У вас уже есть активная заявка на вывод средств',
                            'is_withdrawal' => false,
                        ],
                    ];
                }

                $final_balance = (float) $balanceRow->balance;
                $minimum_bonus_payout = (float) iEXSetting('minimum_bonus_payout');

                if ($final_balance < $minimum_bonus_payout) {
                    return [
                        'http' => 422,
                        'body' => [
                            'status' => 1,
                            'message' => 'Минимальная сумма выплаты '.$minimum_bonus_payout.' '.$code_name,
                        ],
                    ];
                }

                $partner_balance = $this->convertTo($currency, $final_balance, $code_currency);

                $withdrawal_request = WithdrawalRequest::create([
                    'id_user' => $user_id,
                    'id_currency' => intval($request->get('id_currency')),
                    'score' => $client_wallet,
                    'status' => 0,
                    'balance_referral' => $partner_balance,
                    'view_balance_referral' => $partner_balance,
                    'base_referral' => (float) $final_balance,
                    'ip' => $request->ip(),
                    'tx_id' => (string) Str::uuid(),
                ]);

                DB::table('user_balance')->where('id_user', $user_id)->update([
                    'balance' => 0,
                ]);

                return [
                    'http' => 200,
                    'body' => [
                        'status' => 0,
                        'is_withdrawal' => false,
                    ],
                    'model' => $withdrawal_request,
                ];
            });
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => 1,
                'message' => 'Не удалось создать заявку на вывод средств',
            ], 500);
        }

        if (($outcome['http'] ?? 500) === 200 && isset($outcome['model'])) {
            $withdrawal_request = $outcome['model'];

            iEXApp::telegramNotificationForChannel('order_bonuses_withdrawal', $withdrawal_request);

            if (iEXSetting('mail_order_withdrawal')) {
                SmartMailer::dispatch(
                    sendable: 'admin_new_payouts_job',
                    model: $withdrawal_request,
                    delaySeconds: 15,
                    queue: 'low'
                );
            }

            if (iEXSetting('is_verified_payouts_bonus') == 1) {
                SmartMailer::dispatch(
                    sendable: 'user_verified_payouts_job',
                    model: $withdrawal_request,
                    delaySeconds: 15,
                    queue: 'low'
                );
            }
        }

        return response()->json($outcome['body'] ?? ['status' => 1], $outcome['http'] ?? 500);
    }


    public function confirmWithdrawal(Request $request)
    {
        $payouts = WithdrawalRequest::where('tx_id', security_xss($request->hashId));

        // Если не найдена транзакция
        if (! $payouts->exists()) {
            return response()->json([
                'status' => 1
            ]);
        }

        $item = $payouts->first();
        if (is_null($item->verified_at)) {
            $payouts->update([
                'verified_at' => Carbon::now()->toDateTimeString(),
            ]);
        }

        return response()->json([
            'status' => 0
        ]);
    }

    /**
     * Конвертируем базовую суму
     */
    private function convertTo($currency, $amount, array $code_currency): float
    {
        $final_balance = $amount;
        if ($currency->payout_commission > 0) {
            $final_balance = ($amount - $amount / 100) * $currency->payout_commission;
        }

        // Код конвертации
        $convert_code = Str::upper($code_currency['name'] ?? '');
        // Код выбранной валюты
        $selected_code = Str::upper($currency->code_currency->name);

        // Конвертировать в основную валюту
        $converter = calculator_converter($convert_code, $selected_code, $final_balance);

        return (float) iex_number_format($converter - $currency->payout_commission_amount, $currency->number_format);
    }

}
