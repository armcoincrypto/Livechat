<x-mail::message>
# {{ $subject }}

{{ __('emails.new_device.text', ['sitename' => $sitename]) }}

<x-mail::panel>
@php
    $geo = $geoLabel ?? null;
    if (empty($geo)) {
        $parts = [];
        if (!empty($log->country)) $parts[] = $log->country;
        if (!empty($log->city)) $parts[] = $log->city;
        if (!empty($log->iso_code)) $parts[] = $log->iso_code;
        $geo = $parts ? implode(', ', $parts) : null;
    }
@endphp

@if(!empty($geo))
**{{ __('Локация') }}:** {{ $geo }}<br />
@endif

**{{ __('Время') }}:**
@if($time instanceof \Carbon\CarbonInterface)
    {{ $time->toCookieString() }}
@else
    {{ $time }}
@endif
<br />

**IP Address:** {{ $ipAddress }}<br />

**User-Agent:** {{ $browser }}<br />

@if(!empty($device))
**{{ __('Устройство') }}:** {{ $device }}<br />
@endif

@if(!empty($os))
**{{ __('ОС') }}:** {{ $os }}<br />
@endif
</x-mail::panel>

</x-mail::message>
