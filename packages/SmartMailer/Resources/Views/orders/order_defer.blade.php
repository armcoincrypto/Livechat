<x-mail::message>
# {{ $subject }}

{{ __('smart-mailer::messages.greeting') }}

{{ __('smart-mailer::messages.order_deferred_notice', ['id' => current_order_id($order)]) }}

---

### {{ __('smart-mailer::messages.order_details') }}

<x-mail::panel>
- **{{ __('smart-mailer::messages.order_number') }}:** {{ current_order_id($order) }}<br>
- **{{ __('smart-mailer::messages.status') }}:** {{ __('smart-mailer::messages.status_deferred') }}<br>
- **{{ __('smart-mailer::messages.reason') }}:**
@if(isset($order->pending_order_status))
{{ $order->pending_order_status->name }}
@else
{{ __('smart-mailer::messages.reason_not_provided') }}
@endif
</x-mail::panel>

---

### {{ __('smart-mailer::messages.what_next') }}

{{ __('smart-mailer::messages.order_deferred_actions') }}

---

<x-mail::subcopy>
{{ __('smart-mailer::messages.contact_support') }}:<br>
[{{ iEXSetting('project_email_support') }}](mailto:{{ iEXSetting('project_email_support') }})
</x-mail::subcopy>
</x-mail::message>
