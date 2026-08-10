<?php

namespace App\Models;

use App\Models\Casts\JsonCasts;
use App\Models\Filters\DirectionExchangeFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class DirectionExchange extends Model
{
    use Filterable,
        HasTranslations,
        SoftDeletes;

    protected $table = 'direction_exchange';

    protected $casts = [
        'deleted_at' => 'datetime',
        'fix_fee_statuses' => JsonCasts::class,
        'floating_fee_statuses' => JsonCasts::class,
        'auto_del_order_status' => JsonCasts::class,
        'card_verification_rules' => JsonCasts::class,
        'identity_verification_rules' => JsonCasts::class,
    ];

    protected $fillable = [
        'is_type_rate',
        'fix_fee',
        'floating_fee',
        'fix_fee_time',
        'floating_fee_time',
        'fix_fee_statuses',
        'fix_recount_percent',
        'fix_is_enable_recount',
        'fix_fee_display',
        'floating_fee_statuses',
        'floating_threshold_recount_up',
        'floating_threshold_recount_down',
        'floating_fee_display',
        'type_rate_description',
        'is_hidden_not_device',
        'id_currency1',
        'id_currency2',
        'sorting_1',
        'sorting_2',
        'sorting_tariffs',
        'id_crypto_parser',
        'add_course1',
        'add_course1_s',
        'status',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'deadline',
        'instructions',
        'min_price1',
        'min_price2',
        'max_price1',
        'max_price2',
        'commission1',
        'commission2',
        'commission_s1',
        'commission_s2',
        'your_add_course1',
        'your_add_course1_s',
        'profit',
        'profit_s',
        'profit_partner',
        'profit_partner_s',
        'sign',
        'parent_url',
        'is_not_partner',
        'individual_percentage',
        'fixed_payout',
        'export_label_param',
        'minimum_payout',
        'maximum_payout',
        'allow_export',
        'allow_export_from',
        'allow_export_to',
        'is_main',
        'is_restrict_editing',
        'is_enabled_exchange',
        'is_unique_amount_from',
        'is_hidden_order_pay',
        'from_on_time',
        'to_on_time',
        'hidden_export_label_param',
        'is_manual_min_price1',
        'is_manual_max_price1',
        'is_manual_min_price2',
        'is_manual_max_price2',
        'in_who_pay_commission',
        'out_who_pay_commission',
        'id_competitor',
        'course_in',
        'course_our',
        'max_amount_newbie',
        'max_order_one_ip',
        'max_order_one_account1',
        'max_order_one_account2',
        'max_order_one_user',
        'max_order_one_email',
        'not_ip',
        'cr_min_sum',
        'cr_max_sum',
        'cr_add_course',
        'cr_id_new_rate',
        'max_percent_partner',
        'languages',
        'is_hidden_not_locale',
        'device',
        'rl_min2_course',
        'rl_max2_course',
        'rl_id_parser_exchange',
        'rl_add_course',
        'oth_comm_percent',
        'oth_comm_currency',
        'oth_comm2_percent',
        'oth_comm2_currency',
        'oth_min_comm',
        'oth_min2_comm',
        'auto_del_order_status',
        'auto_del_order_day',
        'auto_del_order_hour',
        'auto_del_order_minute',
        'is_hidden_tariffs',
        'is_holding_direction',
        'reserve_max_limit',
        'reserve_limit_day',
        'reserve_limit_month',
        'is_num_transaction',
        'num_transaction_label',
        'is_note_tx',
        'note_tx_label',
        'max_order_one_ip_day',
        'max_order_one_user_day',
        'max_order_one_email_day',
        'max_order_one_account1_day',
        'max_order_one_account2_day',
        'enable_file_parser_rate',
        'id_file_parser_rate',
        'is_enable_alt_bs_parser',
        'is_disable_bs_error',
        'course_value',
        'exchange_rate',
        'is_error_rate',
        'exchange_rate_str',
        'error_rate_text',
        'tech_name',
        'last_order_id',
        'last_order_at',
        'first_order_id',
        'desc_exchange',
        'desc_exchange_dop',
        'id_partner_parser_rate',
        'id_parser_formula_rate',
        'type_output_requisites',
        'formalization_text',
        'min_count_exchanges_client',
        'order_button_i_pay_text',
        'order_button_i_pay',
        'is_allow_telegram_bot',
        'manual_rate_value',
        'parser_source_name',
        'other_docs',
        'is_notify_exchange_amount',
        'is_disable_auto_reg',
        'label_floating',
        'label_floating_percent',
        'label_delay',
        'pay_comm_percent',
        'pay_comm_currency',
        'pay_comm2_percent',
        'pay_comm2_currency',
        'pay_min_comm',
        'pay_min2_comm',
        'is_enable_user_discount',
        'type_profit_field',
        'text_order_success',
        'text_order_failed',
        'text_order_handler',
        'is_verified_account',
        'text_order_confirm',
        'order_button_i_confirm',
        'notice_process_desc',
        'multiplicity_amount',
        'multiplicity_type',
        'multiplicity_comment',
        'type_reserve',
        'direction_reserve',
        'text_order_created_email',

        'network_code',
        'merchant_day_limit_amount',
        'merchant_month_limit_amount',
        'merchant_min_amount_for_order',
        'merchant_max_amount_for_order',
        'merchant_day_limit',
        'merchant_month_limit',
        'group_id', // удалить
        'network_code_out',
        'pay_day_limit_amount',
        'pay_month_limit_amount',
        'pay_min_amount_for_order',
        'pay_max_amount_for_order',
        'pay_day_limit',
        'pay_month_limit',
        'allow_autopay',
        'interval_confirm_order',
        'file_rate_source',
        'title_selector_fee',
        'text_selector_fee',

        'card_verification_type',
        'card_verification_rules',

        'no_verification_description',
        'verification_description',

        'instruction_source_mode',
        'desc_source_mode',
        'method_request_payment',
        'request_payment_text',
        'limit_profile_id',

        'direction_verification_text',
        'direction_verification_info',

        'identity_verification_type',
        'identity_verification_rules',
        'identity_text',
        'identity_info',
        'profit_profile_id',
    ];

    public $translatable = [
        'desc_exchange',
        'desc_exchange_dop',
        'instructions',
        'formalization_text',
        'order_button_i_pay_text',
        'order_button_i_pay',
        'other_docs',
        'text_order_success',
        'text_order_failed',
        'text_order_handler',
        'text_order_confirm',
        'order_button_i_confirm',
        'notice_process_desc',
        'multiplicity_comment',
        'deadline',
        'type_rate_description',
        'text_order_created_email',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'no_verification_description',
        'verification_description',
        'title_selector_fee',
        'text_selector_fee',
        'request_payment_text',

        'direction_verification_text',
        'direction_verification_info',

        'identity_text',
        'identity_info',
    ];


    public function scopeActiveDirection($query, string $from = null, string $to = null)
    {
        $this->scopeQuoteable($query);

        if ($from && $to) {
            if (is_numeric($from) && is_numeric($to)) {
                $query->where([
                    ['id_currency1', $from],
                    ['id_currency2', $to],
                ]);
            } else {
                $query->whereHas('currency1', fn($q) => $q->where('designation_xml', $from))
                    ->whereHas('currency2', fn($q) => $q->where('designation_xml', $to));
            }
        }

        return $query->with('direction_field_sorting');
    }

    /**
     * Canonical public-quote eligibility: direction enabled AND both referenced
     * currencies visible (not hidden/removed). A direction row can be status=1
     * while referencing a currency an admin has hidden (currencies.status=1) or
     * removed (currencies.status=2) — those must never be quoteable, selectable
     * as a default/random pair, or orderable. See F1/F2 remediation.
     */
    public function scopeQuoteable($query)
    {
        return $query
            ->where('status', 1)
            ->whereHas('currency1', fn ($q) => $q->where('status', 0))
            ->whereHas('currency2', fn ($q) => $q->where('status', 0));
    }

    /**
     * Фильтры
     */
    public function modelFilter()
    {
        return $this->provideFilter(DirectionExchangeFilter::class);
    }

    /**
     * Scope a query to only include active.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', 2);
    }

    /**
     * Только включенные направления
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIsEnabled($query)
    {
        return $query->where('status', '=', 1);
    }

    public function direction_exchange_cities()
    {
        return $this->hasMany(DirectionExchangeCity::class, 'id_direction_exchange', 'id')->orderBy('sorting');
    }

    public function direction_exchange_percentage_amount()
    {
        return $this->hasMany(DirectionExchangePercentAmount::class, 'id_direction_exchange', 'id');
    }


    public function direction_exchange_selector_fees()
    {
        return $this->hasMany(DirectionExchangeSelectorFee::class, 'id_direction_exchange', 'id');
    }



    /**
     * Мерчант
     */
    public function merchants(): MorphToMany
    {
        return $this->morphToMany(
            GatewayMerchant::class,
            'model',
            'direction_exchange_merchants',
            'model_id',
            'gateway_merchant_id'
        );
    }

    public function gateway_payments(): MorphToMany
    {
        return $this->morphToMany(
            GatewayPayment::class,
            'model',
            'direction_exchange_pay',
            'model_id',
            'gateway_pay_id'
        );
    }

    /**
     * Мерчант
     */
    public function direction_field(): MorphToMany
    {
        return $this->morphToMany(
            DirectionField::class,
            'model',
            'directions_has_fields',
            'model_id',
            'direction_field_id'
        );
    }

    /**
     * Мерчант
     */
    public function direction_field_sorting(): MorphToMany
    {
        return $this->morphToMany(
            DirectionField::class,
            'model',
            'directions_has_fields',
            'model_id',
            'direction_field_id'
        )->where('status', '=', 0)->orderBy('sorting');
    }

    /**
     * Платежные реквизиты для направлений
     */
    public function direction_requisites(): MorphToMany
    {
        return $this->morphToMany(
            DirectionRequisite::class,
            'model',
            'directions_has_requisites',
            'model_id',
            'direction_requisite_id'
        );
    }

    /**
     * Запрещенные страны
     */
    public function direction_forbidden_countries(): MorphToMany
    {
        return $this->morphToMany(
            GeoCountryList::class,
            'model',
            'directions_has_forbidden_countries',
            'model_id',
            'geo_country_list_id'
        );
    }

    /**
     * Разрешенные страны
     */
    public function direction_allowed_countries(): MorphToMany
    {
        return $this->morphToMany(
            GeoCountryList::class,
            'model',
            'directions_has_allowed_countries',
            'model_id',
            'geo_country_list_id'
        );
    }

    /**
     * Использование направлений под разные режимы работ
     */
    public function direction_modes(): MorphToMany
    {
        return $this->morphToMany(
            DirectionExchangeMode::class,
            'model',
            'directions_has_modes',
            'model_id',
            'direction_exchange_mode_id'
        );
    }

    public function currency1()
    {
        // HISTORICAL_WITH_TRASHED: retired currencies must still resolve on historical directions/orders.
        return $this->hasOne(Currency::class, 'id', 'id_currency1')->withTrashed();
    }

    public function currency1_many()
    {
        return $this->hasMany(Currency::class, 'id', 'id_currency1')->withTrashed();
    }

    public function currency2()
    {
        return $this->hasOne(Currency::class, 'id', 'id_currency2')->withTrashed();
    }

    public function currency2_many()
    {
        return $this->hasMany(Currency::class, 'id', 'id_currency2')->withTrashed();
    }

    public function parser_exchange()
    {
        return $this->hasOne(ParserExchange::class, 'id', 'id_crypto_parser');
    }


    public function cr_new_rate()
    {
        return $this->hasOne(ParserExchange::class, 'id', 'cr_id_new_rate');
    }

    public function rl_parser_exchange()
    {
        return $this->hasOne(ParserExchange::class, 'id', 'rl_id_parser_exchange');
    }

    public function competitor_rates()
    {
        return $this->hasOne(CompetitorRates::class, 'id', 'id_competitor');
    }

    public function file_parser_rates()
    {
        return $this->hasOne(FileParserRates::class, 'id', 'id_file_parser_rate');
    }

    public function partner_parser_rates()
    {
        return $this->hasOne(PartnerParserRates::class, 'id', 'id_partner_parser_rate');
    }

    public function parser_formula_rates()
    {
        return $this->hasOne(ParserFormulaRates::class, 'id', 'id_parser_formula_rate');
    }

    public function history_field1()
    {
        return $this->hasOne(HistoryField::class, 'id_currency1', 'id_currency1');
    }

    public function history_field2()
    {
        return $this->hasOne(HistoryField::class, 'id_currency2', 'id_currency2');
    }

    public function direction_day()
    {
        return $this->hasOne(DirectionDay::class, 'id_direction_exchange', 'id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'id_direction_exchange', 'id');
    }

    /**
     * Данные по Bestchange курсам
     *
     * @return HasOne
     */
    public function bestchange_directions(): HasOne
    {
        return $this->hasOne(BestChangeDirection::class,'id_direction_exchange', 'id');
    }

    /**
     * Чекбоксы соглашений, связанные с направлением обмена
     */
    public function checkboxAgreements(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            CheckboxAgreement::class,
            'checkbox_agreement_direction_exchange',
            'direction_exchange_id',
            'checkbox_agreement_id'
        );
    }

    /**
     * Комиссии (selector fees), связанные с данным направлением обмена.
     *
     * @return BelongsToMany
     */
    public function selectorFees(): BelongsToMany
    {
        return $this->belongsToMany(
            SelectorFee::class,
            'selector_fee_direction_exchange',
            'direction_exchange_id',
            'selector_fee_id'
        );
    }

    /**
     * Групповые комиссии, связанные с данным направлением обмена.
     *
     * @return BelongsToMany
     */
    public function groupCommissions(): BelongsToMany
    {
        return $this->belongsToMany(
            GroupCommission::class,
            'group_commission_direction_exchange',
            'direction_exchange_id',
            'group_commission_id'
        );
    }

    public function groups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\DirectionExchangeGroup::class,
            'direction_exchange_group_pivot',
            'direction_exchange_id',
            'group_id'
        )->withTimestamps();
    }

    public function profitProfile()
    {
        return $this->belongsTo(ProfitProfile::class, 'profit_profile_id');
    }
}
