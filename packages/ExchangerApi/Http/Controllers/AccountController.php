<?php

namespace iEXPackages\ExchangerApi\Http\Controllers;

use iEXPackages\ExchangerApi\Http\Resources\Account\AccountInfoResource;
use Illuminate\Http\Request;

class AccountController extends AbstractAPIController
{
    /**
     * Получаем информацию об учетной записи
     */
    public function getAccountInfo(Request $request): AccountInfoResource
    {
        return new AccountInfoResource($request->user());
    }
}
