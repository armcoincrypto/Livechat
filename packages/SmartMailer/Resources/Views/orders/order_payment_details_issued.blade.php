<x-mail::message>
# {{ $subject }}

<p>
{{ __('smart-mailer::messages.payment_details_intro') }}
</p>

<x-mail::panel>
* **{{ __('smart-mailer::messages.account_number') }}:** {{ $order->requisites_receive }}

@if(isset($order->task_requisite_attached) && !empty($order->task_requisite_attached))

@if(isset($order->task_requisite_attached->ext_params['fields']) && is_array($order->task_requisite_attached->ext_params['fields']))
* **{{ __('smart-mailer::messages.additional_fields') }}:**<br/>
@foreach($order->task_requisite_attached->ext_params['fields'] as $field)
{{ $field }}<br />
@endforeach
@endif

@if(isset($order->task_requisite_attached->ext_params['description']) && !empty($order->task_requisite_attached->ext_params['description']))
* **{{ __('smart-mailer::messages.payment_comment') }}:** {{ $order->task_requisite_attached->ext_params['description'] }}
@endif

@endif

</x-mail::panel>

<p>{{ __('smart-mailer::messages.payment_details_notice') }}</p>


<hr />
<x-mail::button :url="config('app.frontend_url') . '/order/'.$order->public_id" color="primary">
{{ __('smart-mailer::messages.view_order') }}
</x-mail::button>

<hr />

<x-mail::subcopy>
{{ __('smart-mailer::messages.contact_support') }}<br>
[{{ iEXSetting('project_email_support') }}](mailto:{{ iEXSetting('project_email_support') }})
</x-mail::subcopy>

</x-mail::message>
