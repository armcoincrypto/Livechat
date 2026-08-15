<?php

/*
|--------------------------------------------------------------------------
| Панель администрирования
|--------------------------------------------------------------------------
|
| Здесь собраны все роутерные пути администраторской панели управления
| Настройка ролей, внутренняя авторизация и.т.д
|
*/

use App\Http\Controllers\Administrator\Account\BlockIpController;
use App\Http\Controllers\Administrator\Account\ExtraFieldsController;
use App\Http\Controllers\Administrator\Account\GoogleAuthenticatorController;
use App\Http\Controllers\Administrator\Account\LogController;
use App\Http\Controllers\Administrator\Account\RoleController;
use App\Http\Controllers\Administrator\Account\UserController;
use App\Http\Controllers\Administrator\APIController;
use App\Http\Controllers\Administrator\AuthorizationController;
use App\Http\Controllers\Administrator\Basic\CodeCurrencyController;
use App\Http\Controllers\Administrator\Basic\Currency\Automation\CurrencyMerchantsController;
use App\Http\Controllers\Administrator\Basic\Currency\Automation\CurrencyPaysController;
use App\Http\Controllers\Administrator\Basic\Currency\Others\CurrencyRecountController;
use App\Http\Controllers\Administrator\Basic\Currency\Verifications\CurrencyCardController;
use App\Http\Controllers\Administrator\Basic\Currency\Verifications\CurrencyIdentityController;
use App\Http\Controllers\Administrator\Basic\CurrencyCategoryController;
use App\Http\Controllers\Administrator\Basic\CurrencyCommandController;
use App\Http\Controllers\Administrator\Basic\CurrencyController;
use App\Http\Controllers\Administrator\Basic\CurrencyFieldsController;
use App\Http\Controllers\Administrator\Basic\CurrencyGroupNetworkController;
use App\Http\Controllers\Administrator\Basic\CurrencyLabelsController;
use App\Http\Controllers\Administrator\Basic\CurrencyNotificationController;
use App\Http\Controllers\Administrator\Basic\CurrencyTemplateController;
use App\Http\Controllers\Administrator\Basic\DirectionCityProfilesController;
use App\Http\Controllers\Administrator\Basic\DirectionExchange\Fees\DirectionExchangeProfitController;
use App\Http\Controllers\Administrator\Basic\DirectionExchange\Others\DirectionExchangeRecountController;
use App\Http\Controllers\Administrator\Basic\DirectionExchange\Verifications\DirectionExchangeCardController;
use App\Http\Controllers\Administrator\Basic\DirectionExchange\Verifications\DirectionExchangeIdentityController;
use App\Http\Controllers\Administrator\Basic\DirectionExchangeCommissionController;
use App\Http\Controllers\Administrator\Basic\DirectionExchangeController;
use App\Http\Controllers\Administrator\Basic\DirectionExchangeGroupsController;
use App\Http\Controllers\Administrator\Basic\DirectionExchangeMassEditorController;
use App\Http\Controllers\Administrator\Basic\DirectionExchangeMinPriceController;
use App\Http\Controllers\Administrator\Basic\DirectionExchangeModeController;
use App\Http\Controllers\Administrator\Basic\DirectionFieldsController;
use App\Http\Controllers\Administrator\Basic\DirectionNotificationController;
use App\Http\Controllers\Administrator\Basic\DirectionProfitProfilesController;
use App\Http\Controllers\Administrator\Basic\DirectionRequisitesController;
use App\Http\Controllers\Administrator\Basic\DirectionSelectorFeesController;
use App\Http\Controllers\Administrator\Basic\DirectionTemplateController;
use App\Http\Controllers\Administrator\Basic\ExtraOutProfilesController;
use App\Http\Controllers\Administrator\Basic\FilterCurrencyController;
use App\Http\Controllers\Administrator\Basic\PaymentsController;
use App\Http\Controllers\Administrator\Basic\RequisitesController;
use App\Http\Controllers\Administrator\Basic\RequisitesFieldController;
use App\Http\Controllers\Administrator\Basic\RequisitesGroupController;
use App\Http\Controllers\Administrator\Basic\RequisitesInfoFieldController;
use App\Http\Controllers\Administrator\Basic\ReserveFileGroupController;
use App\Http\Controllers\Administrator\Basic\ReservesController;
use App\Http\Controllers\Administrator\Basic\ReservesFilesController;
use App\Http\Controllers\Administrator\Basic\UserWalletController;
use App\Http\Controllers\Administrator\Bonuses\ConditionController;
use App\Http\Controllers\Administrator\Bonuses\DiscountController;
use App\Http\Controllers\Administrator\Bonuses\PartnerBannersController;
use App\Http\Controllers\Administrator\Bonuses\ReferralController;
use App\Http\Controllers\Administrator\Bonuses\ReferralLogsController;
use App\Http\Controllers\Administrator\Content\AdvantageController;
use App\Http\Controllers\Administrator\Content\ContactController;
use App\Http\Controllers\Administrator\Content\ContactGroupController;
use App\Http\Controllers\Administrator\Content\FaqCategoryController;
use App\Http\Controllers\Administrator\Content\FaqController;
use App\Http\Controllers\Administrator\Content\MenuController;
use App\Http\Controllers\Administrator\Content\NewsCategoryController;
use App\Http\Controllers\Administrator\Content\NewsController;
use App\Http\Controllers\Administrator\Content\PageGroupsController;
use App\Http\Controllers\Administrator\Content\PagesController;
use App\Http\Controllers\Administrator\Content\StatisticsController;
use App\Http\Controllers\Administrator\Crypto\APIKeyController;
use App\Http\Controllers\Administrator\Crypto\BestChangeController;
use App\Http\Controllers\Administrator\Crypto\BestchangeSettingsController;
use App\Http\Controllers\Administrator\Crypto\CompetitorsLinkController;
use App\Http\Controllers\Administrator\Crypto\CompetitorsParserController;
use App\Http\Controllers\Administrator\Crypto\FileParserController;
use App\Http\Controllers\Administrator\Crypto\FileParserGroupController;
use App\Http\Controllers\Administrator\Crypto\FormulaCoefficientController;
use App\Http\Controllers\Administrator\Crypto\ParseController;
use App\Http\Controllers\Administrator\Crypto\ParserFormulaController;
use App\Http\Controllers\Administrator\Crypto\ParserLogController;
use App\Http\Controllers\Administrator\DashboardWidgetController;
use App\Http\Controllers\Administrator\Gateways\AutoPaymentController;
use App\Http\Controllers\Administrator\Gateways\MerchantController;
use App\Http\Controllers\Administrator\Gateways\MerchantFlowEventController;
use App\Http\Controllers\Administrator\Gateways\PaymentGatewayLogController;
use App\Http\Controllers\Administrator\Gateways\PaymentGatewaySettingsController;
use App\Http\Controllers\Administrator\Google2FaController;
use App\Http\Controllers\Administrator\HomeController;
use App\Http\Controllers\Administrator\Marketing\BannerButtonController;
use App\Http\Controllers\Administrator\Marketing\BannerController;
use App\Http\Controllers\Administrator\Marketing\Contests\ContestsConditionController;
use App\Http\Controllers\Administrator\Marketing\Contests\ContestsController;
use App\Http\Controllers\Administrator\Marketing\Contests\ContestsFaqController;
use App\Http\Controllers\Administrator\Marketing\PromoCodesController;
use App\Http\Controllers\Administrator\OnlineController;
use App\Http\Controllers\Administrator\Orders\LevelController;
use App\Http\Controllers\Administrator\Orders\LevelGroupController;
use App\Http\Controllers\Administrator\Orders\OrderArchivesController;
use App\Http\Controllers\Administrator\Orders\OrderAttachedPhotoController;
use App\Http\Controllers\Administrator\Orders\OrderCommentsController;
use App\Http\Controllers\Administrator\Orders\OrderCommentsUserController;
use App\Http\Controllers\Administrator\Orders\OrderLogController;
use App\Http\Controllers\Administrator\Orders\OrderOperatorsController;
use App\Http\Controllers\Administrator\Orders\OrderRecountAuditController;
use App\Http\Controllers\Administrator\Orders\OrderRecountController;
use App\Http\Controllers\Administrator\Orders\OrdersController;
use App\Http\Controllers\Administrator\Orders\OrdersExportController;
use App\Http\Controllers\Administrator\Orders\OrdersStatusController;
use App\Http\Controllers\Administrator\Orders\OrdersStepController;
use App\Http\Controllers\Administrator\Orders\PaymentBonusesController;
use App\Http\Controllers\Administrator\Orders\ReasonsDeferController;
use App\Http\Controllers\Administrator\Orders\ReasonsRejectionController;
use App\Http\Controllers\Administrator\Plugins\AMLServicesController;
use App\Http\Controllers\Administrator\Plugins\CitiesController;
use App\Http\Controllers\Administrator\Plugins\ProxyManagerController;
use App\Http\Controllers\Administrator\SessionController;
use App\Http\Controllers\Administrator\SettingController;
use App\Http\Controllers\Administrator\Settings\ExportRatesExtendedController;
use App\Http\Controllers\Administrator\Settings\Notifications\TelegramNotificationController;
use App\Http\Controllers\Administrator\Settings\SettingsLimitProfilesController;
use App\Http\Controllers\Administrator\StatsController;
use App\Http\Controllers\Administrator\Tools\AuthSystemController;
use App\Http\Controllers\Administrator\Tools\BlackListBestChangeController;
use App\Http\Controllers\Administrator\Tools\BlackListController;
use App\Http\Controllers\Administrator\Tools\CheckboxAgreementController;
use App\Http\Controllers\Administrator\Tools\ExportDataController;
use App\Http\Controllers\Administrator\Tools\GEOIPController;
use App\Http\Controllers\Administrator\Tools\JobController;
use App\Http\Controllers\Administrator\Tools\JobScheduleController;
use App\Http\Controllers\Administrator\Tools\LinkFooterGroupController;
use App\Http\Controllers\Administrator\Tools\LinksFootersController;
use App\Http\Controllers\Administrator\Tools\LinksReviewsController;
use App\Http\Controllers\Administrator\Tools\LinksReviewsGroupController;
use App\Http\Controllers\Administrator\Tools\NotificationController;
use App\Http\Controllers\Administrator\Tools\PaymentExplorerController;
use App\Http\Controllers\Administrator\Tools\ReviewsController;
use App\Http\Controllers\Administrator\Tools\SocialReviewsController;
use App\Http\Controllers\Administrator\UploadController;
use App\Http\Controllers\Administrator\Verifications\VerificationAccountController;
use App\Http\Controllers\Administrator\Verifications\VerificationCardCategoryController;
use App\Http\Controllers\Administrator\Verifications\VerificationCardController;
use App\Http\Controllers\Administrator\Verifications\VerificationCardInstructionController;
use App\Http\Controllers\Administrator\Vue\DirectionExchangeVueController;
use App\Http\Controllers\Administrator\Vue\LayoutVueController;
use App\Http\Controllers\Administrator\Vue\NotificationEventController;
use App\Http\Controllers\Administrator\Vue\NotificationVueController;
use App\Http\Controllers\Administrator\Support\SupportChatDiagnosticsController;
use App\Http\Controllers\Administrator\Verifications\KycOpsController;
use App\Http\Controllers\Administrator\Support\SupportConversationController;
use App\Http\Controllers\Administrator\Vue\OrderChatsVueController;
use App\Http\Controllers\Administrator\Vue\OrderChatVueController;
use App\Http\Controllers\Administrator\Vue\OrderVueController;
use App\Http\Controllers\Administrator\Vue\SettingsVueController;
use App\Http\Controllers\Administrator\Vue\SortingVueController;
use iEXPackages\BinInspector\Http\Controllers\BinBankRulesController;
use iEXPackages\BinInspector\Http\Controllers\BinInspectorController;
use Illuminate\Support\Facades\Route;


