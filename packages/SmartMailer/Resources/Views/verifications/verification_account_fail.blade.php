<x-mail::message>
# {{ $subject }}

<p>
{{ __('smart-mailer::messages.greeting_user', ['name' => $item->user->name]) }}
</p>

<p>
{{ __('smart-mailer::messages.verification_account_fail_notice') }}
</p>

<p>
{{ __('smart-mailer::messages.verification_account_fail_support') }}
</p>

<hr />
<x-mail::subcopy>
{{ __('smart-mailer::messages.contact_support') }}<br>
[{{ iEXSetting('project_email_support') }}](mailto:{{ iEXSetting('project_email_support') }})
</x-mail::subcopy>
</x-mail::message>
