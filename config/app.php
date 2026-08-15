<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */
    'name' => env('APP_NAME', 'Sitename'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services your application utilizes. Set this in your ".env" file.
    |
    */
    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */
    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL', null),

    'api_url' => env('API_URL', null),

    // Текущий статус ошибок
    'error_reporting' => env('ERROR_REPORTING', -1),

    // Список возможных ошибок
    'error_reporting_list' => [
        0 => 'Все включено',
        1 => 'Только ошибки',
        2 => 'Ошибки и предупреждения',
        3 => 'Не выводить (Не рекомендуется)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'Europe/Moscow'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
   |--------------------------------------------------------------------------
   | Список доступных языков
   |--------------------------------------------------------------------------
   |
   | Список все доступных языков который будет использоваться
    | поставщиком услуг перевода. Вы можете установить это значение
    | на любой из районов, которые будут поддерживаться приложением.
   |
    */
    'all_locale' => [
        // Заполняется автоматически
    ],

    'form_locales' => [
        // Заполняется автоматически
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
      |--------------------------------------------------------------------------
      | Autoloaded Service Providers
      |--------------------------------------------------------------------------
      |
      | The service providers listed here will be automatically loaded on any
      | requests to your application. You may add your own services to the
      | arrays below to provide additional features to this application.
      |
      */

    'providers' => ServiceProvider::defaultProviders()->merge([
        \Cog\Laravel\Ban\Providers\BanServiceProvider::class,
        \Spatie\Sitemap\SitemapServiceProvider::class,
    ])->merge([
        // Application Service Providers...
        // App\Providers\AppServiceProvider::class,
    ])->merge([
        \iEXPackages\Order\OrderServiceProvider::class,
        \iEXPackages\Payment\PaymentServiceProvider::class,
        \iEXPackages\Transaction\TransactionServiceProvider::class,
        \iEXPackages\Update\UpdateClientServiceProvider::class,
        \iEXPackages\Calculator\CalculatorServiceProvider::class,
        \iEXPackages\Courses\CoursesServiceProvider::class,
        \iEXPackages\ExchangerApi\APIServiceProvider::class,
        \iEXPackages\ReferralSystem\ReferralSystemServiceProvider::class,
        \iEXPackages\AMLPlugin\AMLServiceProvider::class,
        \iEXPackages\BestChange\BestChangeServiceProvider::class,
        \iEXPackages\ExchangerClient\ClientServiceProvider::class,
        \iEXPackages\TagProcessors\TagProcessorsServiceProvider::class,
        \iEXPackages\SmartMailer\SmartMailerServiceProvider::class,
        \iEXPackages\Analytics\AnalyticsServiceProvider::class,
        \iEXPackages\BinInspector\BinInspectorServiceProvider::class,
        \iEXPackages\Rare\ProxiesFilter\ProxyFilterServiceProvider::class,
        \iEXPackages\OnlinePresence\OnlinePresenceServiceProvider::class,
        \iEXPackages\Payments\PaymentsServiceProvider::class,
        \iEXPackages\OrderRecount\OrderRecountServiceProvider::class,
        \iEXPackages\GeoIp\GeoIpServiceProvider::class,
        \iEXPackages\Proxy\ProxyServiceProvider::class,
        \iEXPackages\AuthAudit\AuthAuditServiceProvider::class,
        \iEXPackages\OrderChat\OrderChatServiceProvider::class,
        \iEXPackages\SupportChat\SupportChatServiceProvider::class,
    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. You may add any additional class aliases which should
    | be loaded to the array. For speed, all aliases are lazy loaded.
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        'IRedis' => Illuminate\Support\Facades\Redis::class,
        'Google2FA' => PragmaRX\Google2FALaravel\Facade::class,
        'ReferralSystem' => \iEXPackages\ReferralSystem\ReferralSystemFacade::class,
        'Transaction' => \iEXPackages\Transaction\Facades\TransactionFacade::class,
        'Order' => \iEXPackages\Order\Facades\OrderFacade::class,
        'Telegram' => Telegram\Bot\Laravel\Facades\Telegram::class,
        'Excel' => Maatwebsite\Excel\Facades\Excel::class,
        'UpdateClient' => \iEXPackages\Update\Facades\UpdateClientFacade::class,
        'Calculator' => \iEXPackages\Calculator\CalculatorFacade::class,
        'iEXApp' => \App\Support\Facades\iEXApp::class,
        'AML' => \iEXPackages\AMLPlugin\Facades\AMLFacade::class,
        'MailDispatcher' => \iEXPackages\SmartMailer\Facades\SmartMailer::class,
        'SystemLogger' => App\Facades\SystemLogger::class,
    ])->toArray(),
];
