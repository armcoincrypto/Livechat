<?php

namespace iEXPackages\ExchangerClient\Http\Resources\Orders;

use App\Models\Currency;
use App\Models\MerchantTransactionData;
use App\Models\CurrencyNotification;
use App\Models\CurrencyTemplate;
use App\Models\DirectionNotification;
use App\Models\DirectionTemplate;
use App\Models\Reserve;
use App\Models\User;
use App\Models\VerificationCard;
use App\Models\VerificationCardCategory;
use iEXPackages\Order\Builder\PaymentFieldsBuilder;
use iEXPackages\Order\Services\InvoiceContextService;
use iEXPackages\TagProcessors\TagProcessors;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OrderProcessResource extends JsonResource
{
    /**
     * The "data" wrapper that should be applied.
     *
     * @var string|null
     */
    public static $wrap = 'data';

    /**
     * Название статусов в тест формате
     */
    protected array $statusText = [
        2  => 'pending',
        9  => 'merchant',
        3  => 'process',
        7  => 'pay',
        4  => 'success',
        8  => 'frozen',
        12 => 'check_merchant',
    ];

    /**
     * Информация о пользователе
     *
     * @var User
     */
    protected User $userInfo;

    /**
     * Информация о валютах
     *
     * @var mixed
     */
    protected mixed $currency;

    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->getMainInformation();
    }

    /**
     * Преобразуйте данные в массив.
     *
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        $task = $this->resource;

        // Информация по валюте (Отдаю)
        $in_currency = $this->currency[$this->direction_exchange->id_currency1];

        // Информация по валюте (Получаю)
        $out_currency = $this->currency[$this->direction_exchange->id_currency2];

        // Получаем резерв (Получаю)
        $out_reserve = Reserve::select('id', 'id_currency')
            ->where('id_currency', $this->direction_exchange->id_currency2)
            ->first();

        // Чтобы переменная всегда была определена
        $payment_field_fields = [];

        // Контекст реквизитов/checkout
        $invoiceCtx = app(InvoiceContextService::class)->resolve($task);

        $invoiceMode = (string) ($invoiceCtx['mode'] ?? 'none');

        $payInvoiceUrl = $invoiceMode === 'checkout'
            ? (string) ($invoiceCtx['checkout_url'] ?? '')
            : '';

        $paymentFieldValue = $invoiceMode === 'requisites'
            ? (string) ($invoiceCtx['account'] ?? '')
            : '';

        $invoiceHasDest = $invoiceMode === 'checkout'
            || ($invoiceMode === 'requisites' && $paymentFieldValue !== '');

        if (
            $invoiceHasDest
            && (int) $this->is_from_verification_card === 0
            && (int) ($this->is_from_identity_verification ?? 0) === 0
            && (int) $this->is_wallet_issued === 0
        ) {
            $this->resource->update(['is_wallet_issued' => 1]);
        }

        if (($invoiceCtx['mode'] ?? '') === 'requisites') {
            app(PaymentFieldsBuilder::class)->ensureTaskRequisitesSaved($task, $in_currency, $invoiceCtx);
        }

        // Время обработки заявки
        $start_time = Carbon::now();
        $duration = Carbon::parse($this->created_at)->addSeconds((int) iEXSetting('max_time_task'));
        $lead_time = 0;
        if ($duration->gt($start_time)) {
            $lead_time = $start_time->diffInSeconds($duration);
        }

        // Получаем статус заявки
        $status = $this->statusText[$this->status] ?? 'undefined';

        // Информационные поля реквизитов (если есть)
        if (isset($this->payment_requisites) && isset($this->payment_requisites->requisites_info_fields)) {
            $payment_field_fields = $this->payment_requisites->requisites_info_fields
                ->where('status', '=', 1)
                ->sortBy('sorting')
                ->map(fn ($field) => [
                    'label' => $field->key_name,
                    'value' => $field->value_name,
                ])
                ->toArray();
        }

        $response = [
            'id'       => (string) iEXSetting('client_id_type_for_order') == 1 ? $this->public_id : $this->id,
            'order_id' => $this->id,
            'type'     => 'order',
            'attributes' => [
                'url_pay'                 => config('app.frontend_url') . '/order/' . $this->public_id . '/pay',
                'public_id'               => $this->public_id,
                'created_at'              => $this->created_at->translatedFormat('d F Y, H:i'),
                'created_date'            => $this->created_at->translatedFormat('d M Y'),
                'is_allow_file'           => (int) $in_currency->is_allow_file,
                'payment_system_from_id'  => $this->direction_exchange->id_currency1,
                'payment_system_to_id'    => $this->direction_exchange->id_currency2,
                'is_new_user'             => $this->is_new_user,
                'income_account'          => $this->from_shot,
                'outcome_account'         => $this->to_shot,

                'income_fields' => $this->tasks_fields_currency_in
                    ->whereNotNull('field_name')
                    ->whereNotNull('field_value')
                    ->where('field_name', '!=', '')
                    ->where('field_value', '!=', '')
                    ->map(fn ($f) => [
                        'field_name'  => $f->field_name,
                        'field_value' => $f->field_value,
                    ])
                    ->values(),

                'outcome_fields' => $this->tasks_fields_currency_out
                    ->whereNotNull('field_name')
                    ->whereNotNull('field_value')
                    ->where('field_name', '!=', '')
                    ->where('field_value', '!=', '')
                    ->map(fn ($f) => [
                        'field_name'  => $f->field_name,
                        'field_value' => $f->field_value,
                    ])
                    ->values(),

                'income_amount' => [
                    'currency'       => $in_currency->code_currency->name,
                    'name'           => $in_currency->payment->name,
                    'image'          => '/storage/payment_systems/' . $in_currency->payment->logo,
                    'number_format'  => $in_currency->number_format,
                ],

                'outcome_amount' => [
                    'currency'       => $out_currency->code_currency->name,
                    'name'           => $out_currency->payment->name,
                    'image'          => '/storage/payment_systems/' . $out_currency->payment->logo,
                    'number_format'  => $out_currency->number_format,
                ],

                'income_qrcode' => [
                    'is_qrcode' => (bool) $in_currency->is_qrcode,
                    'prefix'    => (string) $in_currency->prefix_qrcode,
                ],

                'income_payment_timeout' => $lead_time,
                'status'                => $status,

                'buttons' => [
                    'i_pay'      => !empty($this->direction_exchange->order_button_i_pay) ? $this->direction_exchange->order_button_i_pay : null,
                    'i_pay_text' => !empty($this->direction_exchange->order_button_i_pay_text) ? $this->direction_exchange->order_button_i_pay_text : null,
                ],
            ],

            'relationships' => [
                'user' => [
                    'type' => 'user',
                    'attributes' => [
                        'is_auth' => auth()->check(),
                        'email'   => optional($this->userInfo)->email,
                        'name'    => optional($this->userInfo)->name,
                    ],
                ],

                'income_payment_system' => [
                    'data' => [
                        'id'   => $this->direction_exchange->id_currency1,
                        'type' => 'payment_system',
                        'attributes' => [
                            'name'              => ($in_currency->visible_code_currency == 1)
                                ? $in_currency->payment->name . ' ' . $in_currency->code_currency->name
                                : $in_currency->payment->name,
                            'payment_system'    => $in_currency->payment->name,
                            'letter_cod'        => $in_currency->designation_xml,
                            'currency_iso_code' => $in_currency->code_currency->name,
                            'icon_url'          => '/storage/payment_systems/' . $in_currency->payment->logo,
                            'color'             => iEXSetting('is_gradient_text_color') ? '#' . $in_currency->text_color : '',
                            'field_name_from'   => !empty($in_currency->field_name_from) ? $in_currency->field_name_from : null,
                        ],
                    ],
                ],

                'outcome_payment_system' => [
                    'data' => [
                        'id'   => $this->direction_exchange->id_currency2,
                        'type' => 'payment_system',
                        'attributes' => [
                            'name'              => ($out_currency->visible_code_currency == 1)
                                ? $out_currency->payment->name . ' ' . $out_currency->code_currency->name
                                : $out_currency->payment->name,
                            'payment_system'    => $out_currency->payment->name,
                            'letter_cod'        => $out_currency['designation_xml'],
                            'currency_iso_code' => $out_currency->code_currency->name,
                            'icon_url'          => '/storage/payment_systems/' . $out_currency->payment->logo,
                            'color'             => iEXSetting('is_gradient_text_color') ? $out_currency->text_color : '',
                            'field_name_to'     => !empty($out_currency->field_name_to) ? $out_currency->field_name_to : null,
                        ],
                    ],
                ],

                'reserve' => [
                    'id'   => $out_reserve?->id,
                    'type' => 'reserve',
                ],
            ],
        ];

        if ((int) $in_currency->is_allow_file === 1) {
            $response['attributes']['allow_file_data'] = [
                'title'       => !empty($in_currency->file_allow_title) ? $in_currency->file_allow_title : null,
                'description' => !empty($in_currency->file_allow_description) ? $in_currency->file_allow_description : null,
            ];
        }

        // Промокод (упрощённо для клиента)
        if ((int) ($this->id_promo_code ?? 0) > 0) {
            $response['attributes']['promo_code'] = [
                'name'  => optional($this->promo_code)->name
                    ?? $this->promo_code_code
                        ?? null,
                'type'  => $this->promo_code_discount_type ?? null,
                'value' => $this->promo_code_value ?? null,
                'bonus' => $this->receiving_price_with_promocode ?? '0',
            ];
        }

        // Определение способов выдачи реквизитов
        $is_request_payment = 0;

        $direction = $this->direction_exchange;

        // Приоритет: сначала направление, затем валюта
        if ($direction && (int) ($direction->method_request_payment ?? 0) === 1) {
            $is_request_payment = 1;
        } elseif ((int) ($in_currency['method_request_payment'] ?? 0) === 1) {
            $is_request_payment = 1;
        }

        // Если в заявке 0, то выводим стандартный счет
        if ((int) $this->is_request_payment_type == 0) {
            $is_request_payment = 0;
        }

        // Доп. Сообщения при запросе резерва
        $request_payment_text = null;
        if ($is_request_payment == 1) {
            if ($direction && !empty($direction->request_payment_text)) {
                $request_payment_text = $direction->request_payment_text;
            } elseif (!empty($in_currency->request_payment_text)) {
                $request_payment_text = $in_currency->request_payment_text;
            }
        }

        $response['attributes']['method_payment_field'] = $is_request_payment;
        $response['attributes']['is_payment_field'] = (int) $this->direction_exchange->is_hidden_order_pay;

        // Статус проверки реквизитов валидатором (если выбран валидатор)
        $accountValidatorPassed = null;
        $hasValidator = false;

        if ($invoiceMode === 'requisites') {
            $mtd = MerchantTransactionData::query()
                ->select(['account_validator_passed', 'account_validator_type'])
                ->where('id_task', $task->id)
                ->first();

            if ($mtd && !empty($mtd->account_validator_type)) {
                $hasValidator = true;
                $accountValidatorPassed = $mtd->account_validator_passed;
            }
        }

        $response['attributes']['payment_destination_ready'] = $invoiceHasDest;

        $response['attributes']['payment_field'] = [
            'name'         => ($in_currency->account_number_field ?? null),
            'comment'      => ($in_currency->account_number_field_text ?? null),
            'value'        => $paymentFieldValue !== '' ? $paymentFieldValue : null,
            'fields'       => $payment_field_fields ?? [],
            'request_text' => !empty($request_payment_text) ? $request_payment_text : null,
        ];

        if ($hasValidator) {
            $response['attributes']['payment_field']['account_validator_passed'] = $accountValidatorPassed;
        }

        // Получаем доп. поля реквизитов по запросу
        if (isset($this->task_requisite_attached) && !empty($this->task_requisite_attached)) {
            if (!empty($this->task_requisite_attached->ext_params['description'] ?? null)) {
                $response['attributes']['payment_field']['attach_description'] = $this->task_requisite_attached->ext_params['description'];
            }

            if (isset($this->task_requisite_attached->ext_params['fields'])) {
                $response['attributes']['payment_field']['attach_fields'] = $this->task_requisite_attached->ext_params['fields'];
            }
        }

        if (isset($this->payment_requisites) && (int) ($this->payment_requisites->photo_status ?? 0) === 1 && !empty($this->payment_requisites->photo_name)) {
            $response['attributes']['payment_field']['image'] = '/storage/' . $this->payment_requisites->photo_name;
        }

        // QR Code (только если есть значение реквизита)
        if ((bool) ($in_currency['is_qrcode'] ?? false)) {
            $base = trim((string) $paymentFieldValue);
            if ($base !== '') {
                $value = (!empty($in_currency->prefix_qrcode) ? $in_currency->prefix_qrcode . $base : $base);

                if ((int) ($in_currency->is_qrcode_amount ?? 0) === 1) {
                    $value .= (parse_url($value, PHP_URL_QUERY) ? '&' : '?') . 'amount=' . $this->give_price;
                }

                $response['attributes']['income_qrcode']['value'] = $value;
            }
        }

        // Email verification
        $verifyEmail = ((int) ($in_currency['is_email_verification_modal'] ?? 0) === 0)
            ? true
            : !is_null($this->user->email_verified_at);

        $response['attributes']['is_email_verification'] = $verifyEmail;

        $response['attributes']['is_verification'] = true;
        $response['attributes']['verification_status'] = (int) (isset($this->is_from_verification_card) && (int) $this->is_from_verification_card === 1);

        // verification_exists
        $verification_exists = 0;
        if ($response['attributes']['verification_status'] == 1 && !empty($this->from_shot)) {
            $account_number = preg_replace('/\s/', '', $this->from_shot);

            $hasVerifyCheck = VerificationCard::query()
                ->where('id_order', $this->id)
                ->where('email', $this->email)
                ->whereIdentifier($account_number)
                ->where('id_currency', $this->direction_exchange->id_currency1)
                ->where('status', '=', 0)
                ->exists();

            $verification_exists = (int) $hasVerifyCheck;
        }
        $response['attributes']['verification_exists'] = $verification_exists;

        if ($response['attributes']['verification_status'] == 1) {
            $categories_verification = VerificationCardCategory::with('instructions')
                ->where('status', '=', 1)
                ->orderBy('sorting')
                ->get();

            $data_verification = [];
            foreach ($categories_verification as $category) {
                $data_verification[] = [
                    'id'      => $category->id,
                    'name'    => $category->name,
                    'details' => $category->instructions->map(function ($instruction) {
                        return [
                            'id'          => $instruction->id,
                            'name'        => $instruction->name,
                            'text'        => $instruction->text,
                            'notice_text' => $instruction->notice_text,
                            'image'       => '/storage/' . $instruction->image,
                        ];
                    }),
                ];
            }

            $description_verification_card = iEXContentLanguage('description_verification_card');

            if (!empty($this->direction_exchange->direction_verification_info)) {
                $description_verification_card = $this->direction_exchange->direction_verification_info;
            } elseif (!empty($in_currency->verification_info)) {
                $description_verification_card = $in_currency->verification_info;
            }

            $response['attributes']['verifications'] = [
                'text'  => $description_verification_card,
                'qrcode'=> config('app.frontend_url') . '/order/' . $this->public_id . '/pay',
                'error' => iEXContentLanguage('error_verification_card'),
                'items' => $data_verification,
            ];
        }

        // identity verification flags
        $identityFromFlow = (int) ($this->is_from_identity_verification ?? 0);
        $identityVerified = (int) (($this->user->is_verify_account ?? 0) === 1);

        $response['attributes']['is_identity_verification'] = (bool) $identityFromFlow;
        $response['attributes']['identity_verification_status'] = (!$identityVerified && $identityFromFlow) ? 1 : 0;

        // Номер денежного перевода
        if ($this->direction_exchange->is_num_transaction) {
            $response['attributes']['num_tx'] = [
                'label' => (empty($this->direction_exchange->num_transaction_label) ? 'NoName' : $this->direction_exchange->num_transaction_label),
            ];
        }

        // Примечание к транзакции
        if ($this->direction_exchange->is_note_tx) {
            $response['attributes']['note_tx'] = [
                'label' => (empty($this->direction_exchange->note_tx_label) ? 'NoName' : $this->direction_exchange->note_tx_label),
            ];
        }

        $merchantPay = $task->merchant ?? $task->merchant()->first();

        // Цена отдаю/получаю
        $in_price  = $this->give_price;
        $out_price = $this->receiving_price;

        $textInfo = $this->dynamicTextInformation($in_currency, $out_currency);

        $response['attributes']['instructions'] = $textInfo['instruction_direction'] ?? '';
        $response['attributes']['instruction_currency_in'] = !empty($textInfo['instruction_currency_in']) ? $textInfo['instruction_currency_in'] : null;
        $response['attributes']['other_docs'] = !empty($textInfo['other_docs_direction']) ? $textInfo['other_docs_direction'] : null;
        $response['attributes']['notice_process_desc'] = !empty($this->direction_exchange->notice_process_desc) ? $this->direction_exchange->notice_process_desc : null;
        $response['attributes']['deadline'] = !empty($this->direction_exchange->deadline) ? $this->direction_exchange->deadline : null;
        $response['attributes']['other_docs_in'] = !empty($textInfo['other_docs_in']) ? $textInfo['other_docs_in'] : null;
        $response['attributes']['other_docs_out'] = !empty($textInfo['other_docs_out']) ? $textInfo['other_docs_out'] : null;

        if (!empty($merchantPay)) {
            // Меняем сумму отдаю для мерчантов
            if ($payInvoiceUrl !== '') {
                if ((int) $merchantPay->pay_amount === 1) {
                    $in_price = $this->give_price_with_comm_pay;
                } elseif ((int) $merchantPay->pay_amount === 2) {
                    $in_price = $this->give_price_default;
                }
            }

            // Выводим инструкцию к оплате
            if (empty($merchantPay->instruction_payment)) {
                if ((int) iEXSetting('type_instruction_merchant') == 0) {
                    $response['attributes']['instructions'] = null;
                }
            } else {
                $response['attributes']['instructions'] = Str::markdown(
                    app(TagProcessors::class)->setProcessor('order')
                        ->setText($merchantPay->instruction_payment)
                        ->setData($this)
                        ->process()
                        ->getText()
                );
            }
        }

        $response['attributes']['income_amount']['amount'] = $in_price;
        $response['attributes']['outcome_amount']['amount'] = $out_price;

        // Ссылка на оплату (checkout)
        if ($payInvoiceUrl !== '') {
            $response['attributes']['pay_invoice_url'] = $payInvoiceUrl;
        }


        $pay_fields = app(PaymentFieldsBuilder::class)->buildPayFields($task, $in_currency, $invoiceCtx);
        if (!empty($pay_fields)) {
            $response['relationships']['pay_fields'] = $pay_fields;
        }

        $notification = DirectionNotification::where([
            ['id_direction_exchange', '=', $this->id_direction_exchange],
            ['status', '=', 1],
        ])->orderBy('sorting')->get()->filter(function ($filter) {
            if ($filter->is_enabled_schedule == 0) {
                return true;
            }

            $now = Carbon::now();
            $fromTime = Carbon::parse($filter->from_time);
            $toTime = Carbon::parse($filter->to_time);

            return $now->between($fromTime, $toTime);
        })->map(function ($item) {
            return [
                'text_color'     => $item->text_color,
                'bg_color'       => $item->bg_color,
                'title'          => $item->title,
                'description'    => $item->description,
                'is_order_detail'=> $item->is_order_detail,
            ];
        })->toArray();

        if (!empty($notification)) {
            $response['relationships']['notifications'] = $notification;
        }

        $currencyIdForNotification = (int) ($this->direction_exchange->id_currency1 ?? 0);

        $currency_notification = Cache::remember(
            'currency-notification-' . $currencyIdForNotification,
            Carbon::now()->addMinutes(20),
            function () use ($currencyIdForNotification) {
                $view = CurrencyNotification::where([
                    ['id_currency', '=', $currencyIdForNotification],
                    ['status', '=', 1],
                ])->orderBy('sorting')->pluck('description', 'css_class');

                return $view->toArray();
            }
        );

        $response['relationships']['income_payment_systems']['notifications'] = $currency_notification;

        if ((int) $this->status === 4) {
            $response['attributes']['events']['message'] = iEXContentLanguage('s_order_notify_text');
        }

        $response['attributes']['course_display'] = $this->course_display;

        // Наличные (cities)
        if (isset($this->task_info) && !empty($this->task_info->country_name) && !empty($this->task_info->city_name)) {
            $response['attributes']['cities_value'] = sprintf('%s - %s', $this->task_info->country_name, $this->task_info->city_name);

            if ($this->task_info->directionCity) {
                $directionCity = $this->task_info->directionCity;

                if (!empty($directionCity->instruction)) {
                    $rawInstruction = $directionCity->instruction;

                    $processedInstruction = app(TagProcessors::class)
                        ->setProcessor('direction_city')
                        ->setText($rawInstruction)
                        ->setData([
                            'direction_id' => $this->id_direction_exchange ?? ($this->direction_exchange->id ?? null),
                            'city_id'      => $directionCity->id,
                        ])
                        ->process()
                        ->getText();

                    $response['attributes']['cities']['instruction'] = $processedInstruction;
                }

                if (!empty($directionCity->bid_value)) {
                    $response['attributes']['cities']['bid_value'] = $directionCity->bid_value;
                }

                if (!empty($directionCity->exchange_text)) {
                    $response['attributes']['cities']['exchange_text'] = $directionCity->exchange_text;
                }
            }
        }

        return $response;
    }

    /**
     * Собираем всю текстовую информацию для преобразования
     *
     * @param $in_currency
     * @param $out_currency
     * @return array
     */
    private function dynamicTextInformation($in_currency, $out_currency): array
    {
        static $currencyTemplates = null;
        static $directionTemplates = null;

        if (is_null($currencyTemplates)) {
            $currencyTemplates = CurrencyTemplate::all()->keyBy('id_type');
        }

        if (is_null($directionTemplates)) {
            $directionTemplates = DirectionTemplate::all()->keyBy('id_type');
        }

        $rawInstructionCurrency = $this->resolveTemplateText(
            defaultText: $in_currency->instruction_exchange,
            template: $currencyTemplates->get(0)
        );

        $rawInstructionDirection = $this->resolveTemplateText(
            defaultText: $this->direction_exchange->instructions,
            template: $directionTemplates->get(0)
        );

        $instructionCurrency = $this->handlerText($rawInstructionCurrency);
        $instructionDirection = $this->handlerText($rawInstructionDirection);

        $directionMode = $this->direction_exchange->instruction_source_mode ?? 'auto';
        $currencyMode = $in_currency->instruction_source_mode ?? 'auto';

        $effectiveMode = $directionMode !== 'auto' ? $directionMode : $currencyMode;
        if (!in_array($effectiveMode, ['direction', 'currency'], true)) {
            $effectiveMode = 'auto';
        }

        $instructionCurrencyIn = $instructionCurrency;
        $instructionDirectionFinal = $instructionDirection;

        switch ($effectiveMode) {
            case 'direction':
                if (!empty(trim((string) $instructionDirection))) {
                    $instructionDirectionFinal = $instructionDirection;
                    $instructionCurrencyIn = null;
                } else {
                    $instructionDirectionFinal = $instructionCurrency;
                    $instructionCurrencyIn = null;
                }
                break;

            case 'currency':
                if (!empty(trim((string) $instructionCurrency))) {
                    $instructionDirectionFinal = $instructionCurrency;
                    $instructionCurrencyIn = null;
                } else {
                    $instructionDirectionFinal = $instructionDirection;
                    $instructionCurrencyIn = null;
                }
                break;

            case 'auto':
            default:
                $instructionDirectionFinal = $instructionDirection;
                $instructionCurrencyIn = $instructionCurrency ?: null;
                break;
        }

        return [
            'instruction_currency_in' => $instructionCurrencyIn,
            'instruction_direction'   => $instructionDirectionFinal,

            'other_docs_direction' => $this->handlerText(
                $this->resolveTemplateText(
                    defaultText: $this->direction_exchange->other_docs,
                    template: $directionTemplates->get(4)
                )
            ),

            'other_docs_in' => $this->handlerText(
                $this->resolveTemplateText(
                    defaultText: $in_currency->other_docs_in,
                    template: $currencyTemplates->get(2)
                )
            ),

            'other_docs_out' => $this->handlerText(
                $this->resolveTemplateText(
                    defaultText: $out_currency->other_docs_out,
                    template: $currencyTemplates->get(3)
                )
            ),
        ];
    }

    /**
     * Универсальный метод получения текста из шаблона с учетом правил отображения.
     *
     * @param string|null $defaultText
     * @param mixed|null $template
     *
     * @return string
     */
    private function resolveTemplateText(mixed $defaultText, mixed $template = null): string
    {
        if (is_array($defaultText)) {
            $defaultText = implode("\n", $defaultText);
        } elseif (!is_string($defaultText)) {
            $defaultText = (string) $defaultText;
        }

        if (empty($template) || empty($template->text)) {
            return $defaultText;
        }

        return match ((int) $template->type_view_info) {
            1 => (string) $template->text,
            2 => !empty(trim($defaultText)) ? $defaultText : (string) $template->text,
            default => $defaultText,
        };
    }

    protected function handlerText($text = null): string|null
    {
        if (empty($text)) {
            return $text;
        }

        $app = app(TagProcessors::class);

        return $app->setProcessor('order')
            ->setText($text)
            ->setData($this)
            ->process()
            ->getText();
    }

    /**
     * Получаем важные параметры
     *
     * @return void
     */
    private function getMainInformation(): void
    {
        $this->currency = Currency::with([
            'payment' => function ($q) {
                $q->select('id', 'name', 'enable_svg', 'logo', 'is_local_image');
            },
            'code_currency' => function ($q) {
                $q->select('id', 'name');
            },
            'reserve' => function ($q) {
                $q->select('id', 'id_currency', 'summa');
            },
            'requisites_fields' => function ($q) {
                $q->select('*');
            },
        ])
            ->whereIn('id', [$this->direction_exchange->id_currency1, $this->direction_exchange->id_currency2])
            ->get()
            ->keyBy('id');

        $this->userInfo = User::select('id', 'email', 'name')
            ->where('id', '=', $this->id_user)
            ->first();
    }
}
