@component('mail::message')
# {{ $subject }}
<p>{{ __('email.recount_desc') }}.</p>
@component('mail::panel')
<p>{{ __('email.detail') }}:</p>
@if(isset($recount))
<ul>
<li>{{ __('email.you_get') }}: {{ $order->receiving_price }} {{ $order->direction_exchange->currency2->code_currency->name }}</li>
<li>{{ __('email.new_rate') }}: {{ $recount->course }}</li>
</ul>
@endif
@endcomponent
@endcomponent
