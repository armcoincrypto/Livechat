<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class SystemLogger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\Logging\SystemLogService::class;
    }
}
