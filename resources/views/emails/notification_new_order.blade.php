@component('mail::message')
# Новая заявка № {{$item['id']}}!<br/><br/>

Уважаемые операторы, поступила новая заявка #{{ $item['id'] }} от клиента {{$item['name']}}
@endcomponent
