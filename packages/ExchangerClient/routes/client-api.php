<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerificationController;
use iEXPackages\ExchangerClient\Http\Controller\Account\DiscountsController;
use iEXPackages\ExchangerClient\Http\Controller\Account\IdentityVerifyController;
use iEXPackages\ExchangerClient\Http\Controller\Account\OrdersController;
use iEXPackages\ExchangerClient\Http\Controller\Account\PartnerController;
use iEXPackages\ExchangerClient\Http\Controller\Account\PartnerWithdrawalController;
use iEXPackages\ExchangerClient\Http\Controller\Account\PersonalController;
use iEXPackages\ExchangerClient\Http\Controller\Account\UserWalletsController;
use iEXPackages\ExchangerClient\Http\Controller\Account\VerificationCardController;
use iEXPackages\ExchangerClient\Http\Controller\Account\WalletsController;
use iEXPackages\ExchangerClient\Http\Controller\Auth\LoginController;
use iEXPackages\ExchangerClient\Http\Controller\Auth\LogoutController;
use iEXPackages\ExchangerClient\Http\Controller\Auth\RegisterController;
use iEXPackages\ExchangerClient\Http\Controller\ContactController;
use iEXPackages\ExchangerClient\Http\Controller\ConversionLiftController;
use iEXPackages\ExchangerClient\Http\Controller\Content\FAQController;
use iEXPackages\ExchangerClient\Http\Controller\Content\NewsController;
use iEXPackages\ExchangerClient\Http\Controller\Content\PageController;
use iEXPackages\ExchangerClient\Http\Controller\Operations\OperationsController;
use iEXPackages\ExchangerClient\Http\Controller\Operations\OrderPayController;
use iEXPackages\ExchangerClient\Http\Controller\Others\ContestsController;
use iEXPackages\ExchangerClient\Http\Controller\Others\ContestsFAQController;
use iEXPackages\ExchangerClient\Http\Controller\PartnersController;
use iEXPackages\ExchangerClient\Http\Controller\ReviewsController;
use iEXPackages\ExchangerClient\Http\Controller\SendImage\ImageAttachCheckController;
use iEXPackages\ExchangerClient\Http\Controller\SendImage\ImageVerificationAccountController;
use iEXPackages\ExchangerClient\Http\Controller\SendImage\ImageVerificationCardController;
use iEXPackages\ExchangerClient\Http\Controller\SessionController;
use iEXPackages\ExchangerClient\Http\Controller\StartController;
use iEXPackages\ExchangerClient\Http\Controller\TechController;
use iEXPackages\ExchangerClient\Http\Controller\Telegram\TelegramOrdersController;
use iEXPackages\OrderChat\Http\Controllers\Client\OrderChatController;
use Illuminate\Support\Facades\Route;


Route::get('/start', [StartController::class, 'index']);

// Информация о сессии пользователя
Route::get('/session', [SessionController::class, 'index']);

// Пинг, чтобы записывать с cookie входные данные
Route::get('/ping', function () {
    return response()->json(['timestamp' => time() * 1000, 'ping' => 1]);
});

Route::middleware(['throttle:60,1'])->prefix('auth')->group(function () {
    Route::post('/login', [LoginController::class, 'login'])->middleware(['guest']);
    Route::post('/register', [RegisterController::class, 'store'])->middleware(['guest']);

    Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware(['guest'])->name('password.email');
    Route::post('password/reset', [ResetPasswordController::class, 'reset'])->middleware(['guest'])->name('password.update');
});

// Сбросить пароль
Route::get('/email/resend', [VerificationController::class, 'resendMail'])->middleware('auth')->name('verification.resend');


Route::get('/tech', [TechController::class, 'index']);

// P10.3 — anonymized conversion proof for homepage (cached, no PII)
Route::get('/conversion/lift', [ConversionLiftController::class, 'index']);

// Получаем данные по валютам из главной страницы
Route::prefix('rates')->group(function () {
    Route::get('/', [OperationsController::class, 'index']);
    Route::get('/update', [OperationsController::class, 'update']);
    Route::get('/operations/{buy}/{sell}', [OperationsController::class, 'show'])
        ->where(['buy' => '[0-9A-Za-z]+', 'sell' => '[0-9A-Za-z]+']);
});


