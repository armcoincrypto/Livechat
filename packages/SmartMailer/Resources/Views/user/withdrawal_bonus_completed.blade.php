<x-mail::message>
# {{ __('smart-mailer::messages.partner_payout_completed') }}

{{ __('smart-mailer::messages.greeting_user', ['name' => $item->user->name]) }}

{{ __('smart-mailer::messages.partner_payout_completed_notice') }}

<x-mail::panel>
- **{{ __('smart-mailer::messages.amount') }}:** {{ $balance }}
- **{{ __('smart-mailer::messages.currency') }}:** {{ $payment }}
</x-mail::panel>

{{ __('smart-mailer::messages.thank_you_for_partnership') }}
</x-mail::message>
