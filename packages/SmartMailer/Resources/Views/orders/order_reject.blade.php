<x-mail::message>
# {{ $subject }}

{{ __('smart-mailer::messages.greeting') }}

{{ __('smart-mailer::messages.rejected_notice', ['id' => current_order_id($order)]) }}

---

### {{ __('smart-mailer::messages.order_details') }}

<x-mail::panel>
**{{ __('smart-mailer::messages.order_number') }}:** {{ current_order_id($order) }}<br>
**{{ __('smart-mailer::messages.status') }}:** {{ __('smart-mailer::messages.rejected') }}<br>
**{{ __('smart-mailer::messages.reason') }}:**
@if(isset($order->tasks_rejection_status))
{{ $order->tasks_rejection_status->name }}
@else
{{ __('smart-mailer::messages.reason_not_provided') }}
@endif
</x-mail::panel>

---

### {{ __('smart-mailer::messages.what_next') }}

- {{ __('smart-mailer::messages.action_check') }}
- {{ __('smart-mailer::messages.action_retry') }}

---

### {{ __('smart-mailer::messages.need_help') }}

{{ __('smart-mailer::messages.support_email') }}:
[{{ iEXSetting('project_email_support') }}](mailto:{{ iEXSetting('project_email_support') }})
</x-mail::message>
