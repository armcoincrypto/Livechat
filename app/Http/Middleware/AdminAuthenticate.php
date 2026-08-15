<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;

class AdminAuthenticate extends Authenticate
{
    /**
     * Получаем путь для перенаправления неавторизованного пользователя.
     */
    protected function redirectTo(Request $request): ?string
    {
        if (! $request->expectsJson()) {
            return route('logins');
        }

        return null;
    }

    /**
     * Обработка неавторизованных запросов, в том числе AJAX и API.
     */
    protected function unauthenticated($request, array $guards)
    {
        if ($request->is(config('iexexchanger.admin_folder'))
            || $request->is(config('iexexchanger.admin_folder') . '/*')
            || $request->is('frontend-api')
            || $request->is('frontend-api/*')
            || $request->expectsJson()) {
            throw new AuthenticationException('Unauthenticated.', $guards);
        }

        throw new AuthenticationException('Unauthenticated.', $guards, $this->redirectTo($request));
    }
}
