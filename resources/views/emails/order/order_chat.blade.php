@component('mail::message')
# {{ $subject }}

<p>Уважаемый клиент, Вы создали заявку <b>№{{ iEXSetting('client_id_type_for_order') == 1 ? $order->public_id : $order->id }}</b>. </p>
@component('mail::panel')
<p>Комментарий:</p>
@if(isset($options['message_success']))
{!! $options['message_success'] !!}
@endif
@endcomponent
@endcomponent
