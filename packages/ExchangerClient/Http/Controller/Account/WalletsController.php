<?php
namespace iEXPackages\ExchangerClient\Http\Controller\Account;

use App\Models\Currency;
use App\Models\UserWalletStories;
use iEXPackages\ExchangerClient\Http\Resources\Account\WalletCurrencyResources;
use iEXPackages\ExchangerClient\Http\Resources\Account\WalletResources;

class WalletsController
{
    /**
     * Список разрешенных валют, которые будут отображены в разделе "Мои кошельки"
     *
     * @return WalletCurrencyResources
     */
    public function currencies(): WalletCurrencyResources
    {
        $currencies = Currency::with([
            'currency_in_fields', 'payment', 'code_currency', 'commands', 'currency_out_fields',
        ])->where('status', '=', 0)->get()->filter(function ($item) {
            return ! in_array($item->id, explode(',', iEXSetting('ids_currencies_account_my_wallets')));
        })->keyBy('id');

        return new WalletCurrencyResources($currencies);
    }

    /**
     * Информация по счетам
     *
     * @return WalletResources
     */
    public function wallets(): WalletResources
    {
        $info = UserWalletStories::where([
            'id_user' => auth()->id(),
        ])->get();

        return new WalletResources($info);
    }

    /**
     * Информация по счетам
     *
     * @param int $id
     * @return WalletResources
     */
    public function destroy(int $id): WalletResources
    {
        UserWalletStories::where([
            ['id_user', auth()->id()],
            ['id', $id],
        ])->delete();

        return $this->wallets();
    }
}
