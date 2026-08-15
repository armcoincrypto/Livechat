@component('mail::message')

# {{ __('smart-mailer::messages.payout_request_subject') }}

{{ __('smart-mailer::messages.greeting_user', ['name' => $item->user->name]) }}

{{ __('smart-mailer::messages.payout_request_intro', [
    'amount' => iex_number_format($item->balance_referral, 2),
    'currency' => $item->currency->code_currency->name
]) }}

{{ __('smart-mailer::messages.payout_request_confirm_instruction') }}

@component('mail::button', ['url' => $confirmUrl])
{{ __('smart-mailer::messages.confirm_payout_button') }}
@endcomponent

{{ __('smart-mailer::messages.payout_request_warning', ['site' => iEXContentLanguage('sitename')]) }}

@endcomponent
