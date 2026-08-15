<?php

namespace App\Models;

use App\Models\Casts\JsonCasts;
use App\Models\Filters\CurrencyFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrencyLabel> $labels    Метки (все стороны)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrencyLabel> $labelsIn  Метки для стороны "Отдаю" (give)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrencyLabel> $labelsOut Метки для стороны "Получаю" (receive)
 */
class Currency extends Model
{
    use Filterable, HasTranslations, SoftDeletes;

    protected $table = 'currencies';

    protected $fillable = [
        'small_code',
        'id_payment',
        'id_code_currency',
        'designation_xml',
        'id_group_network',
        'convert_by',
        'number_format',
        'day_limit_give',
        'day_limit_receive',
        'month_limit_in',
        'month_limit_out',
        'min_char',
        'visible',
        'max_char',
        'field_name_from',
        'field_name_to',
        'field_comment_from',
        'field_comment_to',
        'allowed_char',
        'status',
        'created_at',
        'updated_at',
        'visible_give',
        'visible_receiving',
        'id_filter_currency',
        'visible_code_currency',
        'background_color',
        'is_email_verification_modal',
        'text_color',
        'char_default',
        'mask_account_from',
        'mask_account_to',
        'mask_placeholder_char_from',
        'mask_placeholder_char_to',
        'is_auto_check_modal',
        'sorting_1',
        'sorting_tariffs',
        'id_pay',
        'validation_account_from',
        'validation_account_to',
        'is_qrcode',
        'prefix_qrcode',
        'is_qrcode_amount',
        'is_payment_default',
        'is_payment_unique',
        'account_number_field',
        'notice_in',
        'transfer_percent_reserve',
        'transfer_amount_reserve',
        'remove_spaces_requisite',
        'payout_commission',
        'is_archive',
        'id_group',
        'is_enabled_verification',
        'min_amount_verification',
        'is_user_verification',
        'payout_commission_amount',
        'id_auto_reserve',
        'max_limit_in_reserve',
        'hour_limit_order_pending',
        'hour_limit_order_process',
        'profit_percent_reserve',
        'is_card_detail',
        'max_display_reserve',
        'is_income_banner',
        'recount_percent',
        'is_recount_default',
        'notice_out',
        'is_unique_recount_order',
        'is_enable_auto_recount_order',
        'unique_recount_percent',
        'recount_statusses',
        'recount_time_minutes',
        'desc_exchange',
        'merchant_validator_fail_strategy',
        'created_user_id',
        'updated_user_id',
        'is_fire',
        'tech_currency_name',
        'button_create_order',
        'button_create_order_text',
        'formalization_text',
        'network_code',
        'aml_text_in',
        'aml_text_out',
        'instruction_exchange',
        'other_docs_in',
        'other_docs_out',
        'is_allow_order',
        'tech_name',
        'first_value',
        'is_verified_cabinet',
        'verification_info',
        'verification_text',
        'identity_text',
        'identity_info',
        'recount_course_text',
        'type_output_requisites',
        'is_allow_file',
        'is_enabled_step_order',
        'network_code_out',
        'valid_account_error_from',
        'valid_account_error_to',
        'min_max_error_message',
        'account_number_field_text',
        'allow_autopay',
        'id_aml_service',
        'is_aml_check_wallet',
        'is_aml_check_tx',
        'aml_tx_from_amount',
        'aml_wallet_from_amount',
        'method_request_payment',
        'request_payment_text',

        'merchant_day_limit_amount',
        'merchant_month_limit_amount',
        'merchant_min_amount_for_order',
        'merchant_max_amount_for_order',
        'merchant_day_limit',
        'merchant_month_limit',

        'pay_day_limit_amount',
        'pay_month_limit_amount',
        'pay_min_amount_for_order',
        'pay_max_amount_for_order',
        'pay_day_limit',
        'pay_month_limit',
        'error_for_aml_check_wallet',
        'error_for_aml_check_tx',

        'file_allow_title',
        'file_allow_description',
        'ext_params',

        'tags',
        'display_scan_qr_from',
        'display_scan_qr_to',
        'instruction_source_mode',
        'desc_source_mode',
        'identity_verification_rules'
    ];

