<?php

namespace iEXPackages\ExchangerClient\Http\Controller\Account;

use App\Models\UserWalletStories;
use iEXPackages\ExchangerClient\Http\Resources\Account\WalletResources;

class UserWalletsController
{
    public function index(): WalletResources
    {
        $info = UserWalletStories::where('id_user', auth()->id())
            ->orderByDesc('usage_count')
            ->orderByDesc('created_at')
            ->get()
            ->unique('wallet')
            ->values();

        return new WalletResources($info);
    }
}
