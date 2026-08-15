@component('mail::layout')
{{-- Header --}}
@slot('header')
<img src="{{ config('app.frontend_url') }}/images/{{ iEXSetting('logotype_mail')  }}" class="logotype" />
@endslot

{{-- Body --}}
{{ $slot }}

{{-- Subcopy --}}
@isset($subcopy)
@slot('subcopy')
@component('mail::subcopy')
{{ $subcopy }}
@endcomponent
@endslot
@endisset

{{-- Footer --}}
@slot('footer')
@component('mail::footer')
{{ iEXContentLanguage('sitename'). ' - '. iEXContentLanguage('sitename_desc') }}.<br />
@endcomponent
@endslot
@endcomponent
