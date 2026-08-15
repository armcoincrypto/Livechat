<x-mail::message>
# {{ $subject }}

<p>
Пользователь {{ $item->name }}, добавил отзыв к заявке №{{ current_order_id($item->tasks) }}
</p>

<x-mail::panel>
{{ $item->text }}
</x-mail::panel>

</x-mail::message>
