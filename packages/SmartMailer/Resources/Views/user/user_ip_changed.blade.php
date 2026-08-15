@component('mail::message')
# {{ $subject }}

{{ __('smart-mailer::messages.hello_user', ['name' => $user->name]) }}

{{ __('smart-mailer::messages.ip_change_intro') }}

<div class="info-table">
<table class="styled-table">
<thead>
<tr>
<th>{{ __('smart-mailer::messages.parameter') }}</th>
<th>{{ __('smart-mailer::messages.value') }}</th>
</tr>
</thead>
<tbody>
<tr>
<td>{{ __('smart-mailer::messages.previous_ip') }}</td>
<td>{{ $oldIp }}</td>
</tr>
<tr>
<td>{{ __('smart-mailer::messages.new_ip') }}</td>
<td>{{ $newIp }}</td>
</tr>
<tr>
<td>{{ __('smart-mailer::messages.date_time') }}</td>
<td>{{ now()->translatedFormat('d.m.Y H:i:s') }}</td>
</tr>
</tbody>
</table>
</div>

{{ __('smart-mailer::messages.ip_change_warning') }}

<x-mail::subcopy>
{{ __('smart-mailer::messages.contact_support') }}<br>
[{{ iEXSetting('project_email_support') }}](mailto:{{ iEXSetting('project_email_support') }})
</x-mail::subcopy>

@endcomponent
