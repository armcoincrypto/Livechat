@component('mail::message')
# {{ $subject }}
<p>Уважаемый клиент, Вы создали заявку <b>№{{ $order->id }}</b>.</p>
@component('mail::panel')
<p>По Вашей заявке был успешно создан номер чека:</p>
@if(isset($options['message_success']))
{!! $options['message_success'] !!}
@endif
@endcomponent
@endcomponent
