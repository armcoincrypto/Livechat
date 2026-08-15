@component('mail::message')

{{-- Заголовок --}}
<h1 class="order-title">{{ $subject }}</h1>


<p>{{ __('smart-mailer::messages.greeting') }}</p>
<p>{{ __('smart-mailer::messages.order_completed_notice') }}</p>

<hr>

{{-- Статус заявки --}}
<div class="order-block-list__wrapper">
<div class="order-block-list__subtitle">{{ __('smart-mailer::messages.order_status') }}</div>
<div class="order-block-list__value-2">{{ $order->task_status->name }}</div>
</div>

{{-- Курс обмена --}}
@if(isset($order->course_display))
<div class="order-block-list__wrapper">
<div class="order-block-list__subtitle">{{ __('smart-mailer::messages.exchange_rate') }}</div>
<div class="order-block-list__value-2">{{ $order->course_display }}</div>
</div>
@endif

{{-- Дополнительные поля заявки --}}
@if(count($tasks_fields['many']) > 0)
<div class="order-block-list__wrapper">
@foreach($tasks_fields['many'] as $value)
<div class="order-block-list__subtitle">{{ $value['field_name'] }}</div>
<div class="order-block-list__value-2">{{ $value['field_value'] }}</div>
@endforeach
</div>
@endif


{{-- Информация "Отдаете" --}}
<div class="order-block-list__wrapper">
<div class="order-block-list__subtitle">{{ __('smart-mailer::messages.you_send') }}</div>
<div class="order-block-list__value-2">
{{ $order->give_price }} {{ $order->direction_exchange->currency1->code_currency->name }}<br>
{{ $order->direction_exchange->currency1->payment->name }} {{ $order->direction_exchange->currency1->code_currency->name }}
</div>

@if(isset($order->from_shot))
<div class="order-block-list__subtitle">{{ $order->direction_exchange->currency1->field_name_from }}</div>
<div class="order-block-list__value-2">{{ $order->from_shot }}</div>
@endif

@if(isset($tasks_fields['currency_in']) && count($tasks_fields['currency_in']) > 0)
@foreach($tasks_fields['currency_in'] as $item)
<div class="order-block-list__subtitle">{{ $item['field_name'] }}</div>
<div class="order-block-list__value-2">
{{ $item['field_value'] ?? __('smart-mailer::messages.not_specified') }}
</div>
@endforeach
@endif
</div>

{{-- Информация "Получаете" --}}
<div class="order-block-list__wrapper">
<div class="order-block-list__subtitle">{{ __('smart-mailer::messages.you_receive') }}</div>
<div class="order-block-list__value-2">
{{ $order->receiving_price }} {{ $order->direction_exchange->currency2->code_currency->name }}<br>
{{ $order->direction_exchange->currency2->payment->name }} {{ $order->direction_exchange->currency2->code_currency->name }}
</div>

@if(isset($order->to_shot))
<div class="order-block-list__subtitle">{{ $order->direction_exchange->currency2->field_name_to }}</div>
<div class="order-block-list__value-2">{{ $order->to_shot }}</div>
@endif

@if(isset($tasks_fields['currency_out']) && count($tasks_fields['currency_out']) > 0)
@foreach($tasks_fields['currency_out'] as $item)
<div class="order-block-list__subtitle">{{ $item['field_name'] }}</div>
<div class="order-block-list__value-2">
{{ $item['field_value'] ?? __('smart-mailer::messages.not_specified') }}
</div>
@endforeach
@endif
</div>

{{-- Город/страна --}}
@if(!empty($order->task_info->country_name))
<div class="order-block-list__wrapper">
<div class="order-block-list__subtitle">{{ __('smart-mailer::messages.location') }}</div>
<div class="order-block-list__value-2">
{{ $order->task_info->country_name }} / {{ $order->task_info->city_name }}
</div>
</div>
@endif


<div class="order-block-list__wrapper">
<div class="order-block-list__subtitle">{{ __('smart-mailer::messages.operation_date') }}</div>
<div class="order-block-list__value-2">
{{ \Illuminate\Support\Carbon::parse($order->created_at)->translatedFormat('d M Y H:i') }}
</div>
</div>



{{-- Направление обмена --}}
<div class="order-block-list__wrapper">
<div class="order-block-list__subtitle">{{ __('smart-mailer::messages.exchange_direction') }}</div>
<div class="order-block-list__value-2">
{{ direction_name($order->direction_exchange) }}
</div>
</div>


<hr />

<x-mail::button :url="config('app.frontend_url') . '/order/'.$order->public_id" color="primary">
{{ __('smart-mailer::messages.open_order') }}
</x-mail::button>


<hr />

<x-mail::subcopy>
{{ __('smart-mailer::messages.contact_support') }}<br>
[{{ iEXSetting('project_email_support') }}](mailto:{{ iEXSetting('project_email_support') }})
</x-mail::subcopy>

@endcomponent
