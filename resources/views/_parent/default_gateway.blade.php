<form id="autosubmit" name="MerchantPay" method="{{ $redirectMethod }}" action="{{ $redirectUrl }}">
    @foreach($item as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
    @endforeach
</form>
