<x-mail::message>
# {{ $subject }}

<p>
По заявке №{{ isset($order) ? current_order_id($order) : ''}}, поступила информация на верификацию счета.
</p>

<x-mail::panel>
* Валюта: **{{ $item->currency->payment->name }} {{ $item->currency->code_currency->name }}**
* Номер счета: **{{ $item->resolvedCardNumberString() ?: ($item->resolvedCardNumber() ?: '') }}**
</x-mail::panel>

<br />
<div style="text-align: center">
<img width="100px" height="auto" src="{{ url('/images/verifications/'.$item->image) }}"/>
</div>

</x-mail::message>
