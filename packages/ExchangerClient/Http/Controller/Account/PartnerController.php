<?php

namespace iEXPackages\ExchangerClient\Http\Controller\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use iEXPackages\ExchangerClient\Http\Resources\Account\PartnerReferralsResources;
use iEXPackages\ReferralSystem\Models\ReferralLink;
use iEXPackages\ReferralSystem\Models\ReferralLog;
use iEXPackages\ReferralSystem\Models\ReferralRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PartnerController extends Controller
{
    /**
     * Информация о партнерской программе
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = User::find(auth()->id());
        $referral_link = ReferralLink::where('user_id', auth()->id())->first();
        $referral_count = ReferralRelationship::where('referral_link_id', '=', $referral_link->id)->count();

        $response = ReferralLog::with(['tasks' => function ($q) {
            $q->select('id', 'public_id', 'id_direction_exchange');
        }, 'user' => function ($q) {
            $q->select('id', 'name');
        }])->where('id_user', auth()->id())
            ->where('bonus_number', '>', 0)->orderBy('id_task', 'desc');

        //Общая сумма заработка за рефералы
        $sum_amount_confirmed = ReferralLog::where('id_user', auth()->id())
            ->where('status', 'confirmed')
            ->sum('bonus_number');


        // Выделяем правильный процент
        $currentPercent = $referral_link->program->percent;
        if($user->personal_ref_discount > 0) {
            $currentPercent = $user->personal_ref_discount;
        }


        $typeBonus = $user->partner_method > 0
            ? ($user->partner_method === 1 ? 0 : 1)
            : (int) iEXSetting('type_partner_deductions');


        // Статистика и реферальный процент
        $info = [
            'percent' => $currentPercent,
            'partner_method' => $typeBonus,
            'count_referral' => $referral_count,
            'total_balance' => $user->user_balance->referral_total_profit,
            'total_withdrawal' => $user->user_balance->referral_total_withdrawal,
            'code_sign' => config('partners-bonus.name') ?? '',
            'total_orders' =>  $response->count(),
        ];

        return response()->json([
            'info' => $info,
            'user' => [
                'referral_hash' => $user->referralLink->getHashAttribute(),
                'referral_hash_url' => config('app.frontend_url').'/?ref='.$user->referralLink->getHashAttribute(),
                'balance' => $user->user_balance->balance,
            ],
            'statistics' => [
                'earned' => number_format($sum_amount_confirmed, 2, '.', ' '),
                'attracted' => $referral_link->relationships()->count(),
            ],
            'settings' => [
                'is_referral_disable_history_orders' => (int) iEXSetting('is_referral_disable_history_orders', 0),
                'is_referral_disable_profit_ref' => (int) iEXSetting('is_referral_disable_profit_ref', 0),
                'is_referral_disable_involved_clients' => (int) iEXSetting('is_referral_disable_involved_clients', 0),
                'is_referral_disable_profit_money' => (int) iEXSetting('is_referral_disable_profit_money', 0),
            ],
        ]);
    }

    /**
     * Обновляем реферальную ссылку
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateRefName(Request $request): JsonResponse
    {
        $validate = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:referral_links,code|regex:/(^([a-zA-Z]+)(\d+)?$)/u',
        ]);

        $user = $request->user();
        if ($validate->fails())
        {
            return response()->json([
                'status' => 1,
                'message' => $validate->errors()->first(),
            ], 422);

        }
        $user->update([
            'username' => security_xss($request->get('username')),
        ]);

        ReferralLink::where('user_id', $user->id)->update([
            'code' => security_xss($request->get('username')),
        ]);

        return response()->json([
            'status' => 0,
            'message' => __('Данные обновлены'),
        ]);
    }

    /**
     * История обменов
     *
     * @param Request $request
     * @return PartnerReferralsResources
     */
    public function histories(Request $request): PartnerReferralsResources
    {
        $response = ReferralLog::with(['tasks' => function ($q) {
            $q->select('id', 'public_id', 'id_direction_exchange');
        }, 'user' => function ($q) {
            $q->select('id', 'name');
        }])->where('id_user', $request->user()->id)
            ->where('bonus_number', '>', 0)->orderBy('id_task', 'desc');

        return new PartnerReferralsResources($response->simplePaginate(10));
    }
}
