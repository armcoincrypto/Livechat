<x-mail::message>
# {{ $subject }}

<p>
Пользователь {{ $item->user->name }}, отправил документы на верификацию личности
</p>

<x-mail::panel>
* ID: **{{ $item->user->id }}**
* E-mail: **{{ $item->user->email }}**
</x-mail::panel>

</x-mail::message>
