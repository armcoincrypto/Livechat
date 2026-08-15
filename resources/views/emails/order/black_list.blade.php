<x-mail::message>
# {{ $subject }}

{{ __('По заявке №:id, все данные занесены в черным список из-за нарушения правил', ['id' => current_order_id($order)]) }}

<x-mail::panel>
<p>
Email: {{ $order->email }}<br />
IP: {{ $order->ip }}<br />
@if(!is_null($order->from_shot))
{{ __('Номер счета (Отдаю)') }}: {{ $order->from_shot }}<br />
@endif
@if(!is_null($order->to_shot))
{{ __('Номер счета (Получаю)') }}: {{ $order->to_shot }}<br />
@endif
</p>
</x-mail::panel>

</x-mail::message>
