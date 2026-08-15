@component('mail::message')
# {{ $subject }}


@if(empty($data))
@include('emails.defaults.'.$default_template)
@else
{!! $data['content'] !!}
@endif
@endcomponent
