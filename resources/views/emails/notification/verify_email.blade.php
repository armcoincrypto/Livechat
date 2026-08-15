<x-mail::message>
# {{ $subject }}

{{ __('emails.verify_email.text', ['sitename' => $sitename], $locale) }}

<x-mail::button :url="$verify">
{{ __('Подтвердить', [], $locale) }}
</x-mail::button>
</x-mail::message>
