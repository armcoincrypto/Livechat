<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LanguageMiddleware
{
    /**
     * Обработка входящего запроса и установка языка приложения.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Получаем все доступные языки из конфига
        $localesConfig = config('app.all_locale', []);
        $defaultLocale = config('app.locale', 'ru');
        $availableLocales = array_keys($localesConfig ?: [$defaultLocale]);

        // Пытаемся определить язык из заголовка Accept-Language
        $requestedLocale = strtolower(substr($request->server('HTTP_ACCEPT_LANGUAGE', $defaultLocale), 0, 2));

        if (!in_array($requestedLocale, $availableLocales)) {
            $requestedLocale = $defaultLocale;
        }

        // Получаем текущий язык из сессии или устанавливаем дефолтный
        $currentLocale = $request->session()->get('language', $defaultLocale);

        // Меняем язык в сессии, только если он валиден и отличается от текущего
        if ($requestedLocale !== $currentLocale) {
            $request->session()->put('language', $requestedLocale);
        } elseif (!$request->session()->has('language')) {
            $request->session()->put('language', $defaultLocale);
        }

        // Устанавливаем язык для приложения, кроме админки
        if (!$request->is(config('iexexchanger.admin_folder') . '/*')) {
            app()->setLocale($request->session()->get('language', $defaultLocale));
        }

        return $next($request);
    }
}
