<x-mail::message>
# {{ $subject }}

<p>
@if(isset($verification->tasks) && !empty($verification->tasks))
{{ __('smart-mailer::messages.verification_request_approved_order_extended', ['id' => current_order_id($verification->tasks)]) }}
@else
{{ __('smart-mailer::messages.verification_request_approved_extended') }}
@endif
</p>

<p>
{{ __('smart-mailer::messages.account_no_restrictions_extended') }}
</p>

<x-mail::subcopy>
{{ __('smart-mailer::messages.contact_support') }}<br>
[{{ iEXSetting('project_email_support') }}](mailto:{{ iEXSetting('project_email_support') }})
</x-mail::subcopy>

</x-mail::message>
