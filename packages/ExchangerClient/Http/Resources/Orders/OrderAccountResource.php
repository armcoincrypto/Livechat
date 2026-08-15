<?php

namespace iEXPackages\ExchangerClient\Http\Resources\Orders;

use App\Models\Currency;
use App\Models\DirectionTemplate;
use App\Models\LinksReview;
use App\Models\OrderStep;
use iEXPackages\DynamicConfig\ValueObjects\Scope;
use App\Models\Review;
use App\Models\TaskInfo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class OrderAccountResource extends JsonResource
{
    /**
     * The "data" wrapper that should be applied.
     *
     * @var string
     */
    public static $wrap = 'data';

    /**
     * Название статусов в тест формате
     *
     * @var array
     */
    protected array $statusText = [
        2 => 'pending',
        3 => 'process',
        4 => 'success',
        7 => 'pay',
        8 => 'frozen',
        9 => 'merchant',
        12 => 'check_merchant',
        13 => 'waiting_merchant',
        15 => 'pending_pay'
    ];


    protected function loadCurrencies(): Collection
    {
        $ids = [
            $this->direction_exchange->id_currency1,
            $this->direction_exchange->id_currency2
        ];

        return Cache::remember($this->id . '_currencies_'.implode('_', $ids) . '_' . app()->getLocale(), now()->addMinutes(5), function () use ($ids) {
            return Currency::with([
                'payment:id,name,logo',
                'payment.explorer:id,id_payment,link',
                'code_currency:id,name',
                'reserve:id,id_currency,summa'
            ])->whereIn('id', $ids)->get()->keyBy('id');
        });
    }

    public function toArray(Request $request): array
    {
        $isSameIp = $this->ip === $request->ip();
        $maskType = (int)iEXSetting('type_wallet_display_for_user');

        $public_id = $this->public_id;

        // Запрос на получение валют
        $currency = $this->loadCurrencies();

        $task_info = TaskInfo::select('id', 'id_task', 'is_aml_analysis', 'is_blocked_chat')->where('id_task', $this->id)->first();

        // Информация по валюте (Отдаю)
        $in_currency = $currency[$this->direction_exchange->id_currency1];
        // Информация по валюте (Получаю)
        $out_currency = $currency[$this->direction_exchange->id_currency2];
        // Получаем статус заявки
        $status = $this->statusText[$this->status] ?? 'cancel';

        $in_icon_url = '/storage/payment_systems/'.$in_currency['payment']['logo'];
        $out_icon_url = '/storage/payment_systems/'.$out_currency['payment']['logo'];

        // Если IP адреса отличаются, то скрываем счет
        $inShotValue = $this->from_shot;
        $outShotValue = $this->to_shot;

        if (!$isSameIp) {
            $maskType = (int)iEXSetting('type_wallet_display_for_user');

            if (!empty($inShotValue)) {
                $inShotValue = maskAccountNumber($inShotValue, $maskType);
            }

            if (!empty($outShotValue)) {
                $outShotValue = maskAccountNumber($outShotValue, $maskType);
            }
        }

        // Единый флаг для верификации личности: учитываем и происхождение заявки, и факт пройденной идентификации
        $identityVerified = isset($this->user) && (int) ($this->user->is_verify_account ?? 0) === 1;
        $identityFromFlag = (int) ($this->is_from_identity_verification ?? 0);

        // Если пользователь уже верифицирован, считаем, что заявка не "из KYC" для фронта
        // Если не верифицирован, но по заявке требуется KYC или она помечена флагом is_from_identity_verification,
        // выставляем 1, иначе 0
        if (! $identityVerified && ($identityFromFlag === 1)) {
            $isFromIdentityVerificationUnified = 1;
        } else {
            $isFromIdentityVerificationUnified = 0;
        }

        $response = [
            'id' => (string) iEXSetting('client_id_type_for_order') == 1 ? $public_id : $this->id,
            'order_id' => $this->id,
            'type' => 'order',
            'attributes' => [
                'public_id' => $public_id,
                'created_at' => $this->created_at->translatedFormat('d M Y, H:i'),
                'created_date' => $this->created_at->translatedFormat('d M Y'),
                'course_display' => $this->course_display,
                'income_account' => $inShotValue,
                'outcome_account' => $outShotValue,
                'is_verification_card' => $this->is_from_verification_card,
                'is_from_identity_verification' => $isFromIdentityVerificationUnified,
                'recipient_account' => [
                    'name' => $in_currency['account_number_field'],
                    'value' => $this->transfer_to_account,
                ],

                'field_name' => [
                    'from' => $in_currency['field_name_from'],
                    'to' => $out_currency['field_name_to']
                ],

                'income_amount' => [
                    'amount' => (float) $this->give_price,
                    'currency' => $in_currency['code_currency']['name'],
                    'name' => $in_currency['payment']['name'],
                    'image' => $in_icon_url,
                    'number_format' => $in_currency['number_format'],
                ],
                'outcome_amount' => [
                    'amount' => (float) $this->receiving_price,
                    'currency' => $out_currency['code_currency']['name'],
                    'name' => $out_currency['payment']['name'],
                    'image' => $out_icon_url,
                    'number_format' => $out_currency['number_format'],
                ],
                'status' => $status,
                'status_int' => $this->status,
                'status_string' => $this->task_status->name,
            ],
        ];

        // Получаем шаблоны направлений
        $templates_directions = DirectionTemplate::all();

        // Текст для статуса "Заявка выполнена"
        $templates_status_order_success = $templates_directions->first(function ($item) {
            return $item->id_type == 5;
        });

        // Текст для статуса "Заявка отклонена"
        $templates_status_order_failed = $templates_directions->first(function ($item) {
            return $item->id_type == 6;
        });

        // Текст для статуса "Заявка выполнена"
        $status_success_direction = $this->direction_exchange->text_order_success;
        if (! empty($templates_status_order_success)) {
            if ($templates_status_order_success->type_view_info == 1) {
                $status_success_direction = $templates_status_order_success->text;
            } elseif ($templates_status_order_success->type_view_info == 2 and empty($this->direction_exchange->text_order_success)) {
                $status_success_direction = $templates_status_order_success->text;
            }
        }

        // Текст для статуса "Заявка отклонена"
        $status_failed_direction = $this->direction_exchange->text_order_failed;
        if (! empty($templates_status_order_failed)) {
            if ($templates_status_order_failed->type_view_info == 1) {
                $status_failed_direction = $templates_status_order_failed->text;
            } elseif ($templates_status_order_failed->type_view_info == 2 and empty($this->direction_exchange->text_order_failed)) {
                $status_failed_direction = $templates_status_order_failed->text;
            }
        }


        // Формирование ссылки на blockchain explorer
        $blockchain_link = null;

        if (
            !empty($in_currency['payment']['explorer']['link']) &&
            !empty($this->merchant_transaction_hash?->transaction_hash)
        ) {
            $blockchain_link = str_replace(
                '{hash}',
                $this->merchant_transaction_hash->transaction_hash,
                $in_currency['payment']['explorer']['link']
            );
        }

        // Подготовка статусов текстового отображения
        $response['attributes']['textStatus'] = [
            'success' => $status_success_direction,
            'failed'  => $status_failed_direction,
        ];

        // Логика проверки входящей транзакции вынесена в переменную
        $isTransactionChecking = $this->id_merchant > 0
            && !empty($this->transfer_to_account)
            && $status !== 'cancel';


        // Подготовка атрибутов входящей транзакции
        $response['attributes']['income_transaction'] = [
            'checking'        => $isTransactionChecking,
            'is_found_tx'     => (bool)$this->register_tx,
            'payment_status'  => $this->meta?->payment_status ?? 0,
            'logs'            => $this->checkPaymentStatusLog
                ? $this->checkPaymentStatusLog->pluck('new_status')->all()
                : [],
            'blockchain_link' => $blockchain_link,
            'hash_tx'         => $this->merchant_transaction_hash?->transaction_hash ?? null,
        ];

        // Если транзакция завершена и есть данные о подтверждениях
        if ($this->id_merchant > 0 && $this->status === 3 && $this->task_single_log_confirm) {
            $response['attributes']['income_transaction']['confirm_data'] = [
                'received_confirm' => $this->task_single_log_confirm->received_confirm,
                'needed_confirm'   => $this->task_single_log_confirm->needed_confirm,
            ];
        }

        // Автоматическое получение Transaction Hash
        if(isset($this->pay_transaction_data) and isset($this->pay_transaction_data->ext_data['transaction_hash']))
        {
            if(isset($out_currency['payment']['explorer']) and ! empty(isset($out_currency['payment']['explorer'])))
            {
                $response['attributes']['outcome_transaction'] = [
                    'blockchain_link' => str_replace('{hash}', $this->pay_transaction_data->ext_data['transaction_hash'], $out_currency['payment']['explorer']['link'])
                ];
            } else {
                $response['attributes']['outcome_transaction'] = [
                    'hash_tx' =>  $this->pay_transaction_data->ext_data['transaction_hash']
                ];
            }
        }


        if (isset($this->tasks_rejection_status)) {
            $response['attributes']['rejection'] = $this->tasks_rejection_status->name;
        }

        if (isset($this->pending_order_status) and $this->status == 8) {
            $response['attributes']['frozen_pending'] = $this->pending_order_status->name;
        }

        if ($this->status == 4 and ! empty(iEXContentLanguage('s_order_notify_text'))) {
            $response['attributes']['success_message'] = iEXContentLanguage('s_order_notify_text');
        }

        // Дополнительные поля, переданные оператором при успешном завершении заявки (opt_params.fields)
        if ($this->status == 4 && !empty($this->opt_params['fields'] ?? null)) {
            $response['attributes']['extra_fields'] = collect($this->opt_params['fields'])
                ->take(5)
                ->map(function ($item) {
                    return [
                        'name'  => (string) ($item['name'] ?? ''),
                        'value' => (string) ($item['value'] ?? ''),
                    ];
                })
                ->filter(fn (array $item) => $item['name'] !== '' && $item['value'] !== '')
                ->values()
                ->toArray();
        }

        // Комментарий для клиента
        if ($this->task_comment_user->count() > 0) {
            $response['attributes']['comment'] = $this->task_comment_user->pluck('message');
        }

        // Фото для прикрепления
        if ($this->task_file->count() > 0) {
            $response['attributes']['files'] = $this->task_file->map(function ($item) {
                return [
                    'value' =>  '/storage/orders/' . $item->file,
                    'content' => $item->text ?? null,
                ];
            });
        }

        // Проверка AML
        $aml_status = 0;
        if (isset($task_info->is_aml_analysis) and (int) $task_info->is_aml_analysis == 1) {
            if (is_numeric($task_info->aml_result_value)) {
                $aml_result_value = $task_info->aml_result_value.'%';
                if ($task_info->aml_result_value > 0 and $task_info->aml_result_value <= 30) {
                    $aml_status = 1;
                } elseif ($task_info->aml_result_value >= 31 and $task_info->aml_result_value <= 69) {
                    $aml_status = 2;
                } elseif ($task_info->aml_result_value >= 70 and $task_info->aml_result_value <= 100) {
                    $aml_status = 3;
                }
            } else {
                $aml_result_value = $task_info->aml_result_value;

                if ($task_info->aml_result_value == 'low') {
                    $aml_status = 1;
                } elseif ($task_info->aml_result_value == 'medium') {
                    $aml_status = 2;
                } elseif ($task_info->aml_result_value) {
                    $aml_status = 3;
                }
            }
        }

        $response['attributes']['aml_check_tx'] = [
            'status' => (int) (isset($task_info->is_aml_analysis) and $task_info->is_aml_analysis == 1),
            'result' => $aml_result_value ?? 0,
            'type' => $aml_status,
        ];

        // Онлайн чат + AI (настройки AI живут в scope ai:1)
        $aiScope = Scope::fromString('ai', 1);

        $response['attributes']['online_chat'] = [
            // чат в заявках (глобальная настройка)
            'status' => (int) iEXSetting('is_enable_order_online_chat'),

            // чат заблокирован по заявке (TaskInfo)
            'is_blocked' => (int) ($task_info->is_blocked_chat ?? 0),

            // AI включён глобально для онлайн-чата (DynamicConfig scope ai:1)
            'ai_enabled' => (int) iEXSetting('ai_online_chat_enabled', 2, scope: $aiScope, schemaProfile: 'ai'),

            // handoff: чат передан оператору (TaskMeta.chat_handoff_to_human)
            'handoff_to_human' => (int) ((bool) ($this->meta?->chat_handoff_to_human ?? false)),

            // подсказка для UI (пометка, что функционал AI в чате в beta)
            'ai_beta' => 1,
        ];

        $response['attributes']['user'] = [];
        if (auth()->check() and auth()->id() == $this->id_user and isset($this->user)) {
            $response['attributes']['user'] = [
                'id' => $this->id_user,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ];
        }

        // Получаем список этапов
        if ($in_currency['is_enabled_step_order'] == 1) {
            $order_steps = OrderStep::where('status', '=', 1)->orderBy('sorting')->get()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name
                ];
            });
            $response['attributes']['steps'] = [
                'list' => $order_steps->toArray(),
                'active' => $this->id_order_step,
            ];
        }

        $response['attributes']['fields'] = [
            'in' => isset($this->tasks_fields_in) ? $this->tasks_fields_in->map(function ($item) use ($isSameIp, $maskType) {
                return [
                    'name' => $item->field_name,
                    'value' => (!$isSameIp && !empty($item->field_value))
                        ? maskAccountNumber($item->field_value, $maskType)
                        : $item->field_value,
                ];
            }) : [],
            'out' => isset($this->tasks_fields_out) ? $this->tasks_fields_out->map(function ($item) use ($isSameIp, $maskType) {
                return [
                    'name' => $item->field_name,
                    'value' => (!$isSameIp && !empty($item->field_value))
                        ? maskAccountNumber($item->field_value, $maskType)
                        : $item->field_value,
                ];
            }) : [],
            'directions' => isset($this->tasks_fields_direction) ? $this->tasks_fields_direction->map(function ($item) use ($isSameIp, $maskType) {
                return [
                    'name' => $item->field_name,
                    'value' => (!$isSameIp && !empty($item->field_value))
                        ? maskAccountNumber($item->field_value, $maskType)
                        : $item->field_value,
                ];
            }) : [],
        ];


        if(!empty($this->task_info->city_name)) {
            $response['attributes']['city'] = [
                'name' => $this->task_info->city_name,
                'country' => $this->task_info->country_name,
            ];
        }

        // Проверяем, оставлялся ли отзыв по заявке
        $is_review = Review::where('id_task', $this->id)->exists();
        $response['attributes']['is_review'] = (int) $is_review;

        // Описание для отзывов
        $response['attributes']['view_reviews'] = [
            'title' => (string)iEXContentLanguage('reviews_block_title'),
            'text' => (string)iEXContentLanguage('reviews_block_description'),
        ];


        // Ссылки на отзывы
        $linksReviews = LinksReview::whereNotNull('icon')->where('is_show', '=', 1)->orderBy('sorting')->get()->map(function ($item) {
            return [
                'link' => $item->url,
                'icon' =>  '/storage/links/'.$item->icon,
            ];
        });
        $response['attributes']['link_reviews'] = $linksReviews;

        return $response;
    }
}
