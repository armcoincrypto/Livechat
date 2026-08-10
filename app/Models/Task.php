<?php
namespace App\Models;

use App\Observers\Task\TaskObserver;
use EloquentFilter\Filterable;
use iEXPackages\ReferralSystem\Models\ReferralLink;
use iEXPackages\ReferralSystem\Models\ReferralLog;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

#[ObservedBy([TaskObserver::class])]
class Task extends Model
{
    use Filterable, Notifiable, SoftDeletes;

    protected $table = 'tasks';

    protected $casts = [
        'next_checkout_at' => 'datetime',
        'started_at'       => 'datetime',
        'archived_at'      => 'datetime',
        'view_expires_at'  => 'datetime',
        'completed_at'     => 'datetime',
    ];

    protected $fillable = [
        // базовые
        'type',
        'type_rate',
        'is_type_rate',
        'floating_recount_stop',
        'fix_recount_stop',
        'type_requisite',

        // пользователь / связь
        'email',
        'phone',
        'id_user',
        'telegram_id',
        'id_referral_link',
        'referral_hash',

        // направление и реквизиты
        'id_direction_exchange',
        'id_direction_requisites',
        'id_payment_requisites',
        'id_payment_gateway',
        'id_merchant',
        'id_pay',
        'id_wallets_addresses',
        'payment_address',
        'requisites_receive',
        'requisites_description',

        // суммы (исходные)
        'give_price',
        'receiving_price',

        // описание и служебные
        'description',
        'description_receiving',
        'description_give',
        'ip',

        // статусы и флаги
        'status',
        'passed',
        'start',
        'is_archive',
        'is_favorites',
        'is_spam',
        'is_bot',
        'is_frozen',
        'is_ban_order_data',
        'is_run_process',

        // статусы (связанные)
        'id_rejection_status',
        'id_pending_status',

        // платёжка
        'merchant_provider',
        'merchant_status',
        'merchant_incomplete_payment',
        'merchant_overpayment',
        'is_auto_check_pay',
        'is_status_pay_callback',
        'status_pay_api',
        'is_status_pay_email',
        'is_attempts_pay',
        'is_check_payment_merchant',

        // курсы
        'course_display',
        'course_float',
        'course_float_fixed',
        'course_display_fixed',

        // комиссии / маркапы
        'discount1',
        'discount2',
        'markup1',
        'markup2',
        'in_price_fee',
        'out_price_fee',

        // расчётные значения
        'give_price_default',
        'give_price_with_comm',
        'give_price_with_comm_pay',
        'give_price_fee_comm',
        'give_price_fee_pay',
        'give_price_reserve',

        'receiving_price_default',
        'receiving_price_with_comm',
        'receiving_price_with_comm_pay',
        'receiving_price_fee_comm',
        'receiving_price_fee_pay',
        'receiving_price_reserve',
        'receiving_price_discount',
        'receiving_price_discount_fee',
        'receiving_price_user_discount',
        'receiving_price_with_user_discount',
        'user_discount',

        // промокод (новая система)
        'id_promo_code',
        'promo_code_code',
        'promo_code_discount_type',       // percent | fixed
        'promo_code_value',               // discount_value
        'receiving_price_with_promocode', // legacy название

        // прочее
        'method_request_payment',
        'queue_status',
        'public_id',
        'unique_security_code',
        'kunacode',

        // даты
        'next_checkout_at',
        'view_expires_at',
        'started_at',
        'completed_at',
        'archived_at',

        // json
        'opt_params',

        // админские
        'id_order_step',
        'id_edit_data_manager',
        'id_who_completed',

        // флаги резерва
        'is_reserve_in_used',
        'is_reserve_out_used',

        // legacy / совместимость
        'is_autopay_limit',
        'is_autopay_modal',
        'is_autopay_off',
        'double_withdrawal',
        'is_new_user',

        'from_shot',
        'to_shot',
        'payment_field',

        'type_finished_order',
        'rejection_reason',
        'message_success',
        'check_status',
        'category_reject',

        'income_code',
        'check_income_code',
        'register_tx',
        'in_flow_funds',
        'is_drain_merchant',
        'in_amount_merchant',
        'out_amount_pay',
        'transfer_to_account',
        'transfer_to_account_type',
        'pay_num',

        'is_send_mail_create',
        'is_request_payment_type',
        'is_wallet_issued',
        'is_file_check',
        'is_wait_callback',
        'is_from_verification_card',
        'is_from_identity_verification',
        'is_pay_referral_bonus',
    ];

    /**
     * Атрибуты, которые должны быть приведены к нативным типам.
     *
     * @return string[]
     */
    protected function casts()
    {
        return [
            'opt_params' => 'array',
        ];
    }

