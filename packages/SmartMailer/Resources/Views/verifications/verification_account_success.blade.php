<x-mail::message>
# {{ $subject }}

<p>{{ __('smart-mailer::messages.greeting_user', ['name' => $item->user->name]) }}</p>

<p>{{ __('smart-mailer::messages.verification_success_notice') }}</p>

<p>{{ __('smart-mailer::messages.verification_success_thanks') }}</p>

<x-mail::subcopy>
{{ __('smart-mailer::messages.contact_support') }}<br>
[{{ iEXSetting('project_email_support') }}](mailto:{{ iEXSetting('project_email_support') }})
</x-mail::subcopy>

</x-mail::message>
