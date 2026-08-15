<x-mail::message>
# {{ $subject }}

<p>{{ __('Здравствуйте :name', ['name' => $user->name]) }}!</p>
<br />

{{ __('Мы только что заметили вход в Ваш аккаунт :sitename, если вы думаете, что кто-то вошел в ваш аккаунт против вашей воли, вам необходимо срочно зайти на сервис и сменить пароль.', ['sitename' => $sitename]) }}


<x-mail::panel>
{{ __('Это были Вы?') }}

* {{ __('Когда') }}: {{ Carbon\Carbon::now()->toDateTimeString() }}
* {{ __('IP Адрес') }}: {{ $user->ip_address }}
@if(!empty($geo_string))
* {{ __('Местоположение') }}: {{ $geo_string }}
@endif
</x-mail::panel>

</x-mail::message>
