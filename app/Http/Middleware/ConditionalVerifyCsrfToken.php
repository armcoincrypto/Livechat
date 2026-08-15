<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Http\Request;

class ConditionalVerifyCsrfToken extends Middleware
{
    protected $except = [
        // Дополнительные исключения, если нужны
    ];

    protected function tokensMatch($request): bool
    {
        if ($request->header('X-Skip-Csrf') === 'telegram') {
            return true;
        }

        // Иначе стандартная проверка токенов
        return parent::tokensMatch($request);
    }
}
