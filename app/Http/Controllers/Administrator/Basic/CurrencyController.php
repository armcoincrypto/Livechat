<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Basic\CurrenciesResources;
use App\Models\AMLService;
use App\Models\CodeCurrency;
use App\Models\Currency;
use App\Models\CurrencyAnalytics;
use App\Support\ValidatorWallet;
use iEXPackages\BestChange\Facades\BestChangeFacade;
use App\Models\CurrencyCommand;
use App\Models\CurrencyFields;
use App\Models\CurrencyGroupNetwork;
use App\Models\CurrencyNotification;
use App\Models\DirectionExchange;
use App\Models\FilterCurrency;
use App\Models\GatewayMerchant;
use App\Models\GatewayPayment;
use App\Models\Payment;
use App\Models\Requisites;
use App\Models\Reserve;
use App\Models\TaskStatus;
use App\Services\Rates\ExchangeRatesCacheInvalidator;
use iEXPackages\OrderRecount\Models\OrderRecountPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CurrencyController extends Controller
{
    /**
     * Дополнительные фильтры
     */
    protected array $allowFiltered = [
        'currency_is_recount_default',
        'currency_recount_percent',
        'currency_recount_time_minutes',
        'currency_recount_statusses',
        'is_recount_order_after_successful'
    ];

    /**
     * Поля для обновления данных
     *
     * @return array
    */
    protected array $optionsFields = [
        'default' => [
            'tech_name' => [],
        ],

        'main' => [
            'id_payment' => ['validator' => ['required']],
            'id_code_currency' => ['validator' => ['required']],
            'tech_name' => [],
            'designation_xml' => ['validator' => ['required']],
            'visible_code_currency' => [],
            'number_format' => [],
            'convert_by' => [],
            'tech_currency_name' => ['locales' => true, 'validate_empty_html' => true],
            'status' => [],
            'tags' => ['type' => 'string'],
        ],

        'fields' => [
            'in_custom_fields' => ['relationship' => 'currency_in_fields', 'pluck' => 'id', 'sync' => true],
            'out_custom_fields' => ['relationship' => 'currency_out_fields', 'pluck' => 'id', 'sync' => true],
            'account_number_field' => ['locales' => true],
            'account_number_field_text' => ['locales' => true],
            'visible_give' => ['type' => 'bool','default' => 0],
            'visible_receiving' => ['type' => 'bool','default' => 0],
            'first_value' => ['default' => ''],
            'min_char' => ['validator' => ['required'],'default' => 0],
            'max_char' => ['validator' => ['required'],'default' => 0],
            'min_max_error_message' => ['locales' => true],
            'field_name_from' => ['locales' => true],
            'field_name_to' => ['locales' => true],
            'field_comment_from' => ['locales' => true],
            'field_comment_to' => ['locales' => true],
            'mask_account_from' => [],
            'mask_account_to' => [],
            'mask_placeholder_char_from' => [],
            'mask_placeholder_char_to' => [],
            'allowed_char' => ['type' => 'int','default' => 0],
            'remove_spaces_requisite' => ['type' => 'bool', 'default' => 0],
            'validation_account_from' => ['type' => 'string','default' => ''],
            'validation_account_to' => ['type' => 'string','default' => ''],
            'valid_account_error_from' => ['locales' => true],
            'valid_account_error_to' => ['locales' => true],
            'display_scan_qr_from' => ['type' => 'int','default' => 0],
            'display_scan_qr_to' => ['type' => 'int','default' => 0],
        ],

        'verification' => [
            'is_enabled_verification' => [],
            'min_amount_verification' => [],
            'verification_text' => ['locales' => true, 'validate_empty_html' => true],
            'verification_info' => ['locales' => true, 'validate_empty_html' => true],
            'is_verified_cabinet' => ['type' => 'string']
        ],

        'verification-identity' => [
            'identity_verification_mode' => [],
            'identity_min_amount' => [],
            'identity_text' => ['locales' => true, 'validate_empty_html' => true],
            'identity_info' => ['locales' => true, 'validate_empty_html' => true]
        ],


        'information' => [
            'instruction_exchange' => ['locales' => true, 'validate_empty_html' => true],
            'desc_exchange' => ['locales' => true, 'validate_empty_html' => true],
            'formalization_text' => ['locales' => true, 'validate_empty_html' => true],
            'other_docs_in' => ['locales' => true, 'validate_empty_html' => true],
            'other_docs_out' => ['locales' => true, 'validate_empty_html' => true],
            'notice_in' => ['locales' => true, 'validate_empty_html' => true],
            'notice_out' => ['locales' => true, 'validate_empty_html' => true],
            'button_create_order' => ['locales' => true, 'validate_empty_html' => true],
            'button_create_order_text' => ['locales' => true, 'validate_empty_html' => true],
            'instruction_source_mode' => ['type' => 'string','default' => 'auto'],
            'desc_source_mode' => ['type' => 'string','default' => 'auto'],
            'text_color' => []
        ],
        'aml' => [
            'id_aml_service' => ['type' => 'int', 'default' => 0],
            'is_aml_check_wallet' => ['type' => 'int', 'default' => 0],
            'aml_wallet_from_amount' => ['default' => 0],
            'error_for_aml_check_wallet' => ['type' => 'string', 'default' => '0'],
            'is_aml_check_tx' => ['type' => 'int', 'default' => 0],
            'aml_tx_from_amount' => ['default' => 0],
            'error_for_aml_check_tx' => ['type' => 'string', 'default' => '0'],
            'aml_text_in' => ['locales' => true, 'validate_empty_html' => true],
            'aml_text_out' => ['locales' => true, 'validate_empty_html' => true]
        ],

        'reserve-limits' => [
            'max_limit_in_reserve' => ['default' => 0],
            'max_display_reserve' => ['default' => 0],
            'profit_percent_reserve' => ['default' => 0],
            'hour_limit_order_pending' => ['default' => 0],
            'hour_limit_order_process' => ['default' => 0],
            'day_limit_give' => ['default' => 0],
            'day_limit_receive' => ['default' => 0],
            'month_limit_in' => ['default' => 0],
            'month_limit_out' => ['default' => 0],
            'transfer_percent_reserve' => ['default' => 0],
            'transfer_amount_reserve' => ['default' => 0],
        ],

        'requisites' => [
            'method_request_payment' => ['type' => 'int', 'default' => 0],
            'type_output_requisites' => ['type' => 'int', 'default' => 0],
            'request_payment_text' => ['locales' => true, 'validate_empty_html' => true]
        ],

        'recount' => [
            'is_unique_recount_order' => ['type' => 'string', 'default' => '0'],
            'recount_statusses' => [],
            'is_enable_auto_recount_order' => ['type' => 'int', 'default' => 0],
            'recount_time_minutes' => ['type' => 'int', 'default' => 0],
            'recount_course_text' => ['locales' => true, 'validate_empty_html' => true],
            'unique_recount_percent' => ['default' => 0]
        ],

        'partner' => [
            'is_payment_default' => ['type' => 'int', 'default' => 0],
            'is_payment_unique' => ['type' => 'int', 'default' => 0],
            'payout_commission' => ['default' => 0],
            'payout_commission_amount' => ['default' => 0]
        ],

        'options' => [
            'is_qrcode' => ['type' => 'string', 'default' => '0'],
            'prefix_qrcode' => ['type' => 'string', 'default' => ''],
            'is_qrcode_amount' => ['default' => 0],
            'is_allow_file' => ['type' => 'string', 'default' => '0'],
            'file_allow_title' => ['locales' => true, 'validate_empty_html' => true],
            'file_allow_description' => ['locales' => true, 'validate_empty_html' => true],
            'is_allow_order' => ['type' => 'string', 'default' => '0'],
            'is_enabled_step_order' => ['type' => 'string', 'default' => '0'],
            'is_email_verification_modal' => ['type' => 'string', 'default' => '0'],
        ]
    ];

    /**
     * Список валют
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function index(Request $request)
    {
        if($request->has('showPage') and $request->get('showPage') == 'settings')
        {
            // Статус заявок
            $statusOrders = TaskStatus::whereNotIn('id', [4])->pluck('name', 'id')->map(function ($value, $id) {
                return [
                    'id' => $id,
                    'value' => $value
                ];
            });;

            return response()->json([
                'statusesOrder' => $statusOrders,
                'attributes' => $this->loadGlobalRecountSettings(),
            ]);
        }

        // Проверка на существование валюты
        if($request->has('is_unique_currency')) {
            $idPayment = (int)$request->get('id_payment');
            $idCodeCurrency = (int)$request->get('id_code_currency');

            $isUnique = Currency::where('is_archive', '=', 0)->where([
                ['id_payment', '=', $idPayment],
                ['id_code_currency', '=', $idCodeCurrency]
            ])->select('is_archive', 'id_payment', 'id_code_currency', 'id')->first();


            return response()->json([
                'is_exists' => isset($isUnique) and isset($isUnique->id) ? 1 : 0,
                'id_currency' => isset($isUnique->id) ? $isUnique->id : 0,
            ]);
        }

        // Фильтры
        $currencies = Currency::with(['payment' => function ($q) {
            $q->select('id', 'name', 'logo', 'is_local_image');
        }, 'code_currency' => function ($q) {
            $q->select('id', 'name');
        }, 'merchants', 'currency_analytics', 'updated_user' => function ($q) {
            $q->select('id', 'name');
        }, 'filters' => function ($q) {
            $q->select('filter_currency.id', 'name');
        }, 'currency_group_network' => function ($q) {
            $q->select('id', 'title');
        }, 'reserve' => function ($q) {
            $q->select('id', 'id_currency', 'summa', 'black_amount');
        }, 'reserve.link' => function ($q) {
            $q->select('reserve_id', 'parent_reserve_id', 'is_active');
        }, 'gateway_payments'])->withCount(['direction_exchange_in', 'direction_exchange_out'])
            ->active()->where('is_archive', '=', 0)->filter($request->all())
            ->orderByDesc('id');

        // Список платежных систем
        $payments = Payment::active()->select('id','name', 'logo')->get()->map(function ($value) {
            return [
                'id' => $value->id,
                'value' => $value->name,
                'image' => '/storage/payment_systems/' .$value->logo
            ];
        });
        // Список кодов валют
        $code_currencies = CodeCurrency::pluck('name', 'id')->map(function ($value, $id) {
            return [
                'id' => $id,
                'value' => $value
            ];
        });

        // Фильтры валют
        $filter_currencies = FilterCurrency::pluck('name', 'id')->map(function ($value, $id) {
            return [
                'id' => $id,
                'value' => $value
            ];
        });
        // Группы сетей
        $group_networks = CurrencyGroupNetwork::pluck('title', 'id')->map(function ($value, $id) {
            return [
                'id' => $id,
                'value' => $value
            ];
        });

        $gateways_merchants = GatewayMerchant::select('id', 'name', 'alias', 'status')->get()
            ->map(function ($item) {
                $isActive = (int)$item->status === 1;
                $statusText = $isActive ? __('активна') : __('не активна');
                $aliasPart = $item->alias ? ' — ' . $item->alias : '';
                return [
                    'id' => (int)$item->id,
                    'name' => (string)$item->name,
                    'alias' => (string)$item->alias,
                    'status' => (int)$item->status,
                    'value' => sprintf('%s%s • (%s)', $item->name, $aliasPart, $statusText),
                ];
            });

        // Список доступных валюты для выплат
        $gateways_payments = GatewayPayment::select('id', 'name', 'status')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => sprintf('%s  (%s)', $item->name, ($item->status == 1 ? __('активна') : __('не активна'))),
            ];
        })->pluck('name', 'id')->map(function ($value, $id) {
            return [
                'id' => $id,
                'value' => $value
            ];
        });

        $admin_hidden_columns = explode(',', iEXSetting('admin_currencies_column_hidden_columns'));
        $allowedColumns = ['icon', 'pc', 'code', 'xml', 'merchant', 'pays', 'reserve', 'receiving', 'sending', 'last_updated'];


        return response()->json([
            'items' => new CurrenciesResources(
                $currencies->paginate(iEXSetting('admin_currencies_pagination', 20))
            ),

            'code_currencies' => $code_currencies->values(),
            'payments' => $payments->values(),
            'filter_currencies' => $filter_currencies->values(),
            'group_networks' => $group_networks->values(),
            'gateways_merchants' => $gateways_merchants->values(),
            'gateways_payments' => $gateways_payments->values(),
            'selected_columns' => collect($admin_hidden_columns)->map(function ($item) {
                return $item;
            })->reject(fn($item) => !in_array($item, $allowedColumns))->values(),
            'per_page' => (int)iEXSetting('admin_currencies_pagination', 20),
        ]);
    }

    /**
     * Обработка и добавление новой валюты
     *
     * @throws \Exception
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        // Если включена возможность обновления данных
        if ((int)$request->is_update === 1 && $request->has('showPage')) {

            if ($request->showPage === 'settings') {
                $this->saveGlobalRecountSettings($request);
            }

            return response()->json([
                'status' => 0,
                'message' => __('Настройки успешно сохранены'),
            ]);
        }


        $validator = Validator::make($request->all(), [
            'id_payment' => ['required', 'numeric'],
            'id_code_currency' => ['required', 'numeric'],
        ]);

        // Перед добавлением новой валюты проверяем на ошибки
        if ($validator->fails()) {
           return response()->json([
               'status' => 1,
               'message' => $validator->messages()->first()
           ]);
        }

        $find = Currency::active()->where([
            ['id_payment', '=', $request->get('id_payment')],
            ['id_code_currency', '=', $request->get('id_code_currency')],
        ]);

        if ($find->exists()) {
            return response()->json([
                'status' => 1,
                'message' => 'Такая валюта уже существует'
            ]);
        }

        $currency = Currency::create([
            'id_payment' => ($request->has('id_payment') ? $request->get('id_payment') : 0),
            'id_code_currency' => ($request->has('id_code_currency') ? $request->get('id_code_currency') : 0),
        ]);

        $name = $currency->payment->name.' '.$currency->code_currency->name;
        $currency->update([
            'tech_name' => $request->get('tech_name') ?? $name,
        ]);

        // Создаем резерв для валюты
        Reserve::updateOrCreate([
            'id_currency' => $currency->id,
        ], [
            'id_currency' => $currency->id,
            'id_group' => 0,
            'summa' => 0,
            'id_user' => $request->user()->id,
        ]);

        // Добавляем реквизит по умолчанию
        Requisites::create([
            'id_currency' => $currency->id,
            'account_number' => '',
            'status' => 1,
            'id_group' => 0,
        ]);

        return response()->json([
            'status' => 0,
            'id' => $currency->id,
            'message' => $name . ' ' .__('успешно добавлен')
        ]);
    }

    /**
     * Форма редактирования валюты
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(Request $request, int $id)
    {
        // Информация о валюте
        $item = Currency::findOrFail($id);

        // Получаем название страницы и в зависимости от страницы получаем данные
        $pageName = $request->has('pageName') ? $request->get('pageName') : 'main';


        // Данные главной страницы
        $responseData = [];

        if($pageName == 'main') {
            // Список платежных систем
            $payments = Payment::select('name', 'id', 'logo')->get()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'value' => $item->name,
                    'image' => '/storage/payment_systems/' .$item->logo
                ];
            });
            // Список кодов валют
            $code_currencies = CodeCurrency::select('name', 'id')->get()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'value' => $item->name
                ];
            });
            // Список фильтров валют
            $filter_currencies = FilterCurrency::select('name', 'id')->get()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'value' => $item->name
                ];
            });


            // Список групп для сетей
            $networks = CurrencyGroupNetwork::select('title', 'id')->get()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'value' => $item->title
                ];
            });

            try {
                $xml_codes = BestChangeFacade::rates()->codesMap();
            } catch (\Throwable $e) {
                $xml_codes = [];
            }

            // Если из BestChange ничего не пришло — пробуем взять из storage/app/bestchange/codes.json
            if (empty($xml_codes)) {
                $path = storage_path('app/bestchange/codes.json');

                if (is_file($path)) {
                    $json = @file_get_contents($path);
                    $data = json_decode((string) $json, true);

                    if (is_array($data)) {
                        $xml_codes = $data;
                    }
                }
            }

            $responseData['options'] = [
                'payments' => $payments,
                'code_currencies' => $code_currencies,
                'filter_currencies' => $filter_currencies,
                'networks' => $networks,
                'xml_codes' => collect($xml_codes)->map(function($value, $key) {
                    return [
                        'id' => $key,
                        'value' => $value
                    ];
                })->values()
            ];
        } elseif($pageName == 'fields')
        {
            $validatorLabels = app(ValidatorWallet::class)->getTypeLabels();

            $validators = collect($validatorLabels)
                ->map(function (string $label, string $type) {
                    return [
                        'id' => $type,
                        'value' => $label,
                    ];
                })
                ->sortBy('value')
                ->values();

            // Доп. поля
            $in_custom_fields = CurrencyFields::whereStatus(0)->where('when_print', '=', 0)
                ->orderBy('sorting')->orderBy('id')->pluck('name', 'id')->map(function ($value, $id) {
                    return [
                        'id' => $id,
                        'value' => $value
                    ];
                })->values();

            $out_custom_fields = CurrencyFields::whereStatus(0)->where('when_print', '=', 1)
                ->orderBy('sorting_out')->orderBy('id')->pluck('name', 'id')->map(function ($value, $id) {
                    return [
                        'id' => $id,
                        'value' => $value
                    ];
                })->values();

            $responseData['options'] = [
                'in_custom_fields' => $in_custom_fields,
                'out_custom_fields' => $out_custom_fields,
                'validators' => $validators
            ];
        } elseif($pageName == 'aml')
        {
            // Список AML сервисов
            $aml_services = AMLService::whereNotNull('name')->orderBy('id')->get()->map(function($item) {
                $status = __('не активна');
                if ($item->status == 1) {
                    $status = __('активна');
                }

                return [
                    'id' => $item->id,
                    'value' => sprintf('%s (%s)', $item->name, $status)
                ];
            });


            $responseData['options'] = [
                'aml_services' => $aml_services
            ];
        } elseif($pageName == 'recount')
        {
            // Статус заявок
            $statusOrders = TaskStatus::whereNotIn('id', [4])->pluck('name', 'id')->map(function ($value, $id) {
                return [
                    'id' => $id,
                    'value' => $value
                ];
            })->values();

            $responseData['options'] = [
                'statuses' => $statusOrders
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

            if(isset($value['relationship']) and !empty($value['pluck'])) {
                $responseData['attributes'][$key] = $item->{$value['relationship']}->pluck($value['pluck']);
            }

            if(isset($value['locales'])) {
                $responseData['attributes'][$key]  = $item->getTranslations($key);
            }

            if(isset($value['type'])) {
                $responseData['attributes'][$key] = variableStrictValue($responseData['attributes'][$key], $value['type']);
            }
        }

        return response()->json(array_merge($responseData, [
            'default' => [
                'tech_name' => $item->tech_name,
            ],
            // Explicit polarity labels (DB semantics unchanged): currency 0=Active, 1/2=Disabled.
            'status_semantics' => [
                'field' => 'currencies.status',
                'active_values' => [0],
                'disabled_values' => [1, 2],
                'labels' => [
                    0 => 'Active',
                    1 => 'Disabled',
                    2 => 'Disabled',
                ],
                'note' => 'Admin JSON envelope status (0=success, 1=error) is unrelated to currencies.status.',
            ],
            'status_label' => in_array((int) $item->status, [1, 2], true) ? 'Disabled' : 'Active',
        ]));
    }

    /**
     * Обновление данные
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id)
    {
        $item = Currency::findOrFail($id);

        if($request->has('is_update'))
        {
            if($request->has('ids_change_merchants')) {
                $item->merchants()->sync($request->ids_change_merchants ?? []);
            }

            if($request->has('ids_change_pays')) {
                $item->gateway_payments()->sync($request->ids_change_pays ?? []);
            }

            return response()->json([]);
        }

        // Получаем название страницы и в зависимости от страницы получаем данные
        $pageName = $request->has('pageName') ? $request->get('pageName') : 'main';


        // Получаем колонки, которые обязательно должны быть заполнены
        $requiredValidator = collect($this->optionsFields[$pageName])
            ->reject(fn($res) => !isset($res['validator']))->map(fn($res) => $res['validator'])->toArray();

        $validator = Validator::make($request->all(), $requiredValidator);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        // Guard: disabling a currency mass-disables related directions — require explicit confirm.
        if ($pageName === 'main' && $request->has('status')) {
            $nextStatus = (int) $request->get('status');
            $prevStatus = (int) $item->status;
            if (in_array($nextStatus, [1, 2], true) && !in_array($prevStatus, [1, 2], true)) {
                $activeDirs = DirectionExchange::query()
                    ->where('status', 1)
                    ->where(function ($q) use ($id) {
                        $q->where('id_currency1', $id)->orWhere('id_currency2', $id);
                    })
                    ->count();
                if ($activeDirs > 0 && (int) $request->get('confirm_disable_directions', 0) !== 1) {
                    return response()->json([
                        'status' => 1,
                        'code' => 'CONFIRM_DISABLE_DIRECTIONS_REQUIRED',
                        'affected_active_directions' => $activeDirs,
                        'status_label_next' => 'Disabled',
                        'message' => sprintf(
                            'Disabling this currency will disable %d active direction(s). Resubmit with confirm_disable_directions=1.',
                            $activeDirs
                        ),
                    ]);
                }
            }
        }

        try {
            // Данные главной страницы
            $responseData = [];
            if(isset($this->optionsFields[$pageName]))
            {
                foreach ($this->optionsFields[$pageName] as $key => $value) {

                    if (isset($value['sync']) && isset($value['relationship'])) {
                        $item->{$value['relationship']}()->sync($request->{$key} ?? []);
                        continue;
                    }

                    $fieldValue = $request->get($key, $value['default'] ?? '');

                    if (isset($value['validate_empty_html']))
                    {
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

                    // Разбиваем ext_params
                    if (isset($value['ext_params'])) {
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
            $name = $item->payment->name.' '.$item->code_currency->name;
            $item->update([
                'tech_name' => empty($request->tech_name) ? $name : $request->tech_name,
            ]);

            // Если отключена валюты, отключаются все направления
            if (in_array($item->status, [1, 2])) {
                \DB::table('direction_exchange')->where('id_currency1', '=', $item->id)->update(['status' => 0]);
                \DB::table('direction_exchange')->where('id_currency2', '=', $item->id)->update(['status' => 0]);
            }
        }

        app(ExchangeRatesCacheInvalidator::class)->afterCommit('admin.currency.update');

        return response()->json([
            'status' => 0,
            'message' => sprintf('%s успешно обновлен', $item->tech_name),
            'status_label' => in_array((int) $item->status, [1, 2], true) ? 'Disabled' : 'Active',
        ]);
    }

    /**
     * Сделать дубликат валюты
     *
     * @return \Illuminate\Http\JsonResponse
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

        $currency = Currency::find($id);
        $newCurrency = $currency->replicate();
        $newCurrency->save();

        return response()->json([
            'status' => 0,
            'message' => 'Дубликат успешно создан'
        ]);
    }

    /**
     * Удалить валюту
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id)
    {
        $currency = Currency::findOrFail($id);
        $oldItem = $currency;
        // Делаем доп. проверку в списке направлений
        $direction = DirectionExchange::where('id_currency1', '=', $id)
            ->orWhere('id_currency2', '=', $id);

        if ($direction->count() > 0) {
            return \response()->json([
                'status' => 1,
                'message' => 'Выбранную валюту удалить невозможно, К валюте привязаны направления'
            ]);
        }

        // Удаляем еще связанные (Резервы и события резервов)
        Reserve::where('id_currency', $id)->delete();
        CurrencyNotification::where('id_currency', $id)->delete();
        CurrencyFields::where('id_currency', $id)->delete();
        CurrencyCommand::where('id_currency', $id)->delete();
        Requisites::where('id_currency', $id)->delete();
        CurrencyAnalytics::where('id_currency', $id)->delete();
        $currency->delete();

        return \response()->json([
            'status' => 0,
            'message' => $oldItem->tech_name . ' успешно удален'
        ]);
    }

    private function saveGlobalRecountSettings(Request $request): void
    {
        $mode    = (int) $request->input('currency_is_recount_default', 0); // 0|1|2
        $percent = (float) $request->input('currency_recount_percent', 0);
        $minutes = (int) $request->input('currency_recount_time_minutes', 0);

        $maxRecounts   = (int) $request->input('max_recounts_in_status', 0);
        $maxAgeMinutes = (int) $request->input('max_age_in_status_minutes', 0);

        $onlyIfRateChanged = (int) $request->input('only_if_rate_changed', 0);
        $minGiveAmount     = max(0.0, (float) $request->input('min_give_amount', 0));

        $statuses = $this->normalizeIds($request->input('currency_recount_statusses', []));

        $policy = OrderRecountPolicy::query()->firstOrNew([
            'scope_type' => 'global',
            'scope_id'   => null,
        ]);

        // Выключено или статусы не выбраны — выключаем политику
        if ($mode === 0 || $statuses === []) {
            $policy->fill($this->disabledGlobalPolicyPayload())->save();
            return;
        }

        // Если выбран режим "по условиям", но условия не заданы — переводим в "всегда пересчитывать"
        // (иначе any_of не сохранится и UI при загрузке будет выглядеть как mode=1)
        if ($mode === 2 && $minutes <= 0 && $percent <= 0) {
            $mode = 1;
        }

        $conditions = [];

        // Freeze-safe включаем всегда
        $conditions[] = ['type' => 'field_equals', 'field' => 'is_frozen', 'value' => 0];

        // Опционально: пересчитывать только если курс изменился
        if ($onlyIfRateChanged === 1) {
            $conditions[] = ['type' => 'rate_changed_required', 'value' => 1];
        }

        // Опционально: минимальная сумма "Отдаю"
        if ($minGiveAmount > 0) {
            $conditions[] = ['type' => 'min_give_amount', 'value' => $minGiveAmount];
        }

        // В каких статусах разрешён пересчёт
        $conditions[] = ['type' => 'status_in', 'value' => $statuses];

        // Режим 2 = по условиям (процент/интервал)
        if ($mode === 2) {
            $anyOf = [];

            if ($minutes > 0) {
                $anyOf[] = [
                    'type' => 'min_interval_minutes',
                    'cron' => $minutes,
                    'status_change' => 0,
                ];
            }

            if ($percent > 0) {
                $anyOf[] = [
                    'type' => 'rate_percent_change_gte',
                    'value' => $percent,
                    'base' => 'last_rate_value',
                ];
            }

            if ($anyOf !== []) {
                $conditions[] = ['type' => 'any_of', 'value' => $anyOf];
            }
        }

        if ($maxRecounts > 0) {
            $conditions[] = ['type' => 'max_recounts_in_status', 'value' => $maxRecounts];
        }

        if ($maxAgeMinutes > 0) {
            $conditions[] = ['type' => 'max_age_in_status_minutes', 'value' => $maxAgeMinutes];
        }

        $policy->fill([
            'is_enabled'    => 1,
            'priority'      => 100,
            'stop_further'  => 0,
            'title'         => 'Global recount',
            'trigger_types' => ['status-change', 'cron', 'manual'],
            'conditions'    => $conditions,
            'actions'       => [['type' => 'recount', 'at_rate' => 1]],
        ])->save();
    }

    private function loadGlobalRecountSettings(): array
    {
        $defaults = [
            'currency_is_recount_default'    => 0,
            'currency_recount_percent'       => 0,
            'currency_recount_time_minutes'  => 0,
            'currency_recount_statusses'     => [],
            'max_recounts_in_status'         => 0,
            'max_age_in_status_minutes'      => 0,

            'only_if_rate_changed'           => 0,
            'min_give_amount'                => 0,
        ];

        $policy = OrderRecountPolicy::query()
            ->where('scope_type', 'global')
            ->whereNull('scope_id')
            ->first();

        if (!$policy || (int) $policy->is_enabled !== 1) {
            return $defaults;
        }

        $mode        = 1;     // если полиска включена, но any_of нет — значит "всегда"
        $percent     = 0.0;
        $minutes     = 0;
        $statuses    = [];
        $maxRecounts = 0;
        $maxAge      = 0;

        $onlyIfRateChanged = 0;
        $minGiveAmount     = 0.0;

        foreach ((array) $policy->conditions as $cond) {
            $type = (string) ($cond['type'] ?? '');

            if ($type === 'status_in') {
                $statuses = (array) ($cond['value'] ?? []);
                continue;
            }

            if ($type === 'rate_changed_required') {
                $onlyIfRateChanged = ((int) ($cond['value'] ?? 0) === 1) ? 1 : 0;
                continue;
            }

            if ($type === 'min_give_amount') {
                $minGiveAmount = max(0.0, (float) ($cond['value'] ?? 0));
                continue;
            }

            if ($type === 'any_of') {
                $mode = 2;

                $anyOf = (array) ($cond['value'] ?? []);
                $minutes = $this->extractIntervalMinutesFromAnyOf($anyOf);
                $percent = $this->extractPercentFromAnyOf($anyOf);

                continue;
            }

            if ($type === 'max_recounts_in_status') {
                $maxRecounts = (int) ($cond['value'] ?? 0);
                continue;
            }

            if ($type === 'max_age_in_status_minutes') {
                $maxAge = (int) ($cond['value'] ?? 0);
                continue;
            }
        }

        return [
            'currency_is_recount_default'   => $mode,
            'currency_recount_percent'      => $percent,
            'currency_recount_time_minutes' => $minutes,
            'currency_recount_statusses'    => $this->normalizeIds($statuses),
            'max_recounts_in_status'        => $maxRecounts,
            'max_age_in_status_minutes'     => $maxAge,

            'only_if_rate_changed'          => $onlyIfRateChanged,
            'min_give_amount'               => $minGiveAmount,
        ];
    }

    private function normalizeIds(mixed $raw): array
    {
        if ($raw instanceof \Illuminate\Support\Collection) {
            $raw = $raw->all();
        }

        if (is_string($raw)) {
            $raw = explode(',', $raw);
        } elseif (!is_array($raw)) {
            $raw = [];
        }

        $out = [];

        foreach ($raw as $v) {
            // поддержка массива объектов: [{id:1,value:"..."}]
            if (is_array($v) && array_key_exists('id', $v)) {
                $v = $v['id'];
            }

            // поддержка объектов
            if (is_object($v) && isset($v->id)) {
                $v = $v->id;
            }

            $i = (int) $v;
            if ($i > 0) {
                $out[$i] = true;
            }
        }

        return array_keys($out);
    }

    private function disabledGlobalPolicyPayload(): array
    {
        return [
            'is_enabled'    => 0,
            'priority'      => 100,
            'stop_further'  => 0,
            'title'         => 'Global recount',
            'trigger_types' => ['status-change', 'cron', 'manual'],
            'conditions'    => [],
            'actions'       => [['type' => 'recount', 'at_rate' => 1]],
        ];
    }

    private function extractIntervalMinutesFromAnyOf(array $anyOf): int
    {
        foreach ($anyOf as $it) {
            if (!is_array($it)) {
                continue;
            }

            if (($it['type'] ?? '') !== 'min_interval_minutes') {
                continue;
            }

            // старый формат
            if (isset($it['value'])) {
                return (int) $it['value'];
            }

            // новый формат
            if (isset($it['cron'])) {
                return (int) $it['cron'];
            }

            return 0;
        }

        return 0;
    }

    private function extractPercentFromAnyOf(array $anyOf): float
    {
        foreach ($anyOf as $it) {
            if (!is_array($it)) {
                continue;
            }

            if (($it['type'] ?? '') === 'rate_percent_change_gte') {
                return (float) ($it['value'] ?? 0);
            }
        }

        return 0.0;
    }


    public function updateSmallCode(Request $request, int $currencyId): JsonResponse
    {
        $request->validate([
            'small_code' => ['nullable', 'string', 'max:32'],
        ]);

        $currency = Currency::query()->findOrFail($currencyId);

        $currency->small_code = $request->small_code;
        $currency->save();

        return response()->json([
            'status'  => 0,
            'message' => 'Короткий код валюты обновлён',
            'data'    => [
                'id' => $currency->id,
                'small_code' => $currency->small_code,
            ],
        ]);
    }
}
