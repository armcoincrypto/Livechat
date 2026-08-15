<?php

use App\Console\Schedule\ExchangeSchedule;
use App\Http\Middleware\AllowedIdAddressesMiddleware;
use App\Http\Middleware\BlacklistMiddleware;
use App\Http\Middleware\CheckUserIpMiddleware;
use App\Http\Middleware\LanguageMiddleware;
use App\Http\Middleware\RecordVisit;
use App\Http\Middleware\RequestIdMiddleware;
use App\Http\Middleware\UserAllowedIdAddressMiddleware;
use iEXPackages\ExchangerApi\Http\Middleware\AccessRouteMiddleware;
use iEXPackages\ExchangerApi\Http\Middleware\ApiLoggerMiddleware;
use iEXPackages\OnlinePresence\Middleware\TrackOnlinePresence;
use iEXPackages\Rare\ProxiesFilter\Middleware\ProxiesFilterMiddleware;
use iEXPackages\ReferralSystem\Middleware\CaptureReferralMiddleware;
use iEXPackages\WorkStatus\Middleware\WorkStatusMiddleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders()
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        then: function () {
            Route::permanentRedirect('/.well-known/carddav', '/');
            Route::permanentRedirect('/.well-known/caldav', '/');
            Route::permanentRedirect('/.well-known/security.txt', '/');
            Route::permanentRedirect('/error.log', '/');

            Route::middleware('web')
                ->prefix(config('iexexchanger.admin_folder'))
                ->group(base_path('routes/admin-frontend.php'));

            Route::middleware('web')
                ->namespace('App\Http\Controllers')
                ->group(base_path('routes/web.php'));
        }
    )
    ->withCommands([
        \iEXPackages\Rare\ProxiesFilter\Commands\Reload::class,
        \iEXPackages\Rare\ProxiesFilter\Commands\View::class,
    ])
    ->withEvents(discover: app_path('Listeners'))
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->trustHosts();
        $middleware->statefulApi();
        $middleware->getGlobalMiddleware();
        $middleware->getMiddlewareGroups();
        $middleware->use([
            \App\Http\Middleware\TrustProxies::class,
            \Illuminate\Http\Middleware\HandleCors::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
            \Illuminate\Http\Middleware\ValidatePostSize::class,
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
            RequestIdMiddleware::class,
            ProxiesFilterMiddleware::class
        ]);

        $middleware->web(append: [
            CaptureReferralMiddleware::class,
            TrackOnlinePresence::class,
            RecordVisit::class,
            WorkStatusMiddleware::class,
            LanguageMiddleware::class,
            Illuminate\Session\Middleware\AuthenticateSession::class,
            CheckUserIpMiddleware::class,
        ]);

        $middleware->group('admin', [
            'permission:allow_admin',
            'allowed-ip-address-admin',
            UserAllowedIdAddressMiddleware::class // Защита учетной записи
        ]);

        $middleware->group('frontend', [
            'web',
            'xss-filter',
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class
        ]);

        $middleware->group('client-web', [
            'forbid-banned-user',
            'xss-filter',
            BlacklistMiddleware::class,
        ]);

        $middleware->appendToGroup('api', [
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
        ]);

        $middleware->alias([
            'xss-filter' => \App\Http\Middleware\XssFilterMiddleware::class,
            'api.ability' => AccessRouteMiddleware::class,
            'api_logger' => ApiLoggerMiddleware::class,
            'auth' => \App\Http\Middleware\Authenticate::class,
            'admin.auth' => \App\Http\Middleware\AdminAuthenticate::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'signed' => \App\Http\Middleware\ValidateSignature::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            '2fa' => \App\Http\Middleware\Google2FAMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'forbid-banned-user' => \App\Http\Middleware\ForbidBannedUser::class,
            'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
            'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            'allowed-ip-address-admin' => AllowedIdAddressesMiddleware::class,
            'is_reading_mode' => \App\Http\Middleware\IsReadingModeMiddleware::class,
            'force-json' => \App\Http\Middleware\ForceJsonResponse::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);

        $middleware->priority([
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Auth\Middleware\Authenticate::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'broadcasting/*',
            'payment_status/*',
            'callbacks/*',
            'api/*',
            'apis/provider/*',
            'events/broadcasting/*',
            'resend/*',
            'client-api/*'
        ]);


    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);

        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e) =>
            $request->is('api/*') || $request->is('frontend/*') || $request->expectsJson()
        );

        $exceptions->render(function (\App\Services\Orders\ManualCompletion\ManualCompletionException $e, Request $request) {
            return $e->toJsonResponse();
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            $unauthenticatedResponse = response()->json(['message' => 'Unauthenticated.'], 401);

            if ($request->is(config('iexexchanger.admin_folder') . '/*')) {
                return $request->expectsJson() ? $unauthenticatedResponse : redirect()->guest('/');
            }

            return $request->wantsJson()
                ? response()->json(['success' => false, 'error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]], 401)
                : redirect()->guest('/');
        });
    })
    ->withSchedule(function (Schedule $schedule) {
        ExchangeSchedule::register($schedule);
    })
    ->create();
