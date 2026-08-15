<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Basic\UserWalletsResources;
use App\Models\Currency;
use App\Models\Task;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class UserWalletController extends Controller
{
    /**
     * Получить доступ к счетам пользователей
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $wallets = Task::filter($request->all())->where('status', '=', 4)
            ->whereNotNull('to_shot')
            ->orderByDesc('id')->paginate(20);

        // Резервы
        $currencies = Currency::where('status', '=', 0)->get()->map(function ($item) {
            return [
                'value' => sprintf('%s %s', $item->payment->name, $item->code_currency->name),
                'id' => $item->id,
            ];
        });


        return response()->json([
            'items' => new UserWalletsResources($wallets),
            'currencies' => $currencies
        ]);
    }
}
