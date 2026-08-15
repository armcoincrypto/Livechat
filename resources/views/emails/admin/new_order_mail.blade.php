<x-mail::message>
# {{ $subject }}

<p>Поступила новая заявка №{{ current_order_id($order) }} от клиента {{ $order->user->name }}</p>

<x-mail::panel>
{{ __('Детали заявки') }}:
* Направление обмена: **{{ $order->direction_exchange->tech_name }}**
</x-mail::panel>


</x-mail::message>