Route::prefix('frontend-api')->middleware('frontend')->group(function ()
{
    Route::post('/uploads', [UploadController::class, 'upload']);
    Route::prefix('auth')->group(function ()
    {
        Route::post('/2faGoogleVerify', [Google2FaController::class, 'verify'])->name('2faGoogleVerify');
        Route::post('/2fa')->name('2fa')->middleware('2fa');

        Route::prefix('session')->group(function () {
            Route::get('/get', [AuthorizationController::class, 'getSession']);
        });
    });

    // Авторизация через Frontend
    Route::post('/login', [AuthorizationController::class, 'loginUser'])->middleware('guest');

    Route::middleware(['2fa', 'is_reading_mode', 'admin.auth', 'admin'])->group(function ()
    {
        Route::get('/ping', [HomeController::class, 'getPing'])->name('ping');
        Route::get('/initialConfig', [HomeController::class, 'getInitialConfig'])->name('initialConfig');

        Route::prefix('session')->group(function () {
            Route::get('current', [SessionController::class, 'getSessions']);

            Route::get('/logout', [AuthorizationController::class, 'logout']);
            // Закрываем все авторизованные сеансы
            Route::post('/logoutOtherDevices',  [SessionController::class, 'logoutOtherDevices'])
                ->middleware('permission:admin_session_destroy');
        });


        Route::controller(HomeController::class)->group(function() {
            Route::any('/', 'index');
            Route::get('/private-image/default', 'imageHandler');
        });


        Route::prefix('analytics')
            ->name('admin.analytics.')
            ->group(function () {
                require base_path('packages/Analytics/Routes/analytics.php');
            });

        Route::prefix('online')->group(function () {
            Route::get('/counters', [OnlineController::class, 'counters']);
            Route::get('/list', [OnlineController::class, 'list']);   // type=user|guest|all
            Route::get('/daily', [OnlineController::class, 'daily']); // from,to
        });

        Route::prefix('support-chat')
            ->middleware('permission:admin_other_online_chat')
            ->group(function () {
                Route::get('/health', [SupportChatDiagnosticsController::class, 'health'])
                    ->name('admin.support-chat.health');
            });

        Route::prefix('/stats')->group(function () {
            Route::get('/visits/hourly',  [StatsController::class, 'visitsHourly']);   // общие визиты по часам
            Route::get('/visits/daily',   [StatsController::class, 'visitsDaily']);    // общие визиты по дням
            Route::get('/visits/monthly', [StatsController::class, 'visitsMonthly']);  // общие визиты по месяцам
            Route::get('/users/top',      [StatsController::class, 'topUsers']);       // топ юзеров
            Route::get('/users/daily',[StatsController::class, 'userDaily']);     // визиты одного юзера по дням
        });

        Route::resource('dashboard-widgets', DashboardWidgetController::class)
            ->middleware(['permission:admin_widgets']);


        Route::prefix('basic')->group(function ()
        {
            Route::middleware(['permission:currencies'])->group(function () {
                Route::prefix('currency')->controller(CurrencyController::class)->group(function () {
                    Route::get('/{id}/delete', 'destroy');
                    Route::post('/{id}/duplicate', 'duplicate');
                });

                Route::resource('currency', CurrencyController::class);


                Route::get('currency/{id}/automation/merchants', [CurrencyMerchantsController::class, 'edit']);
                Route::put('currency/{id}/automation/merchants', [CurrencyMerchantsController::class, 'update']);

                Route::get('currency/{id}/automation/pays', [CurrencyPaysController::class, 'edit']);
                Route::put('currency/{id}/automation/pays', [CurrencyPaysController::class, 'update']);

                Route::get('currency/{id}/verifications/card', [CurrencyCardController::class, 'edit']);
                Route::put('currency/{id}/verifications/card', [CurrencyCardController::class, 'update']);


                Route::get('currency/{id}/verifications/identity', [CurrencyIdentityController::class, 'edit']);
                Route::put('currency/{id}/verifications/identity', [CurrencyIdentityController::class, 'update']);


                Route::get('currency/{id}/others/recount', [CurrencyRecountController::class, 'edit']);
                Route::put('currency/{id}/others/recount', [CurrencyRecountController::class, 'update']);

                Route::post(
                    'currency/{currencyId}/small-code',
                    [CurrencyController::class, 'updateSmallCode']
                );


                Route::resource('currency-extra-out', ExtraOutProfilesController::class);
                Route::resource('currency-labels', CurrencyLabelsController::class);
                Route::resource('currency-templates', CurrencyTemplateController::class);
                Route::resource('currency-notification', CurrencyNotificationController::class);
                Route::resource('currency-group-networks', CurrencyGroupNetworkController::class);
                Route::post('currency-group-networks/{id}/sync-currencies', [CurrencyGroupNetworkController::class, 'syncCurrencies'])
                    ->whereNumber('id');
                Route::resource('currency-categories', CurrencyCategoryController::class);
                Route::post('currency-categories/reorder', [CurrencyCategoryController::class, 'reorder']);
                Route::post('currency-categories/{id}/sync-currencies', [CurrencyCategoryController::class, 'syncCurrencies'])
                    ->whereNumber('id');

                Route::get('/currency-command/{id}/delete', [CurrencyCommandController::class, 'destroy']);
                Route::resource('currency-command', CurrencyCommandController::class);

                // Доп. Поля валют
                Route::resource('currency_fields', CurrencyFieldsController::class);
            });

            Route::get('/user_wallets', [UserWalletController::class, 'index'])->middleware(['permission:user_wallets']);

            Route::get('/requisites/{id}/logs', [RequisitesController::class, 'logs'])->middleware(['permission:admin_requisites_log']);
            Route::middleware(['permission:requisites_system'])->group(function () {
                Route::controller(RequisitesController::class)->prefix('requisites')->group(function () {
                    Route::get('archive', 'archive');
                    Route::put('archive/{id}', 'restoreArchive');
                });
                Route::resource('requisites', RequisitesController::class);

                Route::get('/requisites-groups/{id}/destroy', [RequisitesGroupController::class, 'destroy']);
                Route::resource('requisites-groups', RequisitesGroupController::class);
                Route::resource('requisites-fields', RequisitesFieldController::class);
                Route::resource('requisites-info-fields', RequisitesInfoFieldController::class);

            });

            Route::resource('code_currency', CodeCurrencyController::class)->middleware(['permission:currency_codes']);

            Route::resource('/filter_currency', FilterCurrencyController::class)->middleware(['permission:currency_filters']);

            Route::get('/payments/{id}/delete', [PaymentsController::class, 'delete_url'])->middleware(['permission:payment_system']);
            Route::resource('payments', PaymentsController::class)->middleware(['permission:payment_system']);

            //Направление обмена
            Route::middleware(['permission:direction_exchange'])->group(function()
            {
                Route::prefix('direction_exchange')->group(function () {

                    Route::get('/fields/{id}/delete', [DirectionFieldsController::class, 'delete']);
                    Route::resource('fields', DirectionFieldsController::class)->names('direction_exchange_fields');

                    Route::get('min_price/logs', [DirectionExchangeMinPriceController::class, 'logs']);
                    Route::resource('min_price', DirectionExchangeMinPriceController::class);

                    Route::controller(DirectionExchangeController::class)->group(function () {

                        Route::post('status/bulk', [
                            DirectionExchangeController::class,
                            'bulkChangeStatus'
                        ])->name('direction-exchange.status.bulk');


                        Route::get('/logs/active', [DirectionExchangeController::class, 'activeLogs']);

//                        Route::get('/logs', 'logs');
                        Route::match(['get', 'post'], '/price_adjustment', 'price_adjustment');
                        Route::match(['get', 'post'], '/sorting', 'sorting');
                        Route::match(['get', 'post'], '/sorting/{id}', 'sortingId');
                        Route::match(['get', 'post'], '/unpaid_item', 'unpaid_item');
                        Route::match(['get', 'post'], '/{id}/duplicate', 'duplicate');
                        Route::match(['get', 'post'], '/trashed', 'trashed');
                    });

                    // Массовый редактор направлений
                    Route::get('/mass-direction-editor', [DirectionExchangeMassEditorController::class, 'index'])->name('mass-direction-editor');
                    Route::put('/mass-direction-editor', [DirectionExchangeMassEditorController::class, 'update'])
                        ->name('mass-direction-editor.update');

                    // Шаблоны для валют
                    Route::resource('templates', DirectionTemplateController::class)->name('destroy', 'direction_exchange-templates.destroy');
                });

                Route::resource('direction_exchange', DirectionExchangeController::class);
                Route::get('direction_exchange/{id}/verifications/card', [DirectionExchangeCardController::class, 'edit']);
                Route::put('direction_exchange/{id}/verifications/card', [DirectionExchangeCardController::class, 'update']);
                Route::get('direction_exchange/{id}/verifications/identity', [DirectionExchangeIdentityController::class, 'edit']);
                Route::put('direction_exchange/{id}/verifications/identity', [DirectionExchangeIdentityController::class, 'update']);


                Route::get('direction_exchange/{id}/fees/profit', [DirectionExchangeProfitController::class, 'edit']);
                Route::put('direction_exchange/{id}/fees/profit', [DirectionExchangeProfitController::class, 'update']);


                Route::get('direction_exchange/{id}/others/recount', [DirectionExchangeRecountController::class, 'edit']);
                Route::put('direction_exchange/{id}/others/recount', [DirectionExchangeRecountController::class, 'update']);





                Route::get('/directions-profiles/{id}/delete', [DirectionProfitProfilesController::class, 'destroy']);
                Route::resource('directions-profiles', DirectionProfitProfilesController::class);


                Route::get('/directions-city-profiles/{id}/delete', [DirectionCityProfilesController::class, 'destroy']);
                Route::resource('directions-city-profiles', DirectionCityProfilesController::class);

                Route::get('/direction-exchange-commission/{id}/delete', [DirectionExchangeCommissionController::class, 'destroy']);
                Route::resource('direction-exchange-commission', DirectionExchangeCommissionController::class);

                // Реквизиты для направлений
                Route::resource('direction-requisites', DirectionRequisitesController::class);
                // Реквизиты для направлений
                Route::resource('direction-selector-fees', DirectionSelectorFeesController::class);
                // Уведомление для направлений
                Route::resource('direction-exchange-notification', DirectionNotificationController::class);
                // Режимы работ
                Route::resource('direction-exchange-modes', DirectionExchangeModeController::class);

                Route::post(
                    '/direction-exchange-groups/{id}/mass-editor',
                    [DirectionExchangeGroupsController::class, 'updateGroupMassEditor']
                )->name('direction-exchange-groups.mass-editor');
                Route::resource('direction-exchange-groups', DirectionExchangeGroupsController::class);
            });

            // Файлы из резерва
            Route::middleware(['permission:reserves'])->group(function () {

                Route::get('reserves-files/{id}/delete', [ReservesFilesController::class, 'destroy']);
                Route::resource('reserves-files', ReservesFilesController::class);

                Route::get('reserves-files-group/{id}/delete', [ReserveFileGroupController::class, 'destroy']);
                Route::resource('reserves-files-group', ReserveFileGroupController::class);

                //Корректировка резервов
                Route::get('/reserves/{id}/unattach', [ReservesController::class, 'unattach']);
                Route::match(['get', 'post'], '/reserves/sorting', [ReservesController::class, 'sorting']);
                Route::resource('reserves', ReservesController::class);
            });
        });

        //Платежные шлюзы
        Route::prefix('gateways')->group(function ()
        {

            Route::post('/merchant/auth-token', [MerchantController::class, 'secretKeyIndex']);
            Route::post('/autopayment/auth-token', [AutoPaymentController::class, 'secretKeyIndex']);

            Route::resource('merchant', MerchantController::class)->middleware(['permission:admin_merchant']);
            Route::resource('autopayment', AutoPaymentController::class)->middleware(['permission:admin_autopayment']);


            Route::prefix('logs')->middleware(['permission:admin_merchant_api_logs'])
                ->group(function () {
                    Route::get('/merchants', [PaymentGatewayLogController::class, 'merchants']);
                    Route::get('/payouts', [PaymentGatewayLogController::class, 'payouts']);

                    Route::get(
                        '/merchant-flow-events',
                        [MerchantFlowEventController::class, 'index']
                    );


                    Route::get('/settings', [PaymentGatewaySettingsController::class, 'index']);
                    Route::post('/settings', [PaymentGatewaySettingsController::class, 'update']);
                });
        });

        // Настройки скрипта
        Route::middleware(['permission:admin_settings'])->group(function () {
            // Настройка темы

            Route::resource('settings-limit-profiles', SettingsLimitProfilesController::class);

            Route::post('/settings', [SettingController::class, 'store']);
            Route::get('/settings/{mid?}', [SettingController::class, 'index']);
            Route::put('/settings/{mid?}', [SettingController::class, 'update']);
        });

        // Экспорт курсов
        Route::resource('export-rates-extended', ExportRatesExtendedController::class);

        // Telegram notifications (bots / channels)
        Route::prefix('telegram-notification')->controller(TelegramNotificationController::class)->group(function () {
            // Диагностика канала/чата (ничего не сохраняет)
            Route::get('{id}/check', 'check')
                ->whereNumber('id')
                ->name('telegram-notification.check');

            // Привязка выбранного канала/чата к записи
            Route::post('{id}/bind', 'bind')
                ->whereNumber('id')
                ->name('telegram-notification.bind');
        });

        // CRUD Telegram-уведомлений
        Route::resource('telegram-notification', TelegramNotificationController::class);


        // Плагины
        Route::prefix('plugins')->group(function () {

            Route::resource('aml-services', AMLServicesController::class)->middleware(['permission:admin_plugin_aml']);
            Route::prefix('proxy-manager')
                ->middleware(['permission:admin_plugin_proxy'])
                ->group(function () {
                    Route::get('{id}/history', [ProxyManagerController::class, 'history'])->whereNumber('id');
                    Route::get('{id}/stats', [ProxyManagerController::class, 'stats'])->whereNumber('id');
                    Route::post('{id}/test', [ProxyManagerController::class, 'test'])->whereNumber('id');
                });

            Route::resource('proxy-manager', ProxyManagerController::class)
                ->middleware(['permission:admin_plugin_proxy']);
            Route::resource('cities', CitiesController::class)->middleware(['permission:admin_plugin_cities']);
        });

        Route::prefix('crypto')->group(function () {

            Route::middleware(['permission:admin_parser'])->group(function () {
                // Лог обновлений курсов
                Route::get('/log_update_courses', [ParserLogController::class, 'log_update_courses']);
                Route::resource('api-keys', APIKeyController::class);

                Route::controller(ParseController::class)->group(function () {
                    // Массовое обновление статусов пар по источнику
                    Route::post('/parser/{id}/status/bulk', 'bulkUpdateStatus')
                        ->name('admin.crypto.parser.status.bulk');

                    Route::match(['get', 'post'], '/all_parser', 'items');
                    Route::get('/parser/delete/{id}', 'delete');
                });

                // История курса для графика
                Route::get('parser/{id}/chart', [ParseController::class, 'chart'])
                    ->name('parsers.chart');


                Route::resource('parser', ParseController::class);
            });

            Route::middleware(['permission:admin_parser_bestchange'])->group(function () {

                // Settings
                Route::controller(BestchangeSettingsController::class)->group(function () {
                    Route::get('/bestchange/settings', 'index')->name('admin.crypto.bestchange.settings.index');
                    Route::post('/bestchange/settings', 'update')->name('admin.crypto.bestchange.settings.update');
                });

                // BestChange пары/логи/экшены
                Route::controller(BestChangeController::class)->group(function () {
                    Route::get('/bestchange/log', 'logData')->name('admin.crypto.bestchange.log');
                    Route::post('/bestchange/status/bulk', 'bulkUpdateStatus')->name('admin.crypto.bestchange.status.bulk');

                    Route::post('/bestchange/{id}/favorite-toggle', 'toggleFavorite')->name('admin.crypto.bestchange.favorite-toggle');
                    Route::get('/bestchange/{id}/rating', 'rating')->name('admin.crypto.bestchange.rating');

                    // CRUD (index/store/show/update/destroy + create/edit если используются)
                    Route::resource('bestchange', BestChangeController::class);
                });

            });

            Route::middleware(['permission:admin_parser_competitors'])->group(function () {
                Route::controller(CompetitorsParserController::class)->group(function () {
                    Route::get('competitors-parser/history', 'history');
                    Route::get('competitors-parser/{id}/history', 'historyPair');
                    Route::get('competitors-parser/{id}/delete', 'destroy');
                });
                Route::resource('competitors-parser', CompetitorsParserController::class);

                Route::get('competitors-link/{id}/delete', [CompetitorsLinkController::class, 'destroy']);
                Route::resource('competitors-link', CompetitorsLinkController::class);
            });

            Route::middleware(['permission:admin_parser_formula'])->group(function () {
                Route::get('formula-coefficient/{id}/delete', [FormulaCoefficientController::class, 'destroy']);
                Route::resource('formula-coefficient', FormulaCoefficientController::class);

                // Route::get('/parser-formula/history', [ParserFormulaController::class, 'history']);
                Route::post('/parser-formula/query', [ParserFormulaController::class, 'query']);

                Route::get('/parser-formula/tags-catalog', [ParserFormulaController::class, 'tagsCatalog'])
                    ->name('parser-formula.tags-catalog');


                // История курса для графика
                Route::get('/parser-formula/{id}/chart', [ParserFormulaController::class, 'chart'])
                    ->name('parser-formula.chart');


                Route::resource('parser-formula', ParserFormulaController::class);
            });

            Route::middleware(['permission:admin_parser_file'])->group(function () {
                Route::get('file-parser/{id}/delete', [FileParserController::class, 'destroy']);
                Route::resource('file-parser', FileParserController::class);

                Route::get('file-parser-group/{id}/delete', [FileParserGroupController::class, 'destroy']);
                Route::resource('file-parser-group', FileParserGroupController::class);
            });
        });

        // Список заявок
        Route::prefix('orders')->group(function () {
            Route::resource('list', OrdersController::class)->middleware(['permission:admin_tasks']);
            Route::get('/updateOrderById/{id}', [OrdersController::class, 'updateOrderById'])->middleware(['permission:admin_tasks']);

            Route::prefix('export')->middleware(['permission:admin_export_data'])->name('orders.export.')->group(function () {
                Route::get('/', [OrdersExportController::class, 'index'])->name('index'); // статусы
                Route::post('/', [OrdersExportController::class, 'store'])->name('store'); // запуск экспорта

                // новые:
                Route::get('/recent', [OrdersExportController::class, 'recent']);
                Route::get('/history', [OrdersExportController::class, 'history']);

                // сначала статический download, чтобы он не перекрывался {id}
                Route::get('/download', [OrdersExportController::class, 'downloadExportFile'])
                    ->name('download');

                // потом статус экспорта по id
                Route::get('/{id}', [OrdersExportController::class, 'show'])
                    ->whereNumber('id')
                    ->name('show');
            });

            Route::get('/export/download', [OrdersExportController::class, 'downloadExportFile'])
                ->name('orders.export.download');


            Route::controller(OrderAttachedPhotoController::class)->group(function () {
                Route::get('/{id}/attached-photo', 'index');
                Route::post('/{id}/attached-photo', 'store');
                Route::delete('/attached-photo/{id}', 'destroy');
            });

            Route::controller(OrderCommentsController::class)->group(function () {
                Route::get('/{id}/comments', 'index');
                Route::post('/{id}/comments', 'store');
                Route::delete('/comments/{id}', 'destroy');
            });

            Route::controller(OrderCommentsUserController::class)->group(function () {
                Route::get('/{id}/comments-user', 'index');
                Route::post('/{id}/comments-user', 'store');
                Route::delete('/comments-user/{id}', 'destroy');
            });

            Route::controller(OrderRecountController::class)->group(function () {
                Route::get('/{id}/recount', 'index');
                Route::put('/{id}/recount', 'update');
            });

            Route::get('/{id}/order-recount', [OrderRecountAuditController::class, 'show']);

            Route::controller(OrderOperatorsController::class)->group(function () {
                Route::get('/{id}/operator-histories', 'index');
                Route::post('/{id}/operators', 'store');
                Route::delete('/{id}/operators', 'destroy');
            });

            Route::middleware(['permission:admin_orders_control_status'])->group(function () {
                Route::resource('orders-steps', OrdersStepController::class);
            });
        });

        Route::controller(OrderLogController::class)->prefix('order-logs')->middleware(['permission:admin_orders_logs'])
            ->group(function () {
                Route::match(['get', 'post'], '/statuses', 'getStatusLog');
                Route::get('/aml', 'getAmlLog');
                Route::get('/requisites', 'getRequisitesLog');
            });

        Route::resource('orders-archives', OrderArchivesController::class)->middleware(['permission:admin_order_archive_orders']);
        Route::resource('order-statusses', OrdersStatusController::class)->middleware(['permission:admin_orders_control_status']);
        Route::resource('reasons-rejection', ReasonsRejectionController::class)->middleware(['permission:admin_tasks']);
        Route::resource('reasons-defer', ReasonsDeferController::class)->middleware(['permission:admin_tasks']);
        Route::resource('payment-bonuses', PaymentBonusesController::class)->middleware(['permission:claims_payment']);

        Route::middleware(['permission:admin_orders_causes_limit'])->group(function () {
            Route::resource('operator-levels', LevelController::class);
            Route::resource('operator-levels-group', LevelGroupController::class);
        });

        Route::prefix('journals')->group(function () {
            Route::get('/users-logs', [LogController::class, 'index'])->middleware(['permission:admin_account_logs']);
        });



        Route::prefix('account')->group(function () {
            Route::prefix('users')->group(function () {
                Route::middleware(['permission:admin_users'])->controller(UserController::class)
                    ->group(function () {
                        Route::match(['get', 'post'], '/{id}/download_order', 'download_order');
                        Route::match(['get', 'post'], '/{id}/referral', 'referral');
                        Route::match(['get', 'post'], '/{id}/ban', 'ban');
                        Route::match(['get', 'post'], '/{id}/revoke_user', 'revokeUser');
                        Route::get('/{id}/banned_histories', 'bannedHistories');
                        Route::put('/{id}/balances', 'balances');

                        // Активные сессия
                        Route::get('/{id}/activity_sessions', 'activitySessions')->middleware(['permission:admin_session_logs']);
                        Route::delete('/{id}/delete_sessions', 'deleteActivitySessions')->middleware(['permission:admin_session_destroy']);
                    });

                Route::prefix('google')->controller(GoogleAuthenticatorController::class)
                    ->middleware(['permission:admin_access_google_authentication'])->group(function () {
                        Route::get('/{id}', 'index');
                        Route::put('/{id}/update', 'update');
                        Route::get('/{id}/reset', 'reset');
                    });
            });



            Route::middleware(['permission:admin_users'])->resource('extra_fields', ExtraFieldsController::class);

        Route::controller(UserController::class)
            ->middleware(['permission:admin_users'])
            ->group(function () {
                Route::post('/users/{id}/change-partner', 'changePartner')->name('users.changePartner');
                Route::resource('users', UserController::class);
            });

            Route::middleware('permission:admin_roles')->group(function () {
                Route::resource('roles', RoleController::class);
                Route::get('/roles/{id}/delete', [RoleController::class, 'destroy']);
            });

            Route::get('/admin_logs_auth', [LogController::class, 'admin_logs'])->middleware(['permission:admin_account_logs']);

            // Фильтр по IP, Email ...
            Route::get('/block_ip', [BlockIpController::class, 'index'])->middleware(['permission:admin_blockip']);
            Route::post('/block_ip/create', [BlockIpController::class, 'store'])->middleware(['permission:admin_blockip']);
            Route::delete('/block_ip/{id}/destroy', [BlockIpController::class, 'destroy'])->middleware(['permission:admin_blockip']);

            Route::match(['get', 'post'], '/block_ip', [BlockIpController::class, 'index'])->middleware(['permission:admin_blockip']);
        });

        //Партнерам
        Route::prefix('bonuses')->group(function () {

            Route::get('/referral/logs', [ReferralLogsController::class, 'index'])
                ->name('logs.index');

            Route::controller(ReferralController::class)->middleware('permission:admin_bonuses_program')
                ->group(function () {
                    Route::get('/referral/settings', 'settings');
                    Route::put('/referral/settings', 'settingsUpdate');

                    Route::get('/referral/rate-diagnostics', 'rateDiagnosticsAll');
                });

            Route::resource('referral', ReferralController::class)->middleware('permission:admin_bonuses_program');
            // Система скидок
            Route::resource('discount', DiscountController::class)->middleware('permission:user_discounts');
            // Партнерская информация
            Route::get('/conditions', [ConditionController::class, 'index']);
            Route::put('/conditions', [ConditionController::class, 'update']);


            //Партнеры
            Route::resource('/partner-banners', PartnerBannersController::class)->middleware(['permission:partners']);

        });

        // Верификация
        Route::prefix('verifications')->group(function () {
            // Верификация личности
            Route::resource('accounts', VerificationAccountController::class)->middleware(['permission:admin_verification_account']);

            // Верификация
            Route::middleware(['permission:admin_verification_card'])->group(function () {
                Route::resource('cards', VerificationCardController::class)->name('index', 'verifications-card.index');
                Route::resource('card-category', VerificationCardCategoryController::class);
                Route::resource('card-instructions', VerificationCardInstructionController::class);
            });
        });


        Route::prefix('content')->group(function () {

            Route::resource('menu', MenuController::class)->middleware(['permission:admin_menu']);

            Route::resource('news', NewsController::class)->middleware('permission:admin_news');
            Route::resource('news_category', NewsCategoryController::class);


            Route::middleware('permission:admin_faq')->group(function () {
                Route::post('faq-category/update-status', [FaqController::class, 'updateCategoryStatus']);
                Route::post('faq/update-status', [FaqController::class, 'updateItemStatus']);

                Route::resource('faq-category', FaqCategoryController::class);
                Route::resource('faq', FaqController::class);
            });

            // Статистика
            Route::get('/statistics/{id}/destroy', [StatisticsController::class, 'destroy'])->middleware('permission:admin_statistics_tools');
            Route::resource('statistics', StatisticsController::class)->middleware('permission:admin_statistics_tools');

            // Преимущество
            Route::resource('advantage', AdvantageController::class)->middleware(['permission:admin_advantage']);

            Route::middleware(['permission:admin_pages'])->group(function () {
                Route::resource('pages', PagesController::class);
                Route::resource('page-groups', PageGroupsController::class);
            });


            Route::middleware(['permission:admin_contact'])->group(function () {
                // Статусы (вкл/выкл) — по шаблону FAQ
                Route::post('contacts-group/update-status', [ContactController::class, 'updateGroupStatus']);
                Route::post('contacts/update-status', [ContactController::class, 'updateItemStatus']);

                Route::resource('contacts_group', ContactGroupController::class);
                Route::resource('contacts', ContactController::class);
            });
        });

        Route::prefix('marketing')->group(function ()
        {
            Route::middleware(['permission:admin_banners'])->group(function () {
                Route::resource('banners', BannerController::class);
                Route::get('/banners/{id}/destroy', [BannerController::class, 'destroy']);

                // Кнопки для баннеров
                Route::resource('banners-button', BannerButtonController::class);
                Route::get('/banners-button/{id}/destroy', [BannerButtonController::class, 'destroy']);
            });

            Route::get('promo-codes/{id}/usage-summary', [PromoCodesController::class, 'usageSummary']);
            Route::resource('promo-codes', PromoCodesController::class)->middleware(['permission:admin_plugin_promo_code']);

            Route::prefix('contests')->middleware(['permission:admin_plugin_contests'])->group(function ()
            {
                Route::get('/{id}/users', [ContestsController::class, 'users']);
                Route::put( '/{id}/users', [ContestsController::class, 'usersUpdate']);
                Route::resource('contests-conditions', ContestsConditionController::class);
                Route::resource('contests-faq', ContestsFaqController::class);
            });

            Route::resource('contests', ContestsController::class)->middleware(['permission:admin_plugin_contests']);

        });

        //Инструменты
        Route::group(['prefix' => 'tools'], function ()
        {

            Route::middleware(['permission:admin_contact'])->group(function () {
                Route::resource('social-reviews', SocialReviewsController::class);
            });

            Route::resource('blacklist', BlackListController::class)->middleware(['permission:order_blacklist']);

            // Черный список BestChange
            Route::get('blacklist_bestchange', [BlackListBestChangeController::class, 'index'])->middleware(['permission:order_blacklist']);
            Route::post('blacklist_bestchange', [BlackListBestChangeController::class, 'store'])->middleware(['permission:order_blacklist']);

            Route::resource('export-data', ExportDataController::class)->middleware(['permission:admin_export_data']);

            Route::resource('blockchain-explorer', PaymentExplorerController::class)->middleware(['permission:admin_other_blockchain_explorer']);

            Route::get('/geoip/countries', [GEOIPController::class, 'countries'])->middleware(['permission:admin_geoip']);
            Route::put('/geoip/countries/{id}', [GEOIPController::class, 'countriesPut'])->middleware(['permission:admin_geoip']);

            Route::resource('reviews', ReviewsController::class)->middleware('permission:admin_reviews');


            //Уведомлении
            Route::prefix('notification')->middleware(['permission:admin_notification'])->controller(NotificationController::class)
                ->group(function () {
                    Route::get('/{id}/destroy', 'destroy');
                    Route::get('/{id}/on', 'on');
                    Route::get('/{id}/off', 'off');
                });
            Route::resource('notification', NotificationController::class)->middleware(['permission:admin_notification']);

            // Ссылки на отзывы
            Route::middleware(['permission:admin_links_reviews'])->group(function () {
                Route::resource('links_reviews', LinksReviewsController::class);
                Route::resource('links_reviews_group', LinksReviewsGroupController::class);
            });

            // Ссылки на Footer
            Route::middleware(['permission:admin_links_footer'])->group(function () {
                Route::resource('links_footers', LinksFootersController::class);
                Route::resource('links_footers_group', LinkFooterGroupController::class);
            });

            // Система авторизаций
            Route::get('/auth-system/{id}/destroy', [AuthSystemController::class, 'destroy'])->middleware(['permission:admin_social_auth']);
            Route::resource('auth-system', AuthSystemController::class)->middleware(['permission:admin_social_auth']);

            Route::resource('checkbox-agreements', CheckboxAgreementController::class);

            Route::get('job-schedule/{id}/destroy', [JobScheduleController::class, 'destroy'])->middleware(['permission:admin_status_job']);
            Route::resource('job-schedule', JobScheduleController::class)->middleware(['permission:admin_status_job']);
            Route::get('/job-settings', [JobController::class, 'index'])->middleware(['permission:admin_status_job']);
            Route::put('/job-settings', [JobController::class, 'update'])->middleware(['permission:admin_status_job']);


            // Новый модуль проверки BIN — BinInspector
            Route::resource('bin-inspector', BinInspectorController::class)
                ->middleware(['permission:admin_plugin_card_info']);

            // Управление разрешёнными/запрещёнными банками для валют
            Route::get('bin-inspector/currency/{currency}/banks',
                [BinBankRulesController::class, 'show']
            )->middleware(['permission:admin_plugin_card_info']);

            Route::put('bin-inspector/currency/{currency}/banks',
                [BinBankRulesController::class, 'store']
            )->middleware(['permission:admin_plugin_card_info']);
        });

        Route::middleware('permission:admin_api')->group(function () {
            Route::get('/api-users/logs', [APIController::class, 'logs']);
            Route::resource('api-users', APIController::class);
        });

        // VUE Запросы
        Route::prefix('vue')->group(function () {


            Route::resource('notification-events', NotificationEventController::class);

            Route::get('/layoutOptions', [LayoutVueController::class, 'options']);
            Route::get('/layoutNotification', [LayoutVueController::class, 'getNotifications']);
            Route::put('/layoutNotification', [LayoutVueController::class, 'updateNotifications']);
            Route::put('/layoutNotificationById/{id}', [LayoutVueController::class, 'updateNotificationById']);

            Route::post('/layoutStatusOperator', [LayoutVueController::class, 'postStatusOperator']);
            Route::post('/layoutSettings', [LayoutVueController::class, 'postLayoutSettings']);

            // Направление обмена
            Route::get('/validateDirectionExchange', [DirectionExchangeVueController::class, 'checkValidate']);


            Route::post('/updateSettingInterface', [SettingsVueController::class, 'update']);


            Route::get('/getNotify', [NotificationVueController::class, 'getNotify']);

            Route::controller(OrderVueController::class)->group(function () {
                //
                Route::get('/getLiveOrderSettings', 'getLiveOrderSettings');

                Route::post('/orderHandler/{id}', 'handlerOrder');
                Route::get('/liveOrders', 'liveOrders');
                Route::post('/liveOrderSettings', 'liveOrderSettings');
                Route::post('/checkOrderPayment/{id}', 'checkOrderPayment');
            });

            // Онлайн чат в заявках
            Route::prefix('order-chat')
                ->middleware(['permission:admin_other_online_chat'])
                ->group(function () {
                    Route::get('/list', [\iEXPackages\OrderChat\Http\Controllers\Admin\OrderChatVueController::class, 'listChats']);
                    Route::post('/create', [\iEXPackages\OrderChat\Http\Controllers\Admin\OrderChatVueController::class, 'addMessage']);
                });

            // Чат в заявках (Модальная форма)
            Route::prefix('order-chats')
                ->middleware(['permission:admin_other_online_chat'])
                ->group(function () {
                    Route::get('/orders', [\iEXPackages\OrderChat\Http\Controllers\Admin\OrderChatsVueController::class, 'getOrders']);
                    Route::get('/getMessagesByChat/{id}', [\iEXPackages\OrderChat\Http\Controllers\Admin\OrderChatsVueController::class, 'getMessagesByChat']);
                    Route::put('/allReadById/{id}', [\iEXPackages\OrderChat\Http\Controllers\Admin\OrderChatsVueController::class, 'allReadById']);
                    Route::get('/allReadChats', [\iEXPackages\OrderChat\Http\Controllers\Admin\OrderChatsVueController::class, 'allReadChats']);
                });



            // Сортировка данных
            Route::post('/sortingDefault', [SortingVueController::class, 'sortingDefault']);

            Route::post('/filterColumns', [SortingVueController::class, 'filterColumns']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| KYC Operations Center — Blade UI (before SPA catch-all)
| Permission aligns with existing identity verification admins.
|--------------------------------------------------------------------------
*/
Route::middleware(['2fa', 'is_reading_mode', 'admin.auth', 'admin', 'permission:admin_verification_account'])
    ->prefix('kyc-ops')
    ->group(function () {
        Route::get('/', [KycOpsController::class, 'index'])->name('admin.kyc-ops.index');
        Route::get('/attention', [KycOpsController::class, 'attention'])->name('admin.kyc-ops.attention');
        Route::get('/diagnostics', [KycOpsController::class, 'diagnostics'])->name('admin.kyc-ops.diagnostics');
        Route::get('/dashboard.json', [KycOpsController::class, 'dashboardJson'])->name('admin.kyc-ops.dashboard-json');
        Route::get('/cases/{caseKey}', [KycOpsController::class, 'show'])
            ->where('caseKey', 'didit:\\d+|sumsub:\\d+|manual:\\d+')
            ->name('admin.kyc-ops.show');
        Route::post('/cases/{caseKey}/reconcile', [KycOpsController::class, 'reconcile'])
            ->where('caseKey', 'didit:\\d+|sumsub:\\d+|manual:\\d+')
            ->name('admin.kyc-ops.reconcile');
        Route::post('/cases/{caseKey}/open-session', [KycOpsController::class, 'openSession'])
            ->where('caseKey', 'didit:\\d+|sumsub:\\d+|manual:\\d+')
            ->name('admin.kyc-ops.open-session');
        Route::get('/cases/{caseKey}/export', [KycOpsController::class, 'exportAudit'])
            ->where('caseKey', 'didit:\\d+|sumsub:\\d+|manual:\\d+')
            ->name('admin.kyc-ops.export');
    });

/*
|--------------------------------------------------------------------------
| Support chat (site widget) — read-mostly admin UI + close/reopen (Blade)
| Registered before SPA catch-all. Permission aligns with order online chat operators.
|--------------------------------------------------------------------------
*/
Route::middleware(['2fa', 'is_reading_mode', 'admin.auth', 'admin', 'permission:admin_other_online_chat'])
    ->group(function () {
        Route::get('/support-conversations', [SupportConversationController::class, 'index'])
            ->name('admin.support-conversations.index');
        Route::get('/support-conversations/{conversation:uuid}', [SupportConversationController::class, 'show'])
            ->name('admin.support-conversations.show');
        Route::post('/support-conversations/{conversation:uuid}/close', [SupportConversationController::class, 'close'])
            ->name('admin.support-conversations.close');
        Route::post('/support-conversations/{conversation:uuid}/reopen', [SupportConversationController::class, 'reopen'])
            ->name('admin.support-conversations.reopen');

        Route::get('/support-chat/diagnostics', [SupportChatDiagnosticsController::class, 'index'])
            ->name('admin.support-chat.diagnostics');
        Route::get('/support-chat/health', [SupportChatDiagnosticsController::class, 'health'])
            ->name('admin.support-chat.health.blade');
        Route::post('/support-chat/diagnostics/retry-message/{message}', [SupportChatDiagnosticsController::class, 'retryMessage'])
            ->name('admin.support-chat.retry-message');
        Route::post('/support-chat/diagnostics/recreate-topic/{conversation:uuid}', [SupportChatDiagnosticsController::class, 'recreateTopic'])
            ->name('admin.support-chat.recreate-topic');
        Route::post('/support-chat/diagnostics/retry-attachment/{attachment}', [SupportChatDiagnosticsController::class, 'retryAttachment'])
            ->name('admin.support-chat.retry-attachment');
    });

Route::get('/{vue_capture?}', [HomeController::class, 'init'])
    ->where('vue_capture', '[\/\w\.-]*');
