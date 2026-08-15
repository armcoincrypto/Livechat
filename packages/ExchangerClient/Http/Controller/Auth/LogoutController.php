<?php

namespace iEXPackages\ExchangerClient\Http\Controller\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController
{
    /**
     * Выход из учетной записи
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'status' => 0,
            'message' => __('Вы успешно вышли из учетной записи.')
        ], 200);
    }
}
