<form id="autosubmit" name="MerchantPay" method="{{ $redirectMethod }}" action="{{ $redirectUrl }}">
    @foreach($item as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
    @endforeach
    @if(count($redirectData) > 0)
        @foreach($redirectData as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
        @endforeach
    @endif
</form>
