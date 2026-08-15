<?php

namespace iEXPackages\ExchangerApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class StateController extends AbstractAPIController
{
    /**
     * Проверка соединения с API и состояния авторизации.
     */
    public function health(Request $request): JsonResponse
    {
        $user = $request->user();

        $authenticated = (bool) $user;

        // Текущие abilities токена (если используется Sanctum/Personal Access Token)
        $abilities = [];
        if ($authenticated && method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $abilities = $user->currentAccessToken()->abilities ?? [];
        }

        // Формируем ответ
        return Response::json([
            'type'       => 'health',
            'attributes' => [
                'status'        => 'ok',
                'authenticated' => $authenticated,
                'user'          => $authenticated ? [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ] : null,
                'abilities'     => $abilities,
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'app'       => config('app.name'),
                'version'   => config('iexexchanger.version') ?? null,
            ],
        ]);
    }
}
