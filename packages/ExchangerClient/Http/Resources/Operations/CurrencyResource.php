<?php
namespace iEXPackages\ExchangerClient\Http\Resources\Operations;

use App\Models\CurrencyTemplate;
use App\Models\ExtraOutProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CurrencyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray(Request $request)
    {
        $icon_url = '/storage/payment_systems/'.$this->payment->logo;

        // Метки валюты: раздельно для "Отдаю" (give) и "Получаю" (receive)
        $labelsIn  = $this->mapLabels($this->labelsIn ?? collect());
        $labelsOut = $this->mapLabels($this->labelsOut ?? collect());

        // Поля отдаю
        $incomeFields = $this->currency_in_fields->where('status', '=', 0)->sortBy('sorting');
        // Поля получаю
        $outcomeFields = $this->currency_out_fields->where('status', '=', 0)->sortBy('sorting_out') ?? [];

        $response =  [
            'id' => $this->id,
            'type' => 'payment_system',
            'attributes' => [
                'name' => ($this->visible_code_currency == 1) ? $this->payment->name.' '.$this->code_currency->name : $this->payment->name,
                'first_char' => $this->first_value ?? null,
                'tech_name' => $this->tech_currency_name,
                'payment_system' => $this->payment->name,
                'letter_cod' => $this->designation_xml,
                'short_code' => $this->small_code,
                'with_currency_code' => (int)$this->visible_code_currency,
                'currency_iso_code' => $this->code_currency->name,
                'is_iso_code' => $this->visible_code_currency,
                'default_value' => $this->convert_by,
                'income_enabled' => $this->visible_give,
                'outcome_enabled' => $this->visible_receiving,
                'valid_type_in' => $this->validation_account_from,
                'valid_type_out' => $this->validation_account_to,
                'sorting_reserve' => $this->sorting_reserve,
                'display_scan_qr_from' => $this->display_scan_qr_from,
                'display_scan_qr_to' => $this->display_scan_qr_to,
                'outcome_fee' => 0,

                'aml_text_in' => !empty($this->aml_text_in) ? $this->aml_text_in : null,
                'aml_text_out' => !empty($this->aml_text_out) ? $this->aml_text_out : null,

                'formalization_text' => !empty($this->formalization_text) ? $this->formalization_text : null,
                'amount_decimal' => $this->number_format,
                'income_notice' => !empty($this->notice_in) ? $this->notice_in : null,
                'outcome_notice' => !empty($this->notice_out) ? $this->notice_out : null,
                'icon_url' => $this->payment->logo,
                'icon_url_path' => $icon_url,
                'color' => (bool) iEXSetting('is_gradient_text_color') ?? '#' .$this->text_color,
                'filter_ids' => $this->filters instanceof \Illuminate\Support\Collection
                    ? $this->filters->pluck('id')->values()->all()
                    : [],
                'currency' => [
                    'symbol' => $this->code_currency->name,
                    'sorting' => $this->sorting_1,
                ],
                'order_form_fields' => [
                    'field_type' => 'string',
                    'label_in' => $this->field_name_from,
                    'label_out' => $this->field_name_to,
                ],

                'mask_in' => $this->mask_account_from,
                'mask_out' => $this->mask_account_to,

                'mask_placeholder_in' => $this->mask_placeholder_char_from,
                'mask_placeholder_out' => $this->mask_placeholder_char_to,

                'field_comment_in' => !empty($this->field_comment_from) ? $this->field_comment_from : null,
                'field_comment_out' => !empty($this->field_comment_to) ? $this->field_comment_to : null,
                'button_create_order' => !empty($this->button_create_order) ? $this->button_create_order : null,
                'button_create_order_text' => !empty($this->button_create_order_text) ? $this->button_create_order_text : null,
                'recount_course_text' => !empty($this->recount_course_text) ? $this->recount_course_text : null,

                'income_form_fields' => new CurrencyFieldsResources($incomeFields),
                'outcome_form_fields' => new CurrencyFieldsResources($outcomeFields),

                'commands' => isset($this->commands) and $this->commands->count() > 0 ?
                        $this->whenLoaded('commands', function(Collection $items) {
                            return $items->map(function($item) {
                                return [
                                    'label' => (! is_null($item->name) ? $item->name : ''),
                                    'value' => $item->amount,
                                ];
                            });
                        }) : [],
            ],
        ];

        if (!empty($labelsIn)) {
            $response['attributes']['labels_in'] = $labelsIn;
        }
        if (!empty($labelsOut)) {
            $response['attributes']['labels_out'] = $labelsOut;
        }

        $response['attributes']['extra_out'] = $this->makeExtraOutAttributes();


        if(isset($this->currency_group_network)) {
            $response['attributes']['network'] = [
                'id' => $this->currency_group_network->id,
                'title' => $this->currency_group_network->title,
                'display_type' => $this->currency_group_network->display_type ?? 0,
                'icon' => !empty($this->currency_group_network->icon) ? '/storage/currencies-icon/'.$this->currency_group_network->icon : '',
            ];
        }

        return $response;
    }

    /**
     * Формирует атрибуты extra_out для ответа по текущей валюте.
     * Без кэша. Безопасные дефолты. Возвращает ['enabled' => false] если профиля нет.
     */
    private function makeExtraOutAttributes(): array
    {
        $result = ['enabled' => false];

        try {
            $extraOut = ExtraOutProfile::query()
                ->enabled()
                ->forCurrency((int) $this->id)
                ->select([
                    'id',
                    'is_enabled',
                    'field_label',
                    'button_name',
                    'description',
                    'min_payout_amount',
                    'min_trigger_amount',
                    'max_fields',
                ])
                ->first();

            if ($extraOut) {
                $result = [
                    'enabled'            => (bool) $extraOut->is_enabled,
                    'profile_id'         => (int) $extraOut->id,
                    'field_label'        => $extraOut->field_label ?: null,
                    'button_name'        => $extraOut->button_name ?: null,
                    'description'        => $extraOut->description ?: null,
                    'min_payout_amount'  => (string) ($extraOut->min_payout_amount ?? '0'),
                    'min_trigger_amount' => (string) ($extraOut->min_trigger_amount ?? '0'),
                    'max_fields'         => (int) ($extraOut->max_fields ?? 20),
                ];
            }
        } catch (\Throwable $e) {
            //
        }

        return $result;
    }

    /**
     * Преобразует коллекцию моделей CurrencyLabel в массив для API-ответа.
     * Нормализует цвета: добавляет префикс '#' или возвращает null, если пусто.
     *
     * @param \Illuminate\Support\Collection<int, \App\Models\CurrencyLabel> $labels
     * @return array<int, array{
     *     id:int,
     *     title:string,
     *     text_color:string|null,
     *     bg_color:string|null
     * }>
     */
    private function mapLabels(\Illuminate\Support\Collection $labels): array
    {
        $normalize = static fn(?string $c): ?string => $c ? ('#' . ltrim($c, '#')) : null;

        return $labels->map(fn ($l) => [
            'id'         => $l->id,
            'title'      => $l->title,
            'text_color' => $normalize($l->text_color),
            'bg_color'   => $normalize($l->bg_color),
        ])->values()->all();
    }
}
