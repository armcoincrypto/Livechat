<?php

namespace iEXPackages\ExchangerClient\Http\Controller;

use iEXPackages\ExchangerClient\Http\Resources\SessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController
{
    /**
     * Информация о пользователе
     *
     * @param Request $request
     * @return SessionResource|JsonResponse
     */
    public function index(Request $request): JsonResponse|SessionResource
    {
        // Если пользователь не авторизован
        if (! Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json(new SessionResource($request->user()));
    }
}
