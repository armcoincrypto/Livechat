<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        // Для API / клиента – вместо редиректа отдаём 401 без route('login')
        if ($request->is('api/*') || $request->expectsJson()) {
            return null; // Laravel просто вернёт JSON с 401
        }

        // Если когда-нибудь будет веб-форма логина,
        // сюда можно вернуть route('login') или route('admin.login')
        return null;
    }
}