    public $translatable = [
        'desc_exchange',
        'notice_in',
        'notice_out',
        'field_name_from',
        'field_name_to',
        'field_comment_from',
        'field_comment_to',
        'account_number_field',
        'button_create_order',
        'button_create_order_text',
        'formalization_text',
        'aml_text_in',
        'aml_text_out',
        'instruction_exchange',
        'other_docs_in',
        'other_docs_out',
        'tech_currency_name',
        'verification_info',
        'verification_text',
        'identity_text',
        'identity_info',
        'recount_course_text',
        'valid_account_error_from',
        'valid_account_error_to',
        'min_max_error_message',
        'account_number_field_text',
        'request_payment_text',
        'file_allow_title',
        'file_allow_description'
    ];

    protected $casts = [
        'remove_spaces_requisite' => 'boolean',
        'deleted_at' => 'datetime',
        'recount_statusses' => JsonCasts::class,
        'ext_params' => 'array',
        'identity_verification_rules' => JsonCasts::class,
    ];

    /**
     * Фильтры
     */
    public function modelFilter()
    {
        return $this->provideFilter(CurrencyFilter::class);
    }

    /**
     * Включите в запрос, чтобы отображались только активные валюты.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', 2);
    }

    public function scopeEnabledCurrency($query)
    {
        return $query->where('status', '!=', 2)->where('is_archive', '=', 0);
    }

    public function scopeHideDisabled($query)
    {
        return $query->where('status', '!=', 1);
    }

    /**
     * Дополнительные поля, отдаю
     */
    public function currency_in_fields(): MorphToMany
    {
        return $this->morphToMany(
            CurrencyFields::class,
            'model',
            'currency_in_has_fields',
            'model_id',
            'field_id'
        );
    }

    /**
     * Дополнительные поля, получаю
     */
    public function currency_out_fields(): MorphToMany
    {
        return $this->morphToMany(
            CurrencyFields::class,
            'model',
            'currency_out_has_fields',
            'model_id',
            'field_id'
        );
    }

    /**
     * Дополнительные поля для реквизитов
     */
    public function requisites_fields(): MorphToMany
    {
        return $this->morphToMany(
            RequisiteField::class,
            'model',
            'currency_requisites_has_fields',
            'model_id',
            'field_id'
        );
    }

    /**
     * Выплаты
     */
    public function pay()
    {
        return $this->hasOne(GatewayPayment::class, 'id', 'id_pay');
    }

    /**
     * Мерчант
     */
    public function merchants(): MorphToMany
    {
        return $this->morphToMany(
            GatewayMerchant::class,
            'model',
            'currency_merchants',
            'model_id',
            'gateway_merchant_id',
        )->withPivot(['network_code', 'validator_type']);
    }

    public function gateway_payments(): MorphToMany
    {
        return $this->morphToMany(
            GatewayPayment::class,
            'model',
            'currency_payments',
            'model_id',
            'gateway_payment_id'
        )->withPivot('network_code');
    }


    /**
     * Проверка оплаты
     */
    public function check_pay()
    {
        return $this->hasOne(GatewayPayment::class, 'id', 'id_check_pay');
    }

