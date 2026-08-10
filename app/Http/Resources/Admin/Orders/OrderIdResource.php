<?php

namespace App\Http\Resources\Admin\Orders;

use App\Enums\TaskStatusEnum;
use App\Models\OrderStep;
use App\Presenters\SelectedFeesPresenter;
use iEXPackages\ReferralSystem\ReferralSystemFacade;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class OrderIdResource extends JsonResource
{
    public static $wrap = 'attributes';

    public function toArray($request)
    {
        // Время обработки заявки
        $start_time = Carbon::now();
        $duration = Carbon::parse($this->resource['detail']->created_at)->addSeconds((int) iEXSetting('max_time_task'));
        $lead_time = 0;
        if ($duration->gt($start_time)) {
            $lead_time = $start_time->diffInMilliseconds($duration);
        }


        // Индивидуальный конструктор (тип 2) — комиссия берётся из JSON card_verification_rules
        $meta        = $this->resource['detail']->meta;
        $direction   = $this->resource['detail']->direction_exchange;
        $cardType    = (int) ($meta?->card_verification_type  ?? 0);
        $isRequired  = (bool) ($meta?->card_verification_required ?? false);

        if ($cardType === 2 && $direction?->card_verification_rules) {
            $rules = $direction->card_verification_rules ?? [];
            $noVerification = $rules['no_verification'] ?? [];
            $withVerification = $rules['with_verification'] ?? [];

            $verification_commission = $isRequired
                ? ($withVerification['fee'] ?? null)
                : ($noVerification['fee'] ?? null);

        }
        $sfSnapshot = is_array($this->resource['detail']->meta?->selected_fees) ? $this->resource['detail']->meta?->selected_fees : [];
        $sfFlat     = SelectedFeesPresenter::flatten($sfSnapshot);

        return [
            'status' => $this->resource['detail']->status,
            'status_name' => $this->resource['detail']->task_status?->name,
            'type_rate' => $this->resource['detail']->type_rate,
            'is_type_rate' => $this->resource['detail']->is_type_rate,

            'in_currency' => [
                'name' => $direction ? currency_in($this->resource['detail'], true) : '—',
                'code_name' => $direction?->currency1?->code_currency?->name ?? '',
                'image' => (($__inImgLogo = $direction?->currency1?->payment?->logo ?? '') !== '')
                    ? '/storage/payment_systems/'.$__inImgLogo
                    : '',
                'explorer' => (($__inExplorerLink = $direction?->currency1?->payment?->explorer?->link) !== null && $__inExplorerLink !== '')
                    ? ['link' => $__inExplorerLink]
                    : [],
                'amounts' => [
                    'price_default' => (float)$this->resource['detail']->give_price_default,
                    'price_fee_comm' => (float)$this->resource['detail']->give_price_fee_comm,
                    'price_with_comm' => (float)$this->resource['detail']->give_price_with_comm,
                    'price_fee_pay' => (float)$this->resource['detail']->give_price_fee_pay,
                    'price_with_comm_pay' => (float)$this->resource['detail']->give_price_with_comm_pay,
                ],

                'merchant_data' => [
                    'amount' => (float)$this->resource['detail']->in_amount_merchant,
                    'is_invalid' => ((float)$this->resource['detail']->in_amount_merchant < (float)$this->resource['detail']->give_price),
                    'is_recount' => in_array($this->resource['detail']->status, [3, 7, 12]) and auth()->user()->can('admin_orders_id_recount') and
                        ((float)$this->resource['detail']->in_amount_merchant < (float)$this->resource['detail']->give_price),
                    'alias' => isset($this->resource['detail']->merchant) ? $this->resource['detail']->merchant->alias : '',
                    'different' => getAmountCreditMerchantFromOrder($this->resource['detail'])
                ],

                'card_details' => $this->resource['cardInfoIn'],
                'fields' => get_order_fields_tx($this->resource['detail'], 'currency_in'),
                'wallet_info_in' => $this->resource['walletInfo'],
                'is_enabled_check_pay' => !$this->resource['isPreview'] and
                    empty($this->resource['walletInfo']) and
                    in_array($this->resource['detail']->status, [3, 8, 12, 13]) and $this->resource['detail']->is_check_payment_merchant == 1,
            ],

            'out_currency' => [
                'name' => $direction ? currency_out($this->resource['detail'], true) : '—',
                'code_name' => $direction?->currency2?->code_currency?->name ?? '',
                'image' => (($__outImgLogo = $direction?->currency2?->payment?->logo ?? '') !== '')
                    ? '/storage/payment_systems/'.$__outImgLogo
                    : '',
                'amounts' => [
                    'price_default' => (float)$this->resource['detail']->receiving_price_default,
                    'price_fee_comm' => (float)$this->resource['detail']->receiving_price_fee_comm,
                    'price_with_comm' => (float)$this->resource['detail']->receiving_price_with_comm,
                    'price_fee_pay' => (float)$this->resource['detail']->receiving_price_fee_pay,
                    'price_with_comm_pay' => (float)$this->resource['detail']->receiving_price_with_comm_pay,
                ],
                'options_amount' => [
                    'new_amount' => (float)$this->resource['percentDiff'],
                    'new_amount_diff' => (float)$this->resource['newOutAmount'],
                ],

                'explorer' => (($__outExplorerLink = $direction?->currency2?->payment?->explorer?->link) !== null && $__outExplorerLink !== '')
                    ? ['link' => $__outExplorerLink]
                    : [],
                'card_details' => $this->resource['cardInfoOut'],
                'fields' => get_order_fields_tx($this->resource['detail'], 'currency_out'),
                'status_pay_api' => $this->resource['detail']->status_pay_api,
            ],

            'aml_data' => [
                'address' => $this->resource['detail']->aml_response_data_address ?? [],
                'tx'  => $this->resource['detail']->aml_response_data_tx ?? []
            ],

            'merchant_provider' => $this->resource['detail']->merchant_provider ?? '',
            'pay_hash' => isset($this->resource['detail']->pay_transaction_data) ? new OrderPayTransactionResource($this->resource['detail']) : [],
            'merchant_hash' => isset($this->resource['detail']->merchant_transaction_hash) ? new OrderMerchantTransactionResource($this->resource['detail']) : [],
            'task_comment_count' => $this->resource['detail']->task_comment->count() ?? 0,
            'task_comment_user_count' => $this->resource['detail']->task_comment_user->count() ?? 0,
            'referral_link' => (isset($this->resource['detail']->referral_link)) ? new OrderIdReferralLinkResource($this->resource['detail']) : [],

            'referral_preview' => (function () {
                /** @var \App\Models\Task|null $task */
                $task = $this->resource['detail'] ?? null;
                if (!$task) {
                    return null;
                }

                // legacy: 0 = enabled, 1 = disabled
                if ((int) iEXSetting('enabled_referral_system') === 0) {
                    return null;
                }

                $status = (int) ($task->status ?? 0);

                // только на статусах WAITING_HANDLE (3) и PAID (7)
                if (!in_array($status, [
                    TaskStatusEnum::WAITING_HANDLE->value,
                    TaskStatusEnum::PAID->value,
                ], true)) {
                    return null;
                }

                if (empty($task->referral_hash)) {
                    return null;
                }

                try {
                    $preview = ReferralSystemFacade::previewForTask($task);

                    // DTO -> array (публичные свойства)
                    return is_object($preview) ? get_object_vars($preview) : null;
                } catch (\Throwable $e) {
                    return null;
                }
            })(),

            'promo_code' => [
                // Код промокода (снапшот)
                'code' => $this->resource['detail']->promo_code_code,

                // percent|fixed
                'type' => $this->resource['detail']->promo_code_discount_type,

                // discount_value (процент или фикс)
                'value' => $this->resource['detail']->promo_code_value,

                // BONUS (прибавка к "Получаю")
                'bonus' => $this->resource['detail']->receiving_price_with_promocode,
            ],
            'task_file_count' => $this->resource['detail']->task_file->count() ?? 0,
            'is_request_payment_type' => (int)$this->resource['detail']->is_request_payment_type,
            'task_requisite_attached' => $this->resource['detail']->task_requisite_attached ?? [],
            'is_ban_order_data' => $this->resource['detail']->is_ban_order_data,
            'type_finished_order' => $this->resource['detail']->type_finished_order,
            'queue_status' => $this->resource['detail']->queue_status,
            'tasks_rejection_status' => isset($this->resource['detail']->tasks_rejection_status) ? [
                'id' => $this->resource['detail']->tasks_rejection_status->id,
                'name' => $this->resource['detail']->tasks_rejection_status->name,
            ] : [],
            'pending_order_status' => isset($this->resource['detail']->pending_order_status) ? [
                'id' => $this->resource['detail']->pending_order_status->id,
                'name' => $this->resource['detail']->pending_order_status->name,
            ] : [],

            'from_shot' => $this->resource['detail']->from_shot,
            'from_shot_verify' => is_verified_order_card_collect($this->resource['detail']),
            'to_shot' => $this->resource['detail']->to_shot,
            'is_city_value' => !empty($this->resource['detail']->task_info->country_name) and !empty($this->resource['detail']->task_info->city_name),

            'city_data' => [
                'country' => $this->resource['detail']->task_info->country_name,
                'city' => $this->resource['detail']->task_info->city_name
            ],

            'radios_rejection' => $this->resource['reasonRejection'],
            'radios_defers' => $this->resource['pendingOrderStatus'],

            'course_display' => $this->resource['detail']->course_display,

            'is_pay_referral_bonus' => $this->resource['detail']->is_pay_referral_bonus,
            'is_from_verification_card' => $this->resource['detail']->is_from_verification_card,
            'verification_card_handler' => $this->resource['detail']->verification_card_handler,
            'is_preview' => $this->resource['isPreview'],
            'is_operator' => $this->resource['isOperator'],
            'newCourse' => $this->resource['newCourse'],


            'int_error_type' => $this->resource['detail']->int_error_type,

            'task_info' => [
                'num_transaction' => $this->resource['detail']->task_info->num_transaction ?? '',
                'note_tx' => $this->resource['detail']->task_info->note_tx ?? ''
            ],

            'is_bot' => $this->resource['detail']->is_bot,
            'id_who_completed' => $this->resource['detail']->id_who_completed ?? 0,

            'edit_data_manager' => (isset($this->resource['detail']->edit_data_manager)) ? [
                'id' => $this->resource['detail']->edit_data_manager->id,
                'name' => $this->resource['detail']->edit_data_manager->name,
                'email' => $this->resource['detail']->edit_data_manager->email
            ] : [],

            'direction_exchange' => [
                'id' => $this->resource['detail']->id_direction_exchange,
                'tech_name' => $direction?->tech_name ?? ('#'.$this->resource['detail']->id_direction_exchange.' (архив)'),
                'is_archived' => $direction !== null && method_exists($direction, 'trashed') && $direction->trashed(),
            ],

            'started_at' => $this->resource['detail']->started_at,
            'in_flow_funds' => $this->resource['detail']->in_flow_funds,
            'register_tx' => $this->resource['detail']->register_tx,
            'is_file_check' => (int)$this->resource['detail']->is_file_check,
            'attached_image' => isset($this->resource['detail']->tasks_check_images) ? [
                'private_folder' => get_image_from_private_path('order_check', $this->resource['detail']->tasks_check_images->image),
                'mimetype' => $this->resource['detail']->tasks_check_images->mimetype,
                'size' => $this->resource['detail']->tasks_check_images->size,
            ] : [],
            'created_at' => $this->resource['detail']->created_at->translatedFormat('d M Y H:i'),
            'created_at_human' => $this->resource['detail']->created_at->diffForHumans(),
            'updated_at' => $this->resource['detail']->updated_at->translatedFormat('d M Y H:i'),
            'updated_at_human' => $this->resource['detail']->updated_at->diffForHumans(),
            'recalculated_at' => (!is_null($this->resource['detail']->task_info->recalculated_at)) ? Carbon::parse($this->resource['detail']->task_info->recalculated_at)->translatedFormat('d M Y H:i') : '',
            'code_country' => $this->resource['detail']->task_info->code_country,
            'device' => $this->resource['detail']->task_info->device,
            'is_newbie' => $this->resource['detail']->task_info->newbie,

            'user' => $this->resource['detail']->user
                ? new OrderUserResource(
                    $this->resource['detail']->user,
                    telegramId: $this->resource['detail']->telegram_id,
                    ip: $this->resource['detail']->ip,
                )
                : null,

            'meta' => [
                'telegram_id' => $this->resource['detail']->meta?->telegram_id,
                'telegram_data' => $this->resource['detail']->meta?->telegram_data,

               'selected_fees' => $sfFlat,

                'checkbox_agreements' => $this->resource['detail']->meta?->checkbox_agreements ?? [],

                'card_verification_type' => $this->resource['detail']->meta?->card_verification_type,
                'card_verification_required' => $this->resource['detail']->meta?->card_verification_required,


                'freeze_scam' => $this->resource['detail']->meta?->freeze_scam ?? [],
            ],

            'verification_commission' => $verification_commission ?? null,

            'is_payout_api' => $this->resource['hasAPI'],
            'is_pin_code' => !empty(config('security-codes.order-confirm')),
            'user_discount' => $this->resource['detail']->user_discount,
            'user_discount_amount' => $this->resource['detail']->user_discount > 0 ? (float)$this->resource['detail']->receiving_price_user_discount : 0,
            'waiting_merchant_pay' => vueJSScheduleOrderBlockchain($this->resource['detail']),
            'deleted_at' => $this->resource['detail']->deleted_at,
            'finished_at' => ($this->resource['detail']->started_at != null and $this->resource['detail']->status == 4) ? Carbon::parse($this->resource['detail']->started_at)->diffForHumans(Carbon::parse($this->resource['detail']->updated_at), true) : '',

            'is_restore' => in_array($this->resource['detail']->status, config('iexexchanger.orders.restore_statuses')),
            'leadTime' => $lead_time,

            'order_steps' => Cache::remember('admin.orders.reference.order_steps_list_v1', 300, static function () {
                return OrderStep::pluck('name', 'id')->map(static function ($value, $id) {
                    return [
                        'id' => $id,
                        'value' => $value,
                    ];
                })->values();
            }),
            'id_order_step' => $this->resource['detail']->id_order_step,
            'steps_logs' => $this->resource['detail']->steps_logs,
        ];
    }
}
