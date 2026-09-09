@component('mail::message')

{{-- Hierarchy: order summary first, account setup secondary, exchange details last --}}
# {{ __('smart-mailer::messages.order_created_heading', ['id' => current_order_id($order)]) }}

**{{ __('smart-mailer::messages.order_status') }}:** {{ $localizedStatus }}

---

## {{ __('smart-mailer::messages.you_send') }}
**{{ $order->give_price }} {{ $order->direction_exchange->currency1->code_currency->name }}**  
{{ $order->direction_exchange->currency1->payment->name }}

## {{ __('smart-mailer::messages.you_receive') }}
**{{ $order->receiving_price }} {{ $order->direction_exchange->currency2->code_currency->name }}**  
{{ $order->direction_exchange->currency2->payment->name }}

@if(isset($order->course_display))
**{{ __('smart-mailer::messages.exchange_rate') }}:** {{ $order->course_display }}
@endif

@if(empty($order->task_info->country_name))
**{{ __('smart-mailer::messages.payment_time') }}:** {{ ((int) iEXSetting('max_time_task')) / 60 }} {{ __('smart-mailer::messages.minutes') }}
@endif

<x-mail::button :url="$openOrderUrl" color="primary">
{{ __('smart-mailer::messages.open_order') }}
</x-mail::button>

@if(!empty($showSetPasswordCta) && !empty($setPasswordUrl))
---

## {{ __('smart-mailer::messages.account_section_title') }}

{{ __('smart-mailer::messages.account_section_intro') }}

{{ __('smart-mailer::messages.account_section_set_password_hint') }}

<x-mail::button :url="$setPasswordUrl" color="success">
{{ __('smart-mailer::messages.set_password') }}
</x-mail::button>

{{ __('smart-mailer::messages.account_section_continue_without') }}

{{ __('smart-mailer::messages.account_section_security') }}
@endif

---

## {{ __('smart-mailer::messages.exchange_details') }}

**{{ __('smart-mailer::messages.exchange_direction') }}:** {{ direction_name($order->direction_exchange) }}

@if(!empty($walletInfoAccount))
@if(is_array($walletInfoAccount) && !isset($walletInfoAccount['checkout_url']))
@if(isset($walletInfoAccount['wallet_number']))
**{!! $account_number_field !!}:** {{ $walletInfoAccount['wallet_number'] }}
@endif
@if(isset($walletInfoAccount['memo_id']))
**{{ __('MEMO ID') }}:** {{ $walletInfoAccount['memo_id'] }}
@endif
@elseif(is_string($walletInfoAccount))
**{!! $account_number_field !!}:** {{ $walletInfoAccount }}
@endif
@endif

@if(isset($order->from_shot))
**{{ $order->direction_exchange->currency1->field_name_from }}:** {{ $order->from_shot }}
@endif

@if(isset($order->to_shot))
**{{ $order->direction_exchange->currency2->field_name_to }}:** {{ $order->to_shot }}
@endif

@if(count($tasks_fields['requisites_info_field']) > 0)
@foreach($tasks_fields['requisites_info_field'] as $value)
**{{ $value['key_name'] }}:** {{ $value['value_name'] }}
@endforeach
@endif

@if(count($tasks_fields['many']) > 0)
@foreach($tasks_fields['many'] as $value)
**{{ $value['field_name'] }}:** {{ $value['field_value'] ?? __('smart-mailer::messages.not_specified') }}
@endforeach
@endif

@if(isset($tasks_fields['currency_in']) && count($tasks_fields['currency_in']) > 0)
@foreach($tasks_fields['currency_in'] as $item)
**{{ $item['field_name'] }}:** {{ $item['field_value'] ?? __('smart-mailer::messages.not_specified') }}
@endforeach
@endif

@if(isset($tasks_fields['currency_out']) && count($tasks_fields['currency_out']) > 0)
@foreach($tasks_fields['currency_out'] as $item)
**{{ $item['field_name'] }}:** {{ $item['field_value'] ?? __('smart-mailer::messages.not_specified') }}
@endforeach
@endif

@if(!empty($order->task_info->country_name))
**{{ __('smart-mailer::messages.location') }}:** {{ $order->task_info->country_name }} / {{ $order->task_info->city_name }}
@endif

**{{ __('smart-mailer::messages.operation_date') }}:** {{ \Illuminate\Support\Carbon::parse($order->created_at)->translatedFormat('d M Y H:i') }}

@if($textInfo)
{{-- Direction-specific instruction (secondary) --}}
{!! $textInfo !!}
@endif

@if(!empty($directionCityInstruction))
**{{ __('smart-mailer::messages.operation_information') }}:**  
{!! $directionCityInstruction !!}
@endif

---

<x-mail::subcopy>
{{ __('smart-mailer::messages.contact_support') }}<br>
[{{ iEXSetting('project_email_support') }}](mailto:{{ iEXSetting('project_email_support') }})
</x-mail::subcopy>

@endcomponent