    public function auto_reserve()
    {
        return $this->hasOne(GatewayPayment::class, 'id', 'id_auto_reserve');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'id', 'id_payment');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'id', 'id_payment');
    }

    public function code_currency()
    {
        return $this->hasOne(CodeCurrency::class, 'id', 'id_code_currency');
    }

    public function reserve()
    {
        return $this->hasOne(Reserve::class, 'id_currency', 'id');
    }

    public function requisites()
    {
        return $this->hasOne(Requisites::class, 'id_currency', 'id')->where('status', '=', 1);
    }

    public function requisites_many()
    {
        return $this->hasMany(Requisites::class, 'id_currency', 'id')->where('status', '=', 1);
    }

    public function filters(): BelongsToMany
    {
        return $this->belongsToMany(
            FilterCurrency::class,
            'currency_filter',
            'currency_id',
            'filter_currency_id'
        );
    }

    public function direction_exchange1()
    {
        return $this->hasOne(DirectionExchange::class, 'id_currency1', 'id');
    }

    public function direction_exchange2()
    {
        return $this->hasOne(DirectionExchange::class, 'id_currency2', 'id');
    }

    /**
     * Счетчик заявок (Отдали)
     */
    public function tasks_in_count()
    {
        return $this->hasMany(DirectionExchange::class, 'id_currency1', 'id')
            ->leftJoin('tasks', 'direction_exchange.id', '=', 'tasks.id_direction_exchange')
            ->whereNotIn('tasks.status', [11])
            ->count('tasks.id');
    }

    /**
     * Счетчик заявок (Получили)
     */
    public function tasks_out_count()
    {
        return $this->hasMany(DirectionExchange::class, 'id_currency2', 'id')
            ->leftJoin('tasks', 'direction_exchange.id', '=', 'tasks.id_direction_exchange')
            ->whereNotIn('tasks.status', [11])
            ->count('tasks.id');
    }

    /**
     * Информация по направления для (Отдаю)
     */
    public function direction_exchange_in()
    {
        return $this->hasMany(DirectionExchange::class, 'id_currency1', 'id');
    }

    /**
     * Информация по направления для (Получаю)
     */
    public function direction_exchange_out()
    {
        return $this->hasMany(DirectionExchange::class, 'id_currency2', 'id');
    }

    public function commands()
    {
        return $this->hasMany(CurrencyCommand::class, 'id_currency', 'id')->orderBy('sorting');
    }

    public function currency_analytics()
    {
        return $this->hasOne(CurrencyAnalytics::class, 'id_currency', 'id');
    }

    public function updated_user()
    {
        return $this->hasOne(User::class, 'id', 'updated_user_id');
    }

    public function aml_service()
    {
        return $this->hasOne(AMLService::class, 'id', 'id_aml_service');
    }

    /**
     * Связь «многие-ко-многим» между валютой и метками через таблицу `currency_label_currency`.
     * Pivot-поля:
     *  - side: 'give' | 'receive' — сторона применения метки
     *  - priority: целочисленный приоритет сортировки (меньше — выше)
     *  - is_active: 1/0 — флаг активности связи
     *
     * @return BelongsToMany<\App\Models\CurrencyLabel>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(CurrencyLabel::class, 'currency_label_currency', 'currency_id', 'label_id')
            ->withPivot(['side', 'priority', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Метки для стороны «Отдаю» (give).
     * Возвращает только связи, у которых pivot.side = 'give'.
     *
     * @return BelongsToMany<\App\Models\CurrencyLabel>
     */
    public function labelsIn(): BelongsToMany
    {
        return $this->labels()->wherePivot('side', 'give');
    }

    /**
     * Метки для стороны «Получаю» (receive).
     * Возвращает только связи, у которых pivot.side = 'receive'.
     *
     * @return BelongsToMany<\App\Models\CurrencyLabel>
     */
    public function labelsOut(): BelongsToMany
    {
        return $this->labels()->wherePivot('side', 'receive');
    }

    public function extraOutProfiles()
    {
        return $this->belongsToMany(
            ExtraOutProfile::class,
            'extra_out_profile_currencies',
            'currency_id',
            'profile_id'
        )->withTimestamps();
    }

    /**
     * Группы для сетей
     *
     * @return HasOne
     */
    public function currency_group_network(): HasOne
    {
        return $this->hasOne(CurrencyGroupNetwork::class, 'id', 'id_group_network');
    }

    /**
     * Правила разрешённых/запрещённых банков для этой валюты
     * в модуле проверки BIN (BinInspector).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function binBankRules()
    {
        return $this->hasMany(CurrencyBinBankRule::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            CurrencyCategory::class,
            'currency_category_items',
            'currency_id',
            'currency_category_id'
        )
            ->withPivot(['position', 'is_active'])
            ->withTimestamps()
            ->orderBy('currency_category_items.position');
    }
}
