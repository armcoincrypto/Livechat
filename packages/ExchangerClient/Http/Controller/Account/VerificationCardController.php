<?php
namespace iEXPackages\ExchangerClient\Http\Controller\Account;

use App\Models\Currency;
use App\Models\VerificationCard;
use App\Models\VerificationCardCategory;
use iEXPackages\ExchangerClient\Http\Resources\Verification\VerificationCardResources;
use iEXPackages\ExchangerClient\Http\Resources\Verification\VerificationCurrencyResources;
use iEXPackages\ExchangerClient\Http\Resources\Verification\VerificationInstructionResources;
use Illuminate\Http\JsonResponse;

class VerificationCardController
{
    /**
     * Информация о верификации
     */
    public function index(): JsonResponse
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Необходима авторизация'], 401);
        }

        $currencies = Currency::with([
            'payment:id,name,logo',
            'code_currency:id,name'
        ])
            ->where([
                ['status', 0],
                ['is_enabled_verification', '>', 0],
                ['is_verified_cabinet', '=', 1],
            ])
            ->get(['id', 'id_payment', 'id_code_currency', 'is_verified_cabinet', 'is_verified_cabinet']);


        $verifications = VerificationCard::with([
            'currency:id,id_payment,id_code_currency',
            'currency.payment:id,name,logo',
            'currency.code_currency:id,name'
        ])
            ->where('id_user', auth()->id())
            ->orderByDesc('id')
            ->paginate(20);

        $categories = VerificationCardCategory::with('instructions')
            ->where('status', 1)
            ->orderBy('sorting')
            ->get(['id', 'name']);

        return response()->json([
            'payments' => new VerificationCurrencyResources($currencies),
            'cards' => new VerificationCardResources($verifications),
            'instructions' => new VerificationInstructionResources($categories),
        ]);
    }
}