    public function scopeLimitOutput($query)
    {
        return $query->where('status', '!=', 2);
    }

    /**
     * Установка нижнего регистра для почты
     *
     * @param $value
     * @return void
     */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = strtolower($value);
    }

    /**
     * Получение почты
     *
     * @param $value
     * @return string
     */
    public function getEmailAttribute($value): string
    {
        return strtolower($value);
    }

    public function extraOuts(): HasMany
    {
        return $this->hasMany(TaskExtraOut::class, 'id_task');
    }

    public function meta(): HasOne
    {
        return $this->hasOne(TaskMeta::class);
    }

    public function checkPaymentStatusLog()
    {
        return $this->hasMany(TaskCheckPaymentStatusLog::class);
    }

    public function tasks_check_images(): HasOne
    {
        return $this->hasOne(TasksCheckImage::class, 'id_order', 'id');
    }

    public function task_messages()
    {
        return $this->hasMany(TaskMessage::class, 'id_task', 'id');
    }

    public function modelFilter()
    {
        return $this->provideFilter(\App\Models\Filters\OrderFilter::class);
    }

    public function promo_code(): HasOne
    {
        return $this->hasOne(PromoCode::class, 'id', 'id_promo_code');
    }

    public function task_status()
    {
        return $this->hasOne(TaskStatus::class, 'id', 'status');
    }

    public function task_info()
    {
        return $this->hasOne(TaskInfo::class, 'id_task', 'id');
    }

    public function history_recalculation()
    {
        return $this->hasMany(HistoryRecalculation::class, 'id_task', 'id');
    }

    public function tasks_chat()
    {
        return $this->hasMany(TaskChat::class, 'id_task', 'id');
    }

    public function direction_exchange()
    {
        // HISTORICAL_WITH_TRASHED: orders outlive retired directions.
        // Soft-deleted (archived) directions must still resolve for admin/detail.
        return $this->hasOne(DirectionExchange::class, 'id', 'id_direction_exchange')->withTrashed();
    }

    public function direction_exchange_many()
    {
        return $this->hasMany(DirectionExchange::class, 'id', 'id_direction_exchange')
            ->groupBy('id_direction_exchange');
    }

    public function de_no_group()
    {
        return $this->hasMany(DirectionExchange::class, 'id', 'id_direction_exchange');
    }

    /**
     * Менеджер/пользователь, который завершил заявку.
     *
     * Данные берутся из поля id_who_completed.
     */
    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_who_completed', 'id');
    }


    public function user()
    {
        return $this->hasOne(User::class, 'id', 'id_user');
    }

    public function payment_requisites()
    {
        return $this->hasOne(Requisites::class, 'id', 'id_payment_requisites');
    }

    public function direction_requisites(): HasOne
    {
        return $this->hasOne(DirectionRequisite::class, 'id', 'id_direction_requisites');
    }

    public function wallets_addresses()
    {
        return $this->hasOne(WalletsAddresses::class, 'id', 'id_wallets_addresses');
    }

    public function wallets_history()
    {
        return $this->hasOne(WalletsHistory::class, 'id_task', 'id');
    }

    public function tasks_convert_log_many()
    {
        return $this->hasMany(Task::class, 'id_task', 'id');
    }

    public function history_payment_transaction()
    {
        return $this->hasOne(HistoryPaymentTransaction::class, 'id_task', 'id');
    }

    public function history_code()
    {
        return $this->hasOne(HistoryCode::class, 'id_task', 'id');
    }

    public function tasks_rejection_status()
    {
        return $this->hasOne(TaskRejectionStatus::class, 'id', 'id_rejection_status');
    }

    public function pending_order_status()
    {
        return $this->hasOne(PendingOrderStatus::class, 'id', 'id_pending_status');
    }

    public function wallet_transaction()
    {
        return $this->hasOne(WalletTransaction::class, 'id_task', 'id');
    }

    public function wallet_history()
    {
        return $this->hasOne(WalletsHistory::class, 'id_task', 'id');
    }

    public function tasks_rates_data()
    {
        return $this->hasOne(TasksRatesData::class, 'id_task', 'id');
    }

    /**
     * Информация о полях валюты отдаю
     */
    public function tasks_fields_currency_in(): HasMany
    {
        return $this->hasMany(TaskField::class, 'id_task', 'id')
            ->where('alias', '=', 'currency')->where('type_field', '=', 'in');
    }

    /**
     * Информация о полях валюты отдаю
     */
    public function tasks_fields_currency_out(): HasMany
    {
        return $this->hasMany(TaskField::class, 'id_task', 'id')
            ->where('alias', '=', 'currency')->where('type_field', '=', 'out');
    }

    /**
     * Получаем информацию о полях (Платежных реквизитов и направлений)
     */
    public function tasks_fields_base(): HasMany
    {
        return $this->hasMany(TaskField::class, 'id_task', 'id')->whereIn('alias', ['direction', 'requisites']);
    }


    /** @deprecated  */
    public function tasks_fields_in_out(): HasMany
    {
        return $this->hasMany(TaskField::class, 'id_task', 'id')
            ->where('alias', '=', 'currency')->whereIn('type_field', ['in', 'out']);
    }

    public function tasks_fields_in(): HasMany
    {
        return $this->hasMany(TaskField::class, 'id_task', 'id')
            ->where('alias', '=', 'currency')->where('type_field', 'in');
    }

    public function tasks_fields_out(): HasMany
    {
        return $this->hasMany(TaskField::class, 'id_task', 'id')
            ->where('alias', '=', 'currency')->where('type_field', 'out');
    }


    public function tasks_fields_direction(): HasMany
    {
        return $this->hasMany(TaskField::class, 'id_task', 'id')
            ->where('alias', '=', 'direction');
    }

    /**
     * Реферальный лог по заявке
     *
     * @return HasOne
     */
    public function referral_log()
    {
        return $this->hasOne(ReferralLog::class, 'id_task', 'id');
    }

    public function referral_link(): HasOne
    {
        return $this->hasOne(ReferralLink::class, 'id', 'id_referral_link');
    }

    public function verification_card_handler()
    {
        return $this->hasOne(VerificationCard::class, 'id_order', 'id')->where('status', 0);
    }

    public function verification_card_active_email()
    {
        return $this->hasMany(VerificationCard::class, 'email', 'email')->where('status', '=', 1);
    }

    public function verification_card_active()
    {
        return $this->hasOne(VerificationCard::class, 'id_order', 'id')->whereNotIn('status', [2, 3]);
    }

    public function tasks_card_detail_in()
    {
        return $this->hasOne(TaskCardDetail::class, 'id_task', 'id')->where('type_column', 'in');
    }

    public function tasks_card_detail_out()
    {
        return $this->hasOne(TaskCardDetail::class, 'id_task', 'id')->where('type_column', 'out');
    }

    public function edit_data_manager()
    {
        return $this->hasOne(User::class, 'id', 'id_edit_data_manager');
    }

    public function history_operators()
    {
        return $this->hasMany(TaskHistoryOperator::class, 'id_task', 'id');
    }

    public function task_comment()
    {
        return $this->hasMany(TaskComment::class, 'id_task', 'id');
    }

    public function task_comment_user()
    {
        return $this->hasMany(TaskCommentUser::class, 'id_task', 'id');
    }

    public function task_file()
    {
        return $this->hasMany(TaskFile::class, 'id_task', 'id')->orderByDesc('id');
    }

    /**
     * Получаем информацию о мерчанте
     */
    public function merchant(): HasOne
    {
        return $this->hasOne(GatewayMerchant::class, 'id', 'id_merchant');
    }

    public function task_single_log_confirm()
    {
        return $this->hasOne(TaskSingleLogConfirm::class, 'id_task', 'id');
    }

    public function merchant_transaction_hash()
    {
        return $this->hasOne(MerchantTransactionHash::class, 'id_task', 'id');
    }

    /**
     * @deprecated
    */
    public function pay_transaction_hash()
    {
        return $this->hasOne(PayTransactionHash::class, 'id_task', 'id');
    }

    /**
     * Данные для выплаты
     *
     * @return HasOne
     */
    public function pay_transaction_data(): HasOne
    {
        return $this->hasOne(PayTransactionData::class, 'id_task', 'id');
    }

    public function steps_logs()
    {
        return $this->hasMany(ApplicationStepLog::class, 'id_task', 'id');
    }

    public function aml_response_data_address(): HasOne
    {
        return $this->hasOne(AMLResponseData::class, 'id_task', 'id')->where('method', '=', 'address');
    }

    public function aml_response_data_tx(): HasOne
    {
        return $this->hasOne(AMLResponseData::class, 'id_task', 'id')->where('method', '=', 'tx');
    }



    public function task_operators()
    {
        return $this->hasMany(TaskOperator::class, 'id_task', 'id');
    }

    public function task_requisite_attached(): HasOne
    {
        return $this->hasOne(TaskRequisiteAttached::class, 'id_task', 'id');
    }


    public function profitResult()
    {
        return $this->hasOne(\App\Models\OrderProfitResult::class, 'task_id');
    }

    public function merchantTransactionData()
    {
        return $this->hasOne(MerchantTransactionData::class, 'id_task', 'id');
    }

    public function tasks_fields_user_order(): HasMany
    {
        return $this->hasMany(TaskField::class, 'id_task', 'id')
            ->where('alias', 'user_order')
            ->orderBy('id');
    }
}
