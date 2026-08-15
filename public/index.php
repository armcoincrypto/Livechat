<?php

declare(strict_types=1);

use Illuminate\Http\Request;

if (version_compare(phpversion(), '8.2', '<')) {
    exit('The current version is not supported Update version PHP');
}

define('LARAVEL_START', microtime(true));
define('IEXBASE_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
