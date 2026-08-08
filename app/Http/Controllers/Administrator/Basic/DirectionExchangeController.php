<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Basic\DirectionExchangeResources;
use App\Http\Resources\Admin\Basic\DirectionExchangeTrashedResources;
use App\Models\BestChangeDirection;
use App\Models\CitiesModel;
use App\Models\CodeCurrency;
use App\Models\CompetitorLink;
use App\Models\Currency;
use App\Models\DirectionDay;
use App\Models\DirectionExchange;
use App\Models\DirectionExchangeCity;
use App\Models\DirectionCityProfile;
use App\Models\DirectionExchangeCityProfilePivot;
use App\Models\DirectionExchangeGroup;
use App\Models\DirectionExchangeMode;
use App\Models\DirectionExchangePercentAmount;
use App\Models\DirectionExchangeSelectorFee;
use App\Models\DirectionField;
use App\Models\DirectionNotification;
use App\Models\DirectionRequisite;
use App\Models\SettingsLimitProfile;
use App\Models\FileParserGroup;
use App\Models\GatewayMerchant;
use App\Models\GatewayPayment;
use App\Models\GeoCountryList;
use App\Models\GroupCommission;
use App\Models\GroupParserExchange;
use App\Models\ParserExchange;
use App\Models\ParserFormulaRates;
use App\Models\TaskStatus;
use App\Services\Rates\ExchangeRatesCacheInvalidator;
use App\Settings\DirectionConfig;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class DirectionExchangeController extends Controller
{
    /**
     * Дополнительные фильтры
     *
     * @var array
     */
    protected array $allowFiltered = [
        'generate_min_price',
        'generate_max_price',
        'is_enabled_uncreated_directions'
    ];

    /**
     * Фильтры настройки страницы
     *
     * @var array
    */
    protected array $allowFilteredPage = [
        'num_direction_paginate',
        'admin_directions_hidden_columns',
    ];

    /**
     * Дополнительные фильтры
     */
    protected array $allowFilteredPrice = [
        'price_adjustment_pagination'
    ];


    protected array $optionsFields = [

        'default' => [
            'title_name' => [],
        ],

        'main' => [
            'id_currency1' => ['validator' => ['required']],
            'id_currency2' => ['validator' => ['required']],
            'tech_name' => ['validator' => ['required']],
            'status' => [],

            'min_price1' => ['default' => 0],
            'min_price2' => ['default' => 0],
            'max_price1' => ['default' => 0],
            'max_price2' => ['default' => 0],
            'is_manual_min_price1' => ['type' => 'int','default' => 0],
            'is_manual_min_price2' => ['type' => 'int','default' => 0],
            'is_manual_max_price1' => ['type' => 'int','default' => 0],
            'is_manual_max_price2' => ['type' => 'int','default' => 0],
        ],

        'information' => [
            'deadline' => ['locales' => true, 'validate_empty_html' => true],
            'instructions' => ['locales' => true, 'validate_empty_html' => true],
            'desc_exchange' => ['locales' => true, 'validate_empty_html' => true],
            'desc_exchange_dop' => ['locales' => true, 'validate_empty_html' => true],
            'formalization_text' => ['locales' => true, 'validate_empty_html' => true],
            'other_docs' => ['locales' => true, 'validate_empty_html' => true],
            'notice_process_desc' => ['locales' => true, 'validate_empty_html' => true],
            'text_order_success' => ['locales' => true, 'validate_empty_html' => true],
            'text_order_failed' => ['locales' => true, 'validate_empty_html' => true],
            'text_order_confirm' => ['locales' => true, 'validate_empty_html' => true],
            'order_button_i_pay' => ['locales' => true, 'validate_empty_html' => true],
            'order_button_i_pay_text' => ['locales' => true, 'validate_empty_html' => true],
            'order_button_i_confirm' => ['locales' => true, 'validate_empty_html' => true],
            'text_order_created_email' => ['locales' => true, 'validate_empty_html' => true],
            'interval_confirm_order' => ['type' => 'int','default' => 0],
            'desc_source_mode' => ['type' => 'string','default' => 'auto'],
            'instruction_source_mode' => ['type' => 'string','default' => 'auto'],
            'seo_title' => ['locales' => true, 'validate_empty_html' => true],
            'seo_description' => ['locales' => true, 'validate_empty_html' => true],
            'seo_keywords' => ['locales' => true, 'validate_empty_html' => true],
        ],

        'fields' => [
            'ids_custom_fields' => ['relationship' => 'direction_field', 'pluck' => 'id', 'sync' => true],
        ],


        'cities' => [
            'ids_cities' => ['relationship' => 'direction_exchange_cities', 'pluck' => 'city_id', 'sync' => true],
        ],

        'courses' => [
            'id_crypto_parser' => [],
            'add_course1' => ['default' => 0],
            'add_course1_s' => ['default' => 0],
            'manual_rate_value' => [],
            'id_parser_formula_rate' => [],
            'id_file_parser_rate' => [],
            'id_competitor' => [],
            'cr_min_sum' => [],
            'cr_max_sum' => [],
            'cr_id_new_rate' => [],
            'cr_add_course' => []
        ],

        'multiplicity' => [
            'multiplicity_type' => ['default' => 0],
            'multiplicity_amount' => ['default' => 0],
            'multiplicity_comment' => ['locales' => true, 'validate_empty_html' => true],
        ],

        'restrictions' => [
            'rl_min2_course' => ['default' => 0],
            'rl_max2_course' => ['default' => 0],
            'rl_id_parser_exchange' => ['default' => 0],
            'rl_add_course' => ['default' => 0],
        ],

        'fees' => [
            'type_profit_field' => ['default' => 0],
            'profit' => ['default' => 0],
            'profit_s' => ['default' => 0],
            'ids_group_commissions' => ['relationship' => 'groupCommissions', 'pluck' => 'id', 'sync' => true],
        ],

        'fees-oth' => [
            'oth_comm_percent' => ['default' => 0],
            'oth_comm_currency' => ['default' => 0],
            'oth_comm2_percent' => ['default' => 0],
            'oth_comm2_currency' => ['default' => 0],
            'oth_min_comm' => ['default' => 0],
            'oth_min2_comm' => ['default' => 0],
        ],

        'fees-pay' => [
            'pay_comm_percent' => ['default' => 0],
            'pay_comm_currency' => ['default' => 0],
            'pay_comm2_percent' => ['default' => 0],
            'pay_comm2_currency' => ['default' => 0],
            'pay_min_comm' => ['default' => 0],
            'pay_min2_comm' => ['default' => 0],
        ],

        'exchange-amount' => [
            'is_notify_exchange_amount' => ['default' => '0', 'type' => 'string']
        ],

        'selector-fee' => [
            'title_selector_fee' => ['locales' => true, 'validate_empty_html' => true],
            'text_selector_fee' => ['locales' => true, 'validate_empty_html' => true],
        ],

        'automatic-merchant' => [
            'ids_merchant' => ['relationship' => 'merchants', 'pluck' => 'id', 'sync' => true],
            'network_code' => ['default' => ''],
            'merchant_day_limit_amount' => ['default' => 0],
            'merchant_month_limit_amount' => ['default' => 0],
            'merchant_min_amount_for_order' => ['default' => 0],
            'merchant_max_amount_for_order' => ['default' => 0],
            'merchant_day_limit' => ['default' => 0],
            'merchant_month_limit' => ['default' => 0]
        ],

        'automatic-pays' => [
            'ids_pay' => ['relationship' => 'gateway_payments', 'pluck' => 'id', 'sync' => true],
            'network_code_out' => ['default' => ''],
            'pay_day_limit_amount' => ['default' => 0],
            'pay_month_limit_amount' => ['default' => 0],
            'pay_min_amount_for_order' => ['default' => 0],
            'pay_max_amount_for_order' => ['default' => 0],
            'pay_day_limit' => ['default' => 0],
            'pay_month_limit' => ['default' => 0],
            'allow_autopay' => ['default' => 0, 'type' => 'int']
        ],

        'reserves' => [
            'type_reserve' => ['default' => 0],
            'direction_reserve' => ['default' => 0]
        ],

        'requisites' => [
            'type_output_requisites' => ['default' => 0],
            'ids_requisites' => ['relationship' => 'direction_requisites', 'pluck' => 'id', 'sync' => true],
            'method_request_payment' => ['type' => 'int', 'default' => 0],
            'request_payment_text' => ['locales' => true, 'validate_empty_html' => true]
        ],


        'unpaid-orders' => [
            'auto_del_order_status' => ['default' => []],
            'auto_del_order_day' => ['default' => 0],
            'auto_del_order_hour' => ['default' => 0],
            'auto_del_order_minute' => ['default' => 0]
        ],

        'partner' => [
            'profit_partner' => ['default' => 0],
            'profit_partner_s' => ['default' => 0],
            'is_not_partner'  => ['default' => '0', 'type' => 'string'],
            'fixed_payout' => ['default' => 0],
            'minimum_payout' => ['default' => 0],
            'maximum_payout' => ['default' => 0],
            'individual_percentage' => ['default' => 0],
            'max_percent_partner' => ['default' => 0],
        ],

        'export' => [
            'allow_export' => ['default' => 0],
            'allow_export_from'  => ['default' => ''],
            'allow_export_to'  => ['default' => ''],
            'hidden_export_label_param' => ['default' => 0],
            'label_floating' => ['default' => ''],
            'label_floating_percent' => ['default' => 0],
            'label_delay' => ['default' => 0],
            'export_label_param' => ['default' => '', 'split' => ',', 'is_clear_empty' => true],
        ],

        'restriction-checks' => [
            'reserve_max_limit' => ['default' => 0],
            'reserve_limit_day' => ['default' => 0],
            'reserve_limit_month' => ['default' => 0],
            'device' => ['default' => '', 'split' => ',', 'is_clear_empty' => true],
            'is_hidden_not_device' => ['default' => 0],
            'languages' => ['default' => '', 'split' => ',', 'is_clear_empty' => true],
            'is_hidden_not_locale' => ['default' => 0],
            'is_disable_auto_reg' => ['default' => '0', 'type' => 'string'],
            'ids_geo_forbidden_countries' => ['default' => [], 'relationship' => 'direction_forbidden_countries', 'pluck' => 'id', 'sync' => true],
            'ids_geo_allowed_countries' => ['default' => [], 'relationship' => 'direction_allowed_countries', 'pluck' => 'id', 'sync' => true],
            'is_num_transaction' => ['default' => 0, 'type' => 'string'],
            'num_transaction_label' => ['default' => ''],
            'is_note_tx' => ['default' => 0, 'type' => 'string'],
            'note_tx_label' => ['default' => ''],
            'is_holding_direction' => ['default' => 0, 'type' => 'string'],
            'is_enabled_exchange' => ['default' => 0, 'type' => 'string'],
            'from_on_time' => ['default' => ''],
            'to_on_time' => ['default' => ''],
            'is_verified_account' => ['default' => 0, 'type' => 'string'],
            'not_ip' => ['default' => ''],
            'max_order_one_ip' => ['default' => 0],
            'max_order_one_ip_day' => ['default' => 0],
            'max_order_one_user' => ['default' => 0],
            'max_order_one_user_day' => ['default' => 0],
            'max_order_one_email' => ['default' => 0],
            'max_order_one_email_day' => ['default' => 0],
            'max_order_one_account1' => ['default' => 0],
            'max_order_one_account1_day' => ['default' => 0],
            'max_order_one_account2' => ['default' => 0],
            'max_order_one_account2_day' => ['default' => 0],
            'min_count_exchanges_client' => ['default' => 0],
            'max_amount_newbie' => ['default' => 0],
            'is_unique_amount_from' => ['default' => 0, 'type' => 'string'],
            'is_hidden_order_pay' => ['default' => 0, 'type' => 'string'],
            'is_hidden_tariffs' => ['default' => 0, 'type' => 'string'],
            'is_enable_user_discount' => ['default' => 0, 'type' => 'string'],
            'limit_profile_id' => ['default' => 0, 'type' => 'int'],
        ]
    ];

    /**
     * Список направлений
     *
     * @throws \Exception
     */
    public function index(
        Request $request,
        DirectionConfig $settings
    )
    {
        if($request->has('showPage')) {
            if($request->get('showPage') == 'settings')
            {
                $statuses = TaskStatus::where('allow_delete', '=', ' 1')->get()->pluck('name', 'id');

                return response()->json([
                    'config' => [
                        'unpaid_auto_delete'      => (int) $settings->isUnpaidAutoDeleteEnabled(),
                        'unpaid_order_status'     => $settings->unpaidOrderStatuses(),
                        'unpaid_time_day'         => $settings->unpaidTimeDay(),
                        'unpaid_time_hour'        => $settings->unpaidTimeHour(),
                        'unpaid_time_minute'      => $settings->unpaidTimeMinute(),
                        'generate_min_price'      => (int) $settings->generateMinPrice(),
                        'generate_max_price'      => (int) $settings->generateMaxPrice(),
                        'profit_calculation_type' => (int) $settings->profitCalculationType(),
                    ],
                    'statusesOrder' => $statuses->map(function ($value, $id) {
                        return [
                            'id' => $id,
                            'value' => $value
                        ];
                    })->values()
                ]);
            }
        }

        // Проверка на существование валюты
        if(isset($request->is_unique) and $request->is_unique == 1) {
            $id_currency1 = (int)$request->get('id_currency1');
            $id_currency2 = (int)$request->get('id_currency2');

            $isUnique = DirectionExchange::active()->where([
                ['id_currency1', '=', $id_currency1],
                ['id_currency2', '=', $id_currency2]
            ])->select('status', 'id_currency1', 'id_currency2', 'id', 'tech_name')->first();

            return response()->json([
                'is_exists' => isset($isUnique) and isset($isUnique->id) ? 1 : 0,
                'id' => isset($isUnique->id) ? $isUnique->id : 0,
                'name' => $isUnique->tech_name ?? ''
            ]);
        }

        // Возвращаем список уже существующих направлений по выбранной валюте
        // existing_pairs=1&side=from|to&id_currency=NN
        if ($request->has('existing_pairs') && (int)$request->get('existing_pairs') === 1) {
            $side = $request->get('side');
            $currencyId = (int) $request->get('id_currency');

            if (!in_array($side, ['from', 'to'], true) || $currencyId <= 0) {
                return response()->json([
                    'status'  => 1,
                    'message' => 'Неверные параметры: side должен быть from|to, id_currency > 0',
                ], 422);
            }

            if ($side === 'from') {
                // Выбрана валюта «Отдаю»: вернём id валют «Получаю», с которыми пара уже существует
                $pairs = DirectionExchange::query()
                    ->active()
                    ->select(['id', 'id_currency1', 'id_currency2'])
                    ->where('id_currency1', $currencyId)
                    ->orderBy('id', 'desc')
                    ->get();

                return response()->json([
                    'status'        => 0,
                    'side'          => 'from',
                    'currency_id'   => $currencyId,
                    'ids'           => $pairs->pluck('id_currency2')->unique()->values(),
                    'pairs'         => $pairs->map(fn($p) => [
                        'id'           => (int) $p->id,
                        'id_currency1' => (int) $p->id_currency1,
                        'id_currency2' => (int) $p->id_currency2,
                    ]),
                ]);
            }

            // side === 'to'
            $pairs = DirectionExchange::query()
                ->active()
                ->select(['id', 'id_currency1', 'id_currency2'])
                ->where('id_currency2', $currencyId)
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'status'        => 0,
                'side'          => 'to',
                'currency_id'   => $currencyId,
                'ids'           => $pairs->pluck('id_currency1')->unique()->values(),
                'pairs'         => $pairs->map(fn($p) => [
                    'id'           => (int) $p->id,
                    'id_currency1' => (int) $p->id_currency1,
                    'id_currency2' => (int) $p->id_currency2,
                ]),
            ]);
        }

        if($request->has('isLoadingData'))
        {
            $currencies = Currency::select('id', 'tech_name', 'status', 'id_payment', 'designation_xml', 'id_code_currency')
                ->with([
                    'payment' => function($q) { $q->select('id', 'name', 'logo'); },
                    'code_currency' => function($q) { $q->select('id', 'name'); },
                ])
                ->lazy()
                ->map(function($item) {
                    $status_name = __('включена');
                    if ($item->status == 1) {
                        $status_name = __('отключена');
                    } elseif ($item->status == 2) {
                        $status_name = __('в архиве');
                    }

                    // Build a smart label: Payment · Code [designation] (no duplicates)
                    $paymentName = trim((string)($item->payment->name ?? ''));
                    $codeName    = trim((string)($item->code_currency->name ?? ''));
                    $designation = trim((string)($item->designation_xml ?? ''));
                    $tech        = trim((string)$item->tech_name);

                    $normalize = static function ($s) {
                        $s = (string)$s;
                        $s = mb_strtolower($s);
                        return preg_replace('/[^a-z0-9]+/u', '', $s) ?? '';
                    };

                    $parts = [];
                    if ($paymentName !== '') $parts[] = $paymentName;
                    if ($codeName !== '' && $normalize($codeName) !== $normalize($paymentName)) $parts[] = $codeName;

                    // include designation only if it adds new signal
                    if ($designation !== '') {
                        $joinedNorm = $normalize(implode(' ', $parts));
                        if (!str_contains($joinedNorm, $normalize($designation))) {
                            $parts[] = '['.$designation.']';
                        }
                    }

                    // ensure unique parts (e.g., if payment == code)
                    $seen = [];
                    $labelParts = [];
                    foreach ($parts as $p) {
                        $key = $normalize($p);
                        if ($key === '' || isset($seen[$key])) continue;
                        $seen[$key] = true;
                        $labelParts[] = $p;
                    }

                    $smartLabel = trim(implode(' · ', $labelParts));
                    if ($smartLabel === '') {
                        $smartLabel = $tech; // fallback
                    }

                    return [
                        'id'              => $item->id,
                        'value'           => $item->tech_name, // короткое имя для select
                        'label'           => $smartLabel, // расширенная подпись без дублей
                        'status'          => (int) $item->status,
                        'status_name'     => $status_name,
                        'designation_xml' => (string) ($item->designation_xml ?? ''),
                        'code'            => $item->code_currency->name ?? null,
                        'payment_id'      => $item->id_payment,
                        'payment_name'    => $item->payment->name ?? null,
                        'image'           => '/storage/payment_systems/' . ($item->payment?->logo),
                        'is_active'       => (int) $item->status === 0,
                    ];
                })
                ->values();

            $groups = DirectionExchangeGroup::orderBy('sorting')->get()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'value' =>  $item->name
                ];
            })->values();


            return response()->json([
                'currencies' => $currencies,
                'groups' => $groups
            ]);
        }

        // Фильтры
        $exchanges = DirectionExchange::select(
            'id',
            'id_currency1', 'id_currency2', 'tech_name',
            'status', 'is_main', 'is_error_rate', 'error_rate_text', 'exchange_rate',
            'parser_source_name', 'oth_comm_percent',
            'oth_comm_currency', 'oth_comm2_percent', 'oth_comm2_currency', 'profit', 'profit_s', 'min_price1',
            'min_price2', 'max_price1', 'max_price2', 'is_manual_min_price1', 'is_manual_min_price2', 'is_manual_max_price1',
            'is_manual_max_price2', 'updated_at',  'created_at'
        )
            ->with([
            'currency1' => function($q) {
                $q->select('id', 'id_code_currency', 'id_payment', 'tech_name', 'status');
            },
            'currency2' => function ($q) {
                $q->select('id', 'id_code_currency', 'id_payment', 'tech_name', 'status');
            },
            'currency1.payment' => function($q) {
                $q->select('id', 'name', 'logo');
            },
            'currency2.payment' => function($q) {
                $q->select('id', 'name', 'logo');
            },
            'merchants',
            'gateway_payments',
        ])->withCount('tasks')->filter($request->all());

        if(!$request->has('sorting_order')) {
            $exchanges = $exchanges->orderBy('id', 'desc');
        }

        if (! $request->has('status')) {
            $exchanges = $exchanges->active();
        }

        $admin_hidden_columns = explode(',', iEXSetting('admin_directions_hidden_columns'));
        $allowedColumns = ['direction', 'exchangeRate', 'otherFeeIn', 'otherFeeOut', 'profit', 'merchant', 'pay', 'status', 'minAmount', 'maxAmount'];


        return response()->json([
            'items' => new DirectionExchangeResources(
                $exchanges->paginate((int) iEXSetting('num_direction_paginate', 20))
            ),
            'selected_columns' => collect($admin_hidden_columns)->map(function ($item) {
                return $item;
            })->reject(fn($item) => !in_array($item, $allowedColumns))->values(),
            'per_page' => (int)iEXSetting('num_direction_paginate', 20),
        ]);
    }

    /**
     * Обработка и добавление направлений
     *
     * @throws \Exception
     */
    public function store(
        Request $request,
        DirectionConfig $settings
    )
    {
        // Если включена возможность обновления данных
        if($request->is_update == 1 and $request->has('showPage'))
        {
            // Обновление колонок
            if ((string)$request->showPage === 'settings') {
                $allowFilteredPage = [
                    'unpaid_auto_delete',
                    'unpaid_order_status',
                    'unpaid_time_day',
                    'unpaid_time_minute',
                    'generate_min_price',
                    'generate_max_price',
                    'profit_calculation_type'
                ];


                $update = [];
                foreach ($allowFilteredPage as $item) {
                    $update[$item] = $request->has($item) ? $request->get($item) : null;
                }

                $settings->update($update);
            }

            return response()->json([
                'status' => 0,
                'message' => __('Настройки успешно сохранены')
            ]);
        }


        $validator = Validator::make($request->all(), [
            'id_currency1' => ['required', 'exists:currencies,id'],
            'id_currency2' => ['required', 'exists:currencies,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'error' => $validator->errors()->first()
            ]);
        }

        if ($request->get('id_currency1') == $request->get('id_currency2'))
        {
            return response()->json([
                'status' => 1,
                'errors' => __('Ошибка при создании направления, выберите другую пару')
            ]);
        }

        $find = DirectionExchange::active()->where([
            ['id_currency1', '=', $request->get('id_currency1')],
            ['id_currency2', '=', $request->get('id_currency2')],
        ]);

        if ($find->exists()) {
            return response()->json([
                'status' => 1,
                'error' => __('Эта пара уже существует')
            ]);
        }

        $response = DirectionExchange::create([
            'id_currency1' => $request->get('id_currency1'),
            'id_currency2' => $request->get('id_currency2')
        ]);

        $name = direction_name($response);
        $response->update([
            'tech_name' => $request->get('tech_name') ?? $name,
        ]);

        return response()->json([
            'status' => 0,
            'message' => __(':name успешно добавлен', ['name' => $name]),
            'id' => $response->id,
            'name' => $name
        ]);
    }

    /**
     * Форма редактирования направлений
     */
    public function edit(int $id, Request $request): JsonResponse
    {
        // Получаем название страницы и в зависимости от страницы получаем данные
        $pageName = $request->has('pageName') ? $request->get('pageName') : 'main';

        if($pageName == 'sidebar-page')
        {
            $direction = DirectionExchange::select('tech_name', 'id')->where('id', $id)->first();
            return response()->json([
                'header_title' => $direction->tech_name
            ]);
        }

        // Детали направления
        $item = DirectionExchange::with([
            'direction_exchange_cities',
            'groupCommissions',
            'direction_modes',
            'direction_exchange_cities.city',
            'direction_exchange_cities.profile',
            'direction_forbidden_countries',
            'direction_allowed_countries',
        ])->findOrFail($id);


        // Данные главной страницы
        $responseData = [];

        if($pageName == 'main')
        {
            $currencies = Currency::select('id', 'status', 'id_payment', 'tech_name')
                ->with(['payment' => function ($q) {
                    $q->select('id', 'name', 'logo');
                }, 'code_currency' => function ($q) {
                    $q->select('id', 'name');
                }])
                ->active()->get()->sortBy('id_payment', SORT_ASC, true)
                ->reverse()->map(function($item) {
                    return [
                        'id' => $item->id,
                        'value' => $item->tech_name,
                        'image' => '/storage/payment_systems/' . ($item->payment?->logo ?? ''),
                    ];
                })->values();


            $responseData['options'] = [
                'currencies' => $currencies,
                'is_main' => $item->is_main
            ];
        } elseif($pageName == 'fields') {

            $custom_fields = DirectionField::whereStatus(0)->orderBy('sorting')->pluck('name', 'id')
                ->map(function ($value, $id) {
                    return [
                        'id' => $id,
                        'value' => $value
                    ];
                })->values();

            $responseData['options'] = [
                'fields' => $custom_fields,
            ];
        } elseif($pageName == 'cities')
        {
            $cities = CitiesModel::query()
                ->where('status', 1)
                ->with(['country:id,value']) // Загружаем страны одним запросом
                ->orderBy('country_id')
                ->orderBy('name')
                ->get()
                ->map(function ($item) {
                    $countryName = $item->country?->value ?? 'Без страны';
                    $cityName = $item->name;
                    $designation = $item->designation_xml ? '[' . $item->designation_xml . '] ' : '';

                    return [
                        'id' => $item->id,
                        'country_id' => $item->country?->id,
                        'country_name' => $countryName,
                        'city_name' => $cityName,
                        'designation_xml' => $item->designation_xml,
                        'label' => "{$designation}{$countryName} ({$cityName})",
                    ];
                })
                ->values();

            $profiles = DirectionCityProfile::query()
                ->where('status', true)
                ->orderByDesc('id')
                ->get()
                ->map(function ($profile) {
                    return [
                        'id'        => (int) $profile->id,
                        'value'     => $profile->name,
                        'profit'    => $profile->profit,
                        'profit_s'  => $profile->profit_s,
                        'add_comm'  => $profile->add_comm,
                    ];
                })
                ->values();

            $responseData['options'] = [
                'cities' => $cities,
                'profiles' => $profiles,
                'selected_cities' => $item->direction_exchange_cities->map(function($value) {
                    return [
                        'id' => $value->id,
                        'attributes' => [
                            'country_name' => isset($value->city, $value->city->country) ? $value->city->country?->value : '',
                            'city_id' => $value->city?->id,
                            'city_name' => $value->city?->name,
                            'add_comm' => $value->add_comm,
                            'param' => $value->param,
                            'min_price' => $value->min_price,
                            'max_price' => $value->max_price,
                            'bid_value' => $value->bid_value,
                            'exchange_text' => $value->getTranslations('exchange_text'),
                            'instruction' => $value->getTranslations('instruction'),
                            'information' => $value->getTranslations('information'),
                            'profit_partner' => $value->profit_partner,
                            'profit_partner_s' => $value->profit_partner_s,
                            'profit' => $value->profit,
                            'profit_s' => $value->profit_s,
                            'direction_city_profile_id' => $value->profile?->id,
                            'status' => (bool)$value->status,
                        ]
                    ];
                })
            ];
        }
        elseif ($pageName == 'information') {}

        elseif ($pageName == 'courses')
        {
            $rates_formula = ParserFormulaRates::query()
                ->select('id', 'summa', 'title')
                ->where('status', 1)
                ->lazy()
                ->map(fn($item) => [
                    'id' => $item->id,
                    'value' => sprintf('%s [1 → %s]', $item->title, $item->summa),
                ])
                ->values()
                ->all();

            $group_rates = GroupParserExchange::query()
                ->select('id', 'name')
                ->where('status', 1)
                ->with(['parser_exchange_enabled:id,name,summa,id_group,status,type_price'])
                ->lazy()
                ->map(function ($item) {
                    $rates = $item->parser_exchange_enabled->map(function ($relation) {
                        $typePrice = $relation->type_price
                            ? " ({$relation->type_price})"
                            : '';

                        return [
                            'id' => $relation->id,
                            'name' => "{$relation->name} [1 → {$relation->summa}]{$typePrice}",
                            'type_price' => $relation->type_price ?: null,
                        ];
                    });

                    return [
                        'id' => $item->id,
                        'name' => "{$item->name} ({$rates->count()})",
                        'rates' => $rates,
                    ];
                })
                ->filter(fn($item) => $item['rates']->isNotEmpty())
                ->values();

            $file_parser_groups = FileParserGroup::query()
                ->select('id', 'name')
                ->with(['rates_enabled' => fn($q) => $q->select('id', 'name', 'summa', 'id_group')->where('status', 1)])
                ->where('status', 1)
                ->lazy()
                ->map(function($item) {
                    $rates = $item->rates_enabled->map(fn($relation) => [
                        'id' => $relation->id,
                        'name' => "{$relation->name} [1 → {$relation->summa}]",
                    ]);

                    return [
                        'id' => $item->id,
                        'name' => "{$item->name} ({$rates->count()})",
                        'rates' => $rates,
                    ];
                })
                ->filter(fn($item) => $item['rates']->isNotEmpty())
                ->values();


            $competitors = CompetitorLink::query()
                ->select(['id', 'name'])
                ->whereHas('rates_enabled', function ($q) {
                    $q->where('status', 1);
                })
                ->withCount([
                    'rates_enabled as rates_count' => function ($q) {
                        $q->where('status', 1);
                    },
                ])
                ->with([
                    'rates_enabled' => function ($q) {
                        $q->select(['id', 'name', 'summa', 'id_competitor'])
                            ->where('status', 1)
                            ->orderBy('id');
                    },
                ])
                ->orderBy('name')
                ->get()
                ->map(function ($item) {
                    $rates = $item->rates_enabled->map(fn($relation) => [
                        'id'   => (int) $relation->id,
                        'name' => sprintf('%s [1 → %s]', $relation->name, $relation->summa),
                    ])->values();

                    return [
                        'id'    => (int) $item->id,
                        'name'  => sprintf('%s (%d)', $item->name, (int) ($item->rates_count ?? $rates->count())),
                        'rates' => $rates,
                    ];
                })
                ->values();

            $responseData['options'] = [
                'groupRates' => $group_rates,
                'parserFormula' => $rates_formula,
                'parserFileGroups' => $file_parser_groups,
                'parserCompetitorGroups' => $competitors,
                'codeOut' => $item->currency2->code_currency->name
            ];
        } elseif ($pageName == 'restrictions')
        {
            // Парсинг из остальных источников
            $group_rates = GroupParserExchange::with(['parser_exchange_enabled' => function($q) {
                $q->select('id', 'name', 'summa', 'id_group', 'status');
            }])->select('id', 'name')
                ->where('status', '=', 1)->get()->map(function($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name. ' ('.$item->parser_exchange_enabled->count().')',
                        'rates' => $item->parser_exchange_enabled->map(function($relation) {
                            return [
                                'id' => $relation->id,
                                'name' => $relation->name. ' [1 → '.$relation->summa.']'
                            ];
                        })
                    ];
                })->reject(function($item) {
                    return count($item['rates']) == 0;
                })->values();

            $responseData['options'] = [
                'groupRates' => $group_rates
            ];
        } elseif ($pageName == 'fees')
        {
            $group_commission = GroupCommission::orderByDesc('id')->get()->map(function($item) {
                return [
                    'id' => $item->id,
                    'value' => $item->name . ' (Комиссия: '. $item->receiving.')'
                ];
            });

            $responseData['options'] = [
                'groupFees' => $group_commission
            ];
        } elseif($pageName == 'exchange-amount')
        {
            $responseData['options'] = [
                'exchange_amounts' => $item->direction_exchange_percentage_amount->map(function($value) {
                    return [
                        'id' => $value->id,
                        'attributes' => [
                            'from_amount' => $value->from_amount,
                            'to_amount' => $value->to_amount,
                            'percentage' => $value->percentage
                        ]
                    ];
                })
            ];
        }  elseif($pageName == 'selector-fee') {
            $responseData['options'] = [
                'selector' => $item->direction_exchange_selector_fees->map(function($value) {
                    return [
                        'id' => $value->id,
                        'attributes' => [
                            'name' => $value->getTranslations('name'),
                            'description' => $value->getTranslations('description'),
                            'fee' => $value->fee
                        ]
                    ];
                })
            ];
        } elseif($pageName == 'automatic-merchant') {
            // Список мерчантов
            $merchants = GatewayMerchant::query()
                ->select('id', 'name', 'alias', 'status')
                ->orderByDesc('status')
                ->orderBy('name')
                ->get()
                ->map(function ($item) {
                    $statusLabel = $item->status == 1 ? '' . __('активна') : '' . __('не активна');
                    $aliasPart = !empty($item->alias) ? ' — ' . $item->alias : '';
                    return [
                        'id' => $item->id,
                        'value' => sprintf('%s%s • (%s)', $item->name, $aliasPart, $statusLabel),
                    ];
                })
                ->values();

            $responseData['options'] = [
                'merchants' => $merchants
            ];
        }elseif($pageName == 'automatic-pays')
        {
            $pays = GatewayPayment::query()
                ->select('id', 'name', 'alias', 'status')
                ->get()->map(function ($item) {
                    $statusLabel = $item->status == 1 ? '' . __('активна') : '' . __('не активна');
                    $aliasPart = !empty($item->alias) ? ' — ' . $item->alias : '';
                    return [
                        'id' => $item->id,
                        'value' => sprintf('%s%s • (%s)', $item->name, $aliasPart, $statusLabel),
                    ];
                })->values();

            $responseData['options'] = [
                'pays' => $pays
            ];
        }elseif($pageName == 'requisites')
        {
            $requisites = DirectionRequisite::whereStatus(1)->pluck('name', 'id')->map(function ($value, $id) {
                    return [
                        'id' => $id,
                        'value' => $value,
                    ];
                })->values();

            $responseData['options'] = [
                'requisites' => $requisites
            ];
        } elseif($pageName == 'unpaid-orders') {

            // Статус заявок
            $statusOrders = TaskStatus::whereIn('id', [1, 2, 6, 10, 11])->pluck('name', 'id')->map(function ($value, $id) {
                return [
                    'id' => $id,
                    'value' => $value
                ];
            })->values();

            $responseData['options'] = [
                'orderStatus' => $statusOrders
            ];
        } elseif($pageName == 'partner')
        {
            // Получаем данные по коду валюты
            if ((int) iEXSetting('id_referral_code_currency') > 0) {
                $partner_code_currency = cache()->remember('referral_code_currency', Carbon::now()->addHour(), function () {
                    return CodeCurrency::find((int) iEXSetting('id_referral_code_currency'))->toArray();
                });
            }

            $responseData['options'] = [
                'partnerCurrency' => $partner_code_currency ?? []
            ];
        } elseif($pageName == 'restriction-checks')
        {
            // Список стран
            $geo_countries = GeoCountryList::pluck('value', 'id');
            $forbidden_countries = collect($geo_countries)
                ->sortByDesc(fn($value, $key) => array_search($key, $item->direction_forbidden_countries->pluck('id', 'id')->toArray()));
            $allowed_countries = collect($geo_countries)
                ->sortByDesc(fn($value, $key) => array_search($key, $item->direction_allowed_countries->pluck('id', 'id')->toArray()));

            $profiles = SettingsLimitProfile::query()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(function (SettingsLimitProfile $profile) {
                    return [
                        'id'          => (int) $profile->id,
                        'value'       => $profile->name,
                        'slug'        => $profile->slug,
                        'description' => $profile->description,
                        'is_default'  => (bool) $profile->is_default,
                    ];
                })
                ->values();

            $responseData['options'] = [
                'forbiddenCountries' => $forbidden_countries->map(function ($value, $key) {
                    return [
                        'id' => $key,
                        'value' => $value
                    ];
                })->values(),
                'allowedCountries' => $allowed_countries->map(function ($value, $key) {
                    return [
                        'id' => $key,
                        'value' => $value
                    ];
                })->values(),
                'limitProfiles' => $profiles,
            ];
        }

        $responseData['attributes'] = [];
        foreach ($this->optionsFields[$pageName] as $key => $value)
        {
            // Разбиваем
            if(isset($value['ext_params'])) {
                $epExplode = explode('ep__', $key);
                $responseData['attributes'][$key] = $item->ext_params[$epExplode[1]] ?? '';
            } else {
                $responseData['attributes'][$key] = $item->{$key};
            }

//            if(isset($value['default'])) {
//                $responseData['attributes'][$key] = empty($item->{$key}) ? $value['default'] : $item->{$key};
//            }


            if(isset($value['relationship']) and !empty($value['pluck'])) {
                $responseData['attributes'][$key] = $item->{$value['relationship']}->pluck($value['pluck']);
            }

            if(isset($value['split'])) {

                $responseData['attributes'][$key] = explode($value['split'], $item->{$key});
            }

            if(isset($value['array_map'])) {
                $responseData['attributes'][$key] = array_map($value['array_map'], $responseData['attributes'][$key]) ?? [];
            }

            if(isset($value['is_zero']) and !$value['is_zero']) {
                $responseData['attributes'][$key] = array_values(array_filter($responseData['attributes'][$key], fn($value) => $value != 0));
            }

            if(isset($value['is_clear_empty']) and $value['is_clear_empty']) {
                $responseData['attributes'][$key] = array_values(array_filter($responseData['attributes'][$key], fn($value) => $value != ''));
            }

            if(isset($value['locales'])) {
                $responseData['attributes'][$key]  = $item->getTranslations($key);
            }

            if(isset($value['type'])) {
                $responseData['attributes'][$key] = variableStrictValue($responseData['attributes'][$key], $value['type']);
            }
        }

        if ($pageName === 'courses') {
            $parser = (string) ($item->parser_source_name ?? '');
            $automatic = $parser !== '' && ! preg_match('/ручн|manual/iu', $parser);
            $responseData['options'] = array_merge($responseData['options'] ?? [], [
                'rateAuthority' => [
                    'source' => $automatic ? 'automatic' : 'manual',
                    'source_label' => $automatic ? 'Автоматический' : 'Ручной',
                    'parser_source_name' => $parser,
                    'automatic_base_rate' => (string) ($item->course_value ?? '0'),
                    'manual_rate_is_authority' => ! $automatic,
                    'percent_tab' => 'others/recount',
                    'help' => $automatic
                        ? 'Источник курса: Автоматический. Рыночный BASE в course_value. Коммерческий % — на вкладке «Пересчёт» (floating_fee/fix_fee). Поле manual_rate_value не является авторитетом цены.'
                        : 'Источник курса: Ручной. manual_rate_value может быть авторитетом.',
                ],
            ]);
            $responseData['attributes']['parser_source_name'] = $parser;
            $responseData['attributes']['automatic_base_rate'] = (string) ($item->course_value ?? '0');
            $responseData['attributes']['rate_source_label'] = $automatic ? 'Автоматический' : 'Ручной';
        }

        return response()->json(array_merge($responseData, [
            'default' => [
                'title_name' => $item->tech_name,
            ]
        ]));
    }

    /**
     * Обработка и обновление направлений
     */
    public function update(int $id, Request $request): JsonResponse
    {
        // Детали направления
        $item = DirectionExchange::findOrFail($id);


        if($request->has('is_update') and $request->is_update == 1)
        {
            if($request->has('is_on_make_main'))
            {
                    \DB::table('direction_exchange')->update(['is_main' => 0]);
                $item->update(['is_main' => 1]);

                return response()->json([
                    'status' => 0,
                    'message' => $item->tech_name .' установлена как главная'
                ]);
            }

            if($request->has('ids_change_merchants')) {
                $item->merchants()->sync($request->ids_change_merchants ?? []);

                return response()->json([
                    'status' => 0,
                    'message' => 'Мерчанты обновлены'
                ]);
            }

            if($request->has('ids_change_pays')) {
                $item->gateway_payments()->sync($request->ids_change_pays ?? []);

                return response()->json([
                    'status' => 0,
                    'message' => 'Выплаты обновлены'
                ]);
            }

            if($request->has('change_status')) {
                $next = (int) $request->get('change_status');
                if ($next === 1) {
                    $course = (float) ($item->course_value ?? 0);
                    $src = trim((string) ($item->parser_source_name ?? ''));
                    if ($course <= 0 && (int) $request->get('confirm_activate_without_course', 0) !== 1) {
                        $bc = BestChangeDirection::query()
                            ->where('id_direction_exchange', (int) $item->id)
                            ->first();
                        $unavailableReason = 'zero_course';
                        if ($bc && ((int) ($bc->is_error_parser ?? 0) === 1 || (float) ($bc->rate_value ?? 0) <= 0)) {
                            $unavailableReason = 'no_valid_bestchange_rate';
                        } elseif ($bc && (int) ($bc->status ?? 0) !== 1) {
                            $unavailableReason = 'bestchange_link_disabled';
                        } elseif ($src === '' || $src === 'None') {
                            $unavailableReason = 'missing_rate_provider';
                        }

                        return response()->json([
                            'status' => 1,
                            'code' => 'ACTIVATE_ZERO_COURSE_BLOCKED',
                            'admin_enabled_intent' => true,
                            'operational_state' => 'unavailable',
                            'status_label' => 'Disabled',
                            'rate_source' => $src !== '' ? $src : null,
                            'course_value' => $item->course_value,
                            'provider_link_enabled' => $bc ? ((int) $bc->status === 1) : null,
                            'raw_provider_rate' => $bc->rate_value ?? null,
                            'unavailable_reason' => $unavailableReason,
                            'message' => 'Cannot activate direction with course <= 0. Repair rate authority first, or pass confirm_activate_without_course=1 only for an approved unavailable contract.',
                        ]);
                    }
                }
                $item->update([
                    'status' => $next
                ]);

                app(ExchangeRatesCacheInvalidator::class)->afterCommit('admin.direction.change_status');

                return response()->json([
                    'status' => 0,
                    'message' => 'Статусы обновлены',
                    'status_label' => $next === 1 ? 'Active' : 'Disabled',
                    'rate_source' => $item->parser_source_name,
                ]);
            }

            if(isset($request->on_fee_options)) {
                $item->update([
                    'oth_comm_percent' => $request->on_fee_options['oth_comm_percent'] ?? 0,
                    'oth_comm_currency' => $request->on_fee_options['oth_comm_currency'] ?? 0,
                    'oth_comm2_percent' => $request->on_fee_options['oth_comm2_percent'] ?? 0,
                    'oth_comm2_currency' => $request->on_fee_options['oth_comm2_currency'] ?? 0,
                    'profit' => $request->on_fee_options['profit'] ?? 0,
                    'profit_s' => $request->on_fee_options['profit_s'] ?? 0,
                ]);

                return response()->json([
                    'status' => 0,
                    'message' => 'Комиссии обновлены'
                ]);
            }

            if(isset($request->on_min_max_price)) {
                $item->update([
                    'min_price1' => $request->on_min_max_price['min_price1'] ?? 0,
                    'min_price2' => $request->on_min_max_price['min_price2'] ?? 0,
                    'max_price1' => $request->on_min_max_price['max_price1'] ?? 0,
                    'max_price2' => $request->on_min_max_price['max_price2'] ?? 0,
                    'is_manual_min_price1' => $request->on_min_max_price['is_manual_min_price1'] ?? 0,
                    'is_manual_min_price2' => $request->on_min_max_price['is_manual_min_price2'] ?? 0,
                    'is_manual_max_price1' => $request->on_min_max_price['is_manual_max_price1'] ?? 0,
                    'is_manual_max_price2' => $request->on_min_max_price['is_manual_max_price2'] ?? 0,
                ]);

                return response()->json([
                    'status' => 0,
                    'message' => 'Комиссии обновлены'
                ]);
            }

            // Сумма зависящая от суммы обмена
            if($request->has('exchange_amount'))
            {
                DirectionExchangePercentAmount::create([
                    'id_direction_exchange' => $id,
                    'percentage' => 0,
                    'from_amount',
                    'to_amount',
                ]);

                return response()->json([
                    'status' => 0,
                    'message' => 'Комиссия добавлена'
                ]);
            }

            if($request->has('id_exchange_amount_trash'))
            {
                DirectionExchangePercentAmount::where([
                    ['id_direction_exchange', $item->id],
                    ['id', (int)$request->get('id_exchange_amount_trash')],
                ])->delete();


                return response()->json([
                    'status' => 0,
                    'message' => 'Запись удалена'
                ]);
            }

            // Сумма зависящая от суммы обмена
            if ($request->has('is_exchange_amount')) {
                $rows = $request->input('exchangeAmountData', []);

                if (is_array($rows) && !empty($rows)) {
                    foreach ($rows as $row) {
                        $attributes = is_array($row['attributes'] ?? null) ? $row['attributes'] : [];

                        // from/to: принимаем строку/число, поддерживаем запятую
                        $fromRaw = (string) ($attributes['from_amount'] ?? '');
                        $toRaw   = (string) ($attributes['to_amount'] ?? '');

                        $fromAmount = $fromRaw !== '' ? (float) str_replace(',', '.', $fromRaw) : 0.0;
                        $toAmount   = $toRaw !== '' ? (float) str_replace(',', '.', $toRaw) : 0.0;

                        // если диапазон задан некорректно — сбрасываем
                        if ($fromAmount > $toAmount) {
                            $fromAmount = 0.0;
                            $toAmount = 0.0;
                        }

                        // percentage: разрешаем дробные значения (0.5), знак (+/-), %, а также простые формулы
                        // Пример допустимых значений: 0.5, -1, 1%, -0.25%, 1+0.5, 10/2, 5*2
                        $percentageRaw = trim((string) ($attributes['percentage'] ?? ''));
                        $percentageRaw = str_replace(',', '.', $percentageRaw);

                        // допускаем только цифры, точку, операторы и знак процента (точка нужна для 0.5)
                        $percentage = ($percentageRaw !== '' && preg_match('/^[\d\-\+\*\/%.]+$/', $percentageRaw))
                            ? security_xss($percentageRaw)
                            : '0';

                        $rowId = isset($row['id']) ? (int) $row['id'] : 0;
                        if ($rowId <= 0) {
                            continue;
                        }

                        DirectionExchangePercentAmount::where([
                            ['id_direction_exchange', $item->id],
                            ['id', $rowId],
                        ])->update([
                            'from_amount' => $fromAmount,
                            'to_amount'   => $toAmount,
                            'percentage'  => $percentage,
                        ]);
                    }
                }

                return response()->json([
                    'status'  => 0,
                    'message' => 'Комиссия обновлена'
                ]);
            }


            // Сумма зависящая от суммы обмена
            if($request->has('selector_fee'))
            {
                DirectionExchangeSelectorFee::create([
                    'id_direction_exchange' => $id,
                    'name' => null,
                    'fee' => '0'
                ]);

                return response()->json([
                    'status' => 0,
                    'message' => 'Комиссия добавлена'
                ]);
            }

            if($request->has('id_selector_fee_trash'))
            {
                DirectionExchangeSelectorFee::where([
                    ['id_direction_exchange', $item->id],
                    ['id', (int)$request->get('id_selector_fee_trash')],
                ])->delete();


                return response()->json([
                    'status' => 0,
                    'message' => 'Комиссия удалена'
                ]);
            }

            if($request->has('is_selector_fee'))
            {
                if (!empty($request->selectorFeeData)) {
                    foreach ($request->selectorFeeData as $value) {
                        $percentage = (isset($value['fee']) && preg_match('/^[\+\-\*\/]?\d+(\.\d+)?%?$/', $value['fee']))
                            ? security_xss($value['fee'])
                            : '0';

                        DirectionExchangeSelectorFee::where([
                            ['id_direction_exchange', $item->id],
                            ['id', $value['id']],
                        ])->update([
                            'name' => $value['name'],
                            'description' => $value['description'],
                            'fee' => $percentage
                        ]);
                    }
                }


                return response()->json([
                    'status' => 0,
                    'message' => 'Комиссия обновлена'
                ]);
            }

            // Обновление городов
            if ($request->has('is_change_city')) {
                $citiesData = $request->citiesData ?? [];

                if (count($citiesData) > 0) {
                    foreach ($citiesData as $value) {
                        $attributes = $value['attributes'] ?? [];

                        /** @var DirectionExchangeCity|null $cityModel */
                        $cityModel = DirectionExchangeCity::where([
                            ['id_direction_exchange', $item->id],
                            ['id', $value['id']],
                        ])->first();

                        if (! $cityModel) {
                            continue;
                        }

                        // обновляем поля города
                        $cityModel->update([
                            'add_comm'           => (string) (empty($attributes['add_comm']) ? 0 : security_xss($attributes['add_comm'])),
                            'param'              => security_xss($attributes['param'] ?? ''),
                            'min_price'          => (string) security_xss($attributes['min_price'] ?? 0),
                            'max_price'          => (string) security_xss($attributes['max_price'] ?? 0),
                            'instruction'        => $attributes['instruction'] ?? [],
                            'information'        => $attributes['information'] ?? [],
                            'profit'             => (float) abs($attributes['profit'] ?? 0),
                            'profit_s'           => (float) abs($attributes['profit_s'] ?? 0),
                            'profit_partner'     => (float) abs($attributes['profit_partner'] ?? 0),
                            'profit_partner_s'   => (float) abs($attributes['profit_partner_s'] ?? 0),
                            'bid_value'          => (string) ($attributes['bid_value'] ?? ''),
                            'exchange_text'      => $attributes['exchange_text'] ?? [],
                        ]);

                        // профиль
                        $profileId = $attributes['direction_city_profile_id'] ?? null;
                        $syncIds = [];

                        if (!empty($profileId)) {
                            $syncIds = [(int) $profileId];
                        }

                        // тут sync:
                        // если $syncIds пустой — связь очистится,
                        // если есть id — будет один профиль
                        $cityModel->cityProfiles()->sync($syncIds);
                    }
                }

                return response()->json([
                    'status'  => 0,
                    'message' => 'Города обновлены'
                ]);
            }


            if ($request->has('cityStatusUpdate')) {
                $cityData = $request->input('cityStatusUpdate');

                DirectionExchangeCity::where([
                    ['id', $cityData['id']],
                    ['id_direction_exchange', $item->id],
                ])->update([
                    'status' => $cityData['status'] ? 1 : 0
                ]);

                return response()->json([
                    'status' => 0,
                    'message' => 'Статус города успешно обновлён'
                ]);
            }

            // Работа с городами
            if($request->has('ids_cities_change'))
            {
                // Получаем актуальный список
                $db_cities = $item->direction_exchange_cities->pluck('city_id', 'city_id')->toArray();
                $currencyCities = collect($request->ids_cities_change)->diff($db_cities)->toArray();

                if(count($currencyCities) > 0)
                {
                    foreach ($currencyCities as $city) {
                        DirectionExchangeCity::updateOrCreate([
                            'id_direction_exchange' => $item->id,
                            'city_id' => $city,
                        ], [
                                'id_direction_exchange' => $item->id,
                                'city_id' => $city,
                                'add_comm' => '0',
                            ]
                        );
                    }
                } else {
                    $db_cities = $item->direction_exchange_cities->pluck('city_id', 'city_id')->diff($request->ids_cities_change);

                    foreach ($db_cities as $city) {
                        DirectionExchangeCity::where([
                            ['id_direction_exchange', '=', $item->id],
                            ['city_id', '=', $city]
                        ])->delete();
                    }
                }

                return response()->json([
                    'status' => 0,
                    'message' => 'Города обновлены'
                ]);
            }

            return response()->json([
                'status' => 0
            ]);
        }


        // Получаем название страницы и в зависимости от страницы получаем данные
        $pageName = $request->has('pageName') ? $request->get('pageName') : 'main';


        // Получаем колонки, которые обязательно должны быть заполнены
        $requiredValidator = collect($this->optionsFields[$pageName])
            ->reject(fn($res) => !isset($res['validator']))
            ->map(fn($res) => $res['validator'])
            ->toArray();

        if ($pageName === 'fees-pay') {
            $requiredValidator = array_merge($requiredValidator, [
                'pay_min_comm' => [
                    'numeric',
                    function ($attribute, $value, $fail) use ($request) {
                        if ((float)$value > 0 && empty($request->pay_comm_percent) && empty($request->pay_comm_currency)) {
                            $fail('Нельзя указать минимальную комиссию (pay_min_comm) без процента или валютной комиссии.');
                        }
                    },
                ],
                'pay_min2_comm' => [
                    'numeric',
                    function ($attribute, $value, $fail) use ($request) {
                        if ((float)$value > 0 && empty($request->pay_comm2_percent) && empty($request->pay_comm2_currency)) {
                            $fail('Нельзя указать минимальную комиссию 2 (pay_min2_comm) без процента или валютной комиссии.');
                        }
                    },
                ],
            ]);
        }

        if ($pageName === 'fees-oth') {
            $requiredValidator = array_merge($requiredValidator, [
                'oth_min_comm' => [
                    'numeric',
                    function ($attribute, $value, $fail) use ($request) {
                        if ((float)$value > 0 && empty($request->oth_comm_percent) && empty($request->oth_comm_currency)) {
                            $fail('Нельзя указать минимальную комиссию (oth_min_comm) без процента (oth_comm_percent) или валютной комиссии (oth_comm_currency).');
                        }
                    },
                ],
                'oth_min2_comm' => [
                    'numeric',
                    function ($attribute, $value, $fail) use ($request) {
                        if ((float)$value > 0 && empty($request->oth_comm2_percent) && empty($request->oth_comm2_currency)) {
                            $fail('Нельзя указать минимальную комиссию 2 (oth_min2_comm) без процента (oth_comm2_percent) или валютной комиссии (oth_comm2_currency).');
                        }
                    },
                ],
            ]);
        }

        $validator = Validator::make($request->all(), $requiredValidator);


        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        try {
            // Данные главной страницы
            $responseData = [];
            if(isset($this->optionsFields[$pageName]))
            {
                foreach ($this->optionsFields[$pageName] as $key => $value) {

                    if(isset($value['sync']) and isset($value['relationship'])) {
                        $item->{$value['relationship']}()->sync($request->{$key} ?? []);
                        continue;
                    }

                    $fieldValue = $request->get($key, $value['default'] ?? '');

                    if (isset($value['validate_empty_html'])) {

                        $fieldValue = cleanHtmlContent($fieldValue);

                        if (is_array($fieldValue)) {
                            foreach ($fieldValue as $locale => $val) {
                                if (!is_string($val)) {
                                    // Если значение не строка, приводим его к пустой строке
                                    $fieldValue[$locale] = '';
                                    continue;
                                }

                                if (trim(strip_tags($val)) === '') {
                                    $fieldValue[$locale] = '';
                                }
                            }
                        } else {
                            if (!is_string($fieldValue) || trim(strip_tags($fieldValue)) === '') {
                                $fieldValue = '';
                            }
                        }
                    }

                    // Разбиваем
                    if(isset($value['ext_params'])) {
                        $epExplode = explode('ep__', $key);
                        $responseData['ext_params'][$epExplode[1]] = $fieldValue;
                    } else {
                        $responseData[$key] = $fieldValue;
                    }
                }
            }

            if(!empty($responseData)) {
                $item->update($responseData);
            }

        }catch (\Exception $exception) {
            return response()->json([
                'status' => 1,
                'message' => 'Данные не обновлены'. $exception->getMessage()
            ]);
        }


        if($pageName == 'main')
        {
            $name = direction_name($item);
            $item->update([
                'tech_name' => empty($request->tech_name) ? $name : $request->tech_name,
            ]);
        }

        app(ExchangeRatesCacheInvalidator::class)->afterCommit('admin.direction.update');

        return response()->json([
            'status' => 0,
            'message' => sprintf('%s успешно обновлен', $item->tech_name),
            'status_label' => ((int) $item->status === 1) ? 'Active' : 'Disabled',
            'rate_source' => $item->parser_source_name,
        ]);
    }

    /**
     * Полное удаление направления
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id, Request $request)
    {
        $direction = DirectionExchange::find($id);
        $oldItem = $direction;
        if ($direction->tasks->count() > 0)
        {
            $direction->update([
                'status' => 2,
            ]);

            return response()->json([
                'status' => 0,
                'message' => $direction->tech_name. ' перемещен в корзину'
            ]);
        }

        // Удаляем связанные уведомления
        DirectionNotification::where('id_direction_exchange', '=', $id)->delete();
        BestChangeDirection::where('id_direction_exchange', $id)->delete();
        DirectionDay::where('id_direction_exchange', '=', $id)->delete();
        $direction->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->tech_name. ' успешно удален'
        ]);
    }

    /**
     * Сортировка направлений
     *
     * @return JsonResponse
     */
    public function sorting(): JsonResponse
    {
        // опционально: кэш на 60 сек, можно убрать, если не нужен
        $data = Currency::query()
            ->select(['id', 'sorting_1', 'status', 'id_payment', 'id_code_currency'])
            ->where('status', 0)
            ->whereHas('direction_exchange_in', fn($q) => $q->where('status', 1))
            ->with([
                'payment:id,name,logo',
                'code_currency:id,name',
            ])
            ->orderBy('sorting_1')
            ->get()
            ->map(function ($item) {
                return [
                    'id'   => $item->id,
                    'name' => trim(($item->payment->name ?? '').' '.($item->code_currency->name ?? '')),
                    'logo' => '/storage/payment_systems/' . ($item->payment->logo ?? ''),
                ];
            })
            ->values();

        return response()->json($data);
    }

    /**
     * Сортировка по ID Валюты
     *
     * @param int $id
     * @return JsonResponse
     */
    public function sortingId(int $id): JsonResponse
    {
        // опционально: кэш на 60 сек, можно убрать, если не нужен
        $cacheKey = "direction_sorting_out:currency1={$id}:v1";

        $data = DirectionExchange::query()
                ->active()
                ->select([
                    'id',
                    'tech_name',
                    'id_currency1',
                    'id_currency2',
                    'sorting_2',
                ])
                ->where('id_currency1', $id)
                ->with([
                    // проверь корректность FK в модели Currency
                    'currency2:id,id_payment,id_code_currency',
                    'currency2.payment:id,name,logo',
                    'currency2.code_currency:id,name',
                ])
                ->orderBy('sorting_2')
                ->get()
                ->map(function ($item) {
                    $payment = $item->currency2?->payment;
                    $code    = $item->currency2?->code_currency;

                    return [
                        'id'        => $item->id,
                        'key_id'    => "{$item->id}-{$item->id_currency1}",
                        'name'      => trim(($payment->name ?? '').' '.($code->name ?? '')),
                        'logo'      => '/storage/payment_systems/' . ($payment->logo ?? ''),
                        'full_name' => (string) $item->tech_name,
                    ];
                })
                ->values();
        return response()->json($data);
    }


    /**
     * Создаем дубликат направления
     *
     * @return JsonResponse
     */
    public function duplicate(int $id)
    {
        if (config('iexexchanger.is_reading_mode'))
        {
            return response()->json([
                'status' => 1,
                'message' => 'Данная функция недоступна в демо версии'
            ]);
        }

        $direction = DirectionExchange::find($id);
        $newExchange = $direction->replicate();
        $newExchange->save();

        return response()->json([
            'status' => 0,
            'message' => 'Дубликат успешно создан'
        ]);
    }

    /**
     * Удаленные направления
     */
    public function trashed(Request $request): JsonResponse
    {
        if (mb_strtoupper($request->getMethod()) == 'POST')
        {
            $direction = DirectionExchange::find((int)$request->id);
            $direction->update([
                'status' => 0
            ]);

            return response()->json([
                'status' => 0,
                'message' => $direction->tech_name.' восстановлен'
            ]);
        }

        $exchanges = DirectionExchange::where('status', '=', 2)
            ->orderByDesc('id')->filter($request->all())
            ->paginate(20);

        return response()->json([
            'items' => new DirectionExchangeTrashedResources($exchanges)
        ]);
    }

    /**
     * Недостающие направления
     */
    public function uncreatedDirections(): array
    {
        if ((int) iEXSetting('is_enabled_uncreated_directions') == 0) {
            return [];
        }

        $currencies = Currency::where('status', 0)->get();

        $currency_1 = [];
        foreach ($currencies as $currency) {
            $currencies2 = Currency::where('status', 0)->where('id', '!=', $currency->id)->get();
            $currency_1_2 = [];
            foreach ($currencies2 as $item2) {
                $currency_1_2[$item2->id] = $item2->payment->name.' '.$item2->code_currency->name;
            }

            $currency_1[$currency->id] = [
                'name' => $currency->payment->name.' '.$currency->code_currency->name,
                'relationship' => $currency_1_2,
            ];
        }

        $direction_exists = [];

        foreach ($currency_1 as $key => $value) {
            foreach ($value['relationship'] as $key2 => $value2) {
                $exists = DirectionExchange::where([
                    ['id_currency1', $key],
                    ['id_currency2', $key2],
                ])->exists();

                if ($exists) {
                    continue;
                }

                $direction_exists[] = [
                    'currency1' => [
                        'id' => $key,
                        'name' => $value['name'],
                    ],
                    'currency2' => [
                        'id' => $key2,
                        'name' => $value2,
                    ],
                ];
            }
        }

        return $direction_exists;
    }

    /**
     * Простой список последних логов по направлениям (direction_exchange)
     * без пагинации и фильтров — только активные (state='active' или active_flag=1)
     */
    public function activeLogs(): JsonResponse
    {
        $logs = \App\Models\SystemLog::query()
            ->select([
                'id',
                'entity_id',
                'entity_type',
                'level',
                'state',
                'active_flag',
                'message',
                'last_message',
                'times_seen',
                'occurred_at',
                'last_seen_at',
                'resolved_at'
            ])
            ->where('module', 'direction_exchange')
            ->where(function ($q) {
                $q->where('state', 'active')
                    ->orWhere('active_flag', 1);
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        // Получаем ID направлений, чтобы подтянуть названия
        $directionIds = $logs->pluck('entity_id')->filter()->unique()->values();
        $names = DirectionExchange::query()
            ->whereIn('id', $directionIds)
            ->pluck('tech_name', 'id');

        // Формируем финальный ответ
        $data = $logs->map(function ($log) use ($names) {
            return [
                'id'             => (int) $log->id,
                'direction_id'   => (int) $log->entity_id,
                'direction_name' => $names->get($log->entity_id) ?? '-',
                'level'          => $log->level,
                'state'          => $log->state ?? 'log',
                'text'           => $log->last_message ?? $log->message,
                'times_seen'     => (int) ($log->times_seen ?? 0),
                'occurred_at'    => optional($log->occurred_at)->toDateTimeString(),
                'last_seen_at'   => optional($log->last_seen_at)->toDateTimeString(),
                'resolved_at'    => optional($log->resolved_at)->toDateTimeString(),
            ];
        });

        return response()->json([
            'count' => $data->count(),
            'items' => $data,
        ]);
    }


    public function bulkChangeStatus(Request $request)
    {
        $data = $request->validate([
            'ids'          => ['required', 'array', 'min:1'],
            'ids.*'        => ['integer', 'exists:direction_exchange,id'],
            'change_status'=> ['required', 'boolean'],
        ]);

        $enable = (bool) $data['change_status'];
        if ($enable) {
            $blocked = DirectionExchange::whereIn('id', $data['ids'])
                ->where(function ($q) {
                    $q->whereNull('course_value')
                        ->orWhere('course_value', '')
                        ->orWhereRaw('CAST(course_value AS DECIMAL(36,18)) <= 0');
                })
                ->pluck('id')
                ->all();
            if ($blocked !== [] && (int) $request->get('confirm_activate_without_course', 0) !== 1) {
                return response()->json([
                    'status' => 1,
                    'code' => 'ACTIVATE_ZERO_COURSE_BLOCKED',
                    'blocked_ids' => $blocked,
                    'message' => 'Cannot bulk-activate directions with course <= 0 without confirm_activate_without_course=1.',
                ]);
            }
        }

        DirectionExchange::whereIn('id', $data['ids'])
            ->update(['status' => $data['change_status']]);

        app(ExchangeRatesCacheInvalidator::class)->afterCommit('admin.direction.bulk_status');

        return response()->json([
            'status'  => 0,
            'message' => $data['change_status']
                ? 'Все направления включены'
                : 'Все направления отключены',
            'status_label' => $data['change_status'] ? 'Active' : 'Disabled',
        ]);
    }
}
