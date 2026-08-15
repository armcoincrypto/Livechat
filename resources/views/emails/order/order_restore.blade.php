<x-mail::message>
# {{ $subject }}

<p>{!! __('Заявка №:id восстановлена и находится в процессе обработки', ['id' => current_order_id($order)]) !!}</p>
</x-mail::message>
