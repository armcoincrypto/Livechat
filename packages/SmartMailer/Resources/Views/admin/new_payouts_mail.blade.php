<x-mail::message>
# {{ $subject }}

<p>
{{ __('smart-mailer::messages.new_payout_request_intro', [
    'id' => $item->big_id,
    'name' => $item->user->name
]) }}
</p>

<x-mail::subcopy>
{{ __('smart-mailer::messages.check_admin_panel') }}
</x-mail::subcopy>

</x-mail::message>
