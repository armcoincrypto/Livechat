<?php
declare(strict_types=1);


use App\Http\Controllers\Administrator\AuthorizationController;
use App\Http\Controllers\Callbacks\DiditWebhookController;
use App\Http\Controllers\Callbacks\MerchantCallbackController;
use App\Http\Controllers\Callbacks\ReceiveMoneyController;
use App\Http\Controllers\Callbacks\WebhookController;
use App\Http\Controllers\PaymentStatusController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Здесь только "браузерные" маршруты (web middleware: cookies/session/csrf).
| Внешние callbacks/webhooks мы оставляем здесь, но явно отключаем CSRF.
|--------------------------------------------------------------------------
*/

// Локальные маршруты для разработки (никогда не подключаются в продакшене)
if (app()->environment('local')) {
    $localRoutesPath = base_path('routes/test.php');

    if (is_file($localRoutesPath)) {
        require $localRoutesPath;
    }
}


/**
 * Важно:
 * routes/channels.php обычно подключается в BroadcastServiceProvider.
 * Если у вас уже так — эту строку лучше удалить.
 * Если же проект исторически подключает channels тут — оставляем.
 */
require base_path('routes/channels.php');

// Авторизация в панели управления
Route::prefix(config('iexexchanger.admin_folder') . '-frontend')->group(function () {
    Route::get('/logout', [AuthorizationController::class, 'logout'])
        ->middleware('admin.auth')
        ->name('admin.logout');
});


Route::prefix('callbacks/v1')->group(function () {
    Route::match(['GET','POST'], '/receive_money/{payment_system}/{security_hash?}', [MerchantCallbackController::class, 'receive'])
        ->name('merchant.receive_money');

    Route::match(['GET','POST'], '/webhook/{payment_system}/{security_hash?}', [MerchantCallbackController::class, 'webhook'])
        ->name('merchant.webhook');

    Route::post('/telegram-operator', [\App\Http\Controllers\Callbacks\TelegramOperatorWebhookController::class, 'handle'])
        ->middleware([\App\Http\Middleware\VerifyTelegramOperatorWebhookSecret::class])
        ->name('telegram.operator.webhook');
});

// Didit KYC provider webhook (CSRF-exempt via bootstrap validateCsrfTokens except)
Route::post('/apis/provider/didit/webhook', DiditWebhookController::class)
    ->name('provider.didit.webhook');
Route::match(['GET', 'HEAD'], '/apis/provider/didit/webhook', static function () {
    return response('Method Not Allowed', 405)->header('Allow', 'POST');
})->name('provider.didit.webhook.method_not_allowed');


//Route::prefix('callbacks')->group(function () {
//    Route::prefix('v1')->group(function () {
//        Route::post('/webhook/{payment_system}/{security_hash?}', [WebhookController::class, 'status'])->name('merchant.webhook');
//
//        // Стандартный callback
//        Route::match(['get', 'post'], '/receive_money/{payment_system}/{security_hash?}', [ReceiveMoneyController::class, 'status'])
//            ->name('merchant.receive_money');
//
//    });
//});

// Новая версия мерчанта
Route::prefix('payment_status')->controller(PaymentStatusController::class)->group(function () {
    Route::get('/checkout/{hash}', 'checkout')->name('merchant.checkout');
});


Route::get('/', function () {
    $payload = [
        'status'  => 'ok',
        'message' => 'Это API-сервис. Публичного веб-интерфейса по данному адресу не существует.',
    ];

    return response()
        ->json($payload, 200, ['Content-Type' => 'application/json; charset=UTF-8'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});
