<?php

namespace iEXPackages\ExchangerClient\Http\Controller\Account;

use App\Models\RewardProgram;

class DiscountsController
{

    public function index() {

        $cashback = RewardProgram::get()->map(function ($item) {
            return [
                'id' => $item->id,
                'type' => 'cashback',
                'attributes' => [
                    'label' => $item->title,
                    'name' => $item->name,
                    'amount' => $item->amount,
                    'value' => $item->percent
                ],
            ];
        });



        return response()->json([
            'list' => $cashback,
            'is_enabled' => (int)iEXSetting('is_discount_disabled', 0),
            'description' => iEXContentLanguage('cashback_text'),
        ]);
    }
}
