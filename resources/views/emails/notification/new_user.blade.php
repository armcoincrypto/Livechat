<x-mail::message>
# {{ $subject }}

{{ __('Поздравляем Вас с успешной регистрацией на сайте :sitename', ['sitename' => $sitename], $locale) }}

<x-mail::panel>
* {{ __('Ваш Email', [], $locale) }}: **{{ $user->email }}**
* {{ __('Ваш пароль', [], $locale) }}: **{{ $password }}**
</x-mail::panel>

</x-mail::message>