// Для авторизованных клиентов
Route::middleware(['auth'])->group(function ()
{
    Route::get('user_wallets', [UserWalletsController::class, 'index']);

    Route::group(['prefix' => 'account', 'as' => 'account.'], function ()
    {
        Route::get('/logout', [LogoutController::class, 'logout']);

        // Работа с заявками
        Route::get('/orders', [OrdersController::class, 'index']);
        Route::get('/orders/filters', [OrdersController::class, 'filterOptions']);

        Route::prefix('wallet')->controller(WalletsController::class)->group(function () {
            Route::get('/', 'wallets');
            Route::post('/{id}', 'destroy');
            Route::get('/currencies', 'currencies');
        });

        // Получаем скидки и промокоды
        Route::get('/discounts', [DiscountsController::class, 'index']);

        // Верификация учетной записи
        Route::get('/identity-verification', [IdentityVerifyController::class, 'index']);
        Route::post('/identity-verification', [IdentityVerifyController::class, 'store']);
        Route::get('/identity-verification/{id}', [IdentityVerifyController::class, 'status']);


        // Для личного кабинета
        Route::prefix('user')->group(function () {
            Route::get('/', [PersonalController::class, 'index']);
            Route::put('/', [PersonalController::class, 'update']);

            Route::get('/internal', [PersonalController::class, 'internalBalance']);

            Route::get('/updateApiKey', [PersonalController::class, 'updateApiKey']);
            Route::put('/changePassword', [PersonalController::class, 'changePassword']);
        });


        // Список карт
        Route::get('/verifications', [VerificationCardController::class, 'index']);

        // Партнерская программа
        Route::prefix('partners')->group(function () {
            Route::controller(PartnerController::class)->group(function () {
                Route::get('/', 'index');
                Route::put('/',  'updateRefName');
                Route::get('/histories', 'histories');
            });

            // Партнерские выплаты
            Route::controller(PartnerWithdrawalController::class)->group(function() {
                Route::get('/withdrawal', 'withdrawalInfo');
                // Создание заявки на выплату бонусов
                Route::post('/withdrawal', 'create');
                // История выводов
                Route::get('/withdrawal-histories', 'histories');
            });
        });
    });
});


Route::prefix('content')->group(function ()
{
    Route::get('/faq', [FAQController::class, 'index']);
    Route::get('/pages/{page_slug}', [PageController::class, 'show']);

    Route::resource('news', NewsController::class);
});

Route::get('/contacts', [ContactController::class, 'index']);
Route::get('/partners', [PartnersController::class, 'index']);



// Отзывы
Route::prefix('reviews')->group(function () {
    Route::get('/', [ReviewsController::class, 'index']);
    Route::post('/create', [ReviewsController::class, 'create'])->middleware(['auth']);
    Route::get('/links', [ReviewsController::class, 'links']);
});

// Прикрепляем фото
Route::prefix('send')->group(function ()
{
    Route::prefix('image')->group(function () {
        // Отправляем верификацию (possession-gated in controller; dedicated throttle)
        Route::post('/upload-verification', [ImageVerificationCardController::class, 'store'])
            ->middleware('throttle:20,1');
        // Статус проверки верификаций (possession-gated; no unauthenticated mutation)
        Route::get('/{order_id}/status-verification', [ImageVerificationCardController::class, 'status'])
            ->middleware('throttle:60,1');

        // Прикрепляем чек к заявке
        Route::post('/attach-file', [ImageAttachCheckController::class, 'store']);
        // Проверка загруженного файла
        Route::post('/validate-attach-file', [ImageAttachCheckController::class, 'validate']);

        // Отправляем на верификацию через личный кабинет
        Route::post('/upload-verification-account', [ImageVerificationAccountController::class, 'store'])->middleware(['auth']);
    });
});

Route::prefix('order')->group(function ()
{
    Route::post('/', [OrderPayController::class, 'create']);
    Route::get('/{public_id}/process', [OrderPayController::class, 'process']);
    Route::post('/{public_id}/confirm', [OrderPayController::class, 'confirm']);
    Route::get('/{public_id}/status', [OrderPayController::class, 'status']);
    Route::delete('/{public_id}/cancel', [OrderPayController::class, 'cancel']);


    Route::get('/{public_id}',[OrdersController::class, 'show']);
    Route::get('/{public_id}/chat', [OrderChatController::class, 'index']);
    Route::post('/{public_id}/chat', [OrderChatController::class, 'store']);
});

// Конкурсы
Route::prefix('contests')->group(function () {
    Route::get('/', [ContestsController::class, 'index']);
    Route::post('/', [ContestsController::class, 'store']);
    Route::get('/faq', [ContestsFAQController::class, 'index']);
});

Route::prefix('confirms')->group(function () {
    Route::post('/partner-withdrawal/{hashId}', [PartnerWithdrawalController::class, 'confirmWithdrawal']);
    Route::get('/email-verify', [VerificationController::class, 'verify']);
});


Route::prefix('telegram-urls')->group(function () {
    Route::get('/orders', [TelegramOrdersController::class, 'index']);
});
