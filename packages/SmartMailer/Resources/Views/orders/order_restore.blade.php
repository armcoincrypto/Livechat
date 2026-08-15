<x-mail::message>
# {{ $subject }}

{{ __('smart-mailer::messages.greeting') }}

{{ __('smart-mailer::messages.order_restored_notice', ['id' => current_order_id($order)]) }}

---

### {{ __('smart-mailer::messages.next_steps') }}

{{ __('smart-mailer::messages.order_restored_actions') }}

---

### {{ __('smart-mailer::messages.need_help') }}

{{ __('smart-mailer::messages.contact_support') }}:<br>
[{{ iEXSetting('project_email_support') }}](mailto:{{ iEXSetting('project_email_support') }})

</x-mail::message>
