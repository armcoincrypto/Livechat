@component('mail::message')

# Ваш номер чека!

<p>Уважаемый клиент, Вы создали заявку <b>№{{ $item['id'] }}</b>.</p>
@component('mail::panel')
<p>По Вашей заявке был успешно создан номер чека:</p>
@if(isset($input['message_success']))
{!! $input['message_success'] !!}
@endif


<p>При возникновении вопросов, Вы можете обратиться в службу поддержки на нашем сайте или написать нам на почту {{ iEXSetting('project_email_support') }}</p>
@endcomponent
