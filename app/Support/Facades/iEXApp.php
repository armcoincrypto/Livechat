<?php

namespace App\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 *
 * @see \App\Support\iEXApplication
 */
class iEXApp extends Facade
{
    /**
     * Получить зарегистрированное имя компонента.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'iexexchanger-app';
    }
}
