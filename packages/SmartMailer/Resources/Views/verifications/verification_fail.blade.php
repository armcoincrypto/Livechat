<x-mail::message>
# {{ $subject }}

<p>
@if(isset($verification->tasks) && !empty($verification->tasks))
{{ __('smart-mailer::messages.verification_request_with_order', ['id' => current_order_id($verification->tasks)]) }}
@else
{{ __('smart-mailer::messages.verification_request_without_order') }}
@endif
</p>

<p>
{{ __('smart-mailer::messages.verification_rejected_notice') }}
</p>

@if(!empty($verification->text_message))
<x-mail::panel>
{{ __('smart-mailer::messages.rejection_reason') }}: {{ $verification->text_message }}
</x-mail::panel>
@endif

<x-mail::subcopy>
{{ __('smart-mailer::messages.contact_support') }}<br>
[{{ iEXSetting('project_email_support') }}](mailto:{{ iEXSetting('project_email_support') }})
</x-mail::subcopy>

</x-mail::message>
