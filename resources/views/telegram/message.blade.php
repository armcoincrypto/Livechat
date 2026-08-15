{{ __('telegram-default.messages.order_id') }}: {{ iEXSetting('client_id_type_for_order') == 1 ? e($detail->public_id) : e($detail->id) }}
{{ __('telegram-default.messages.course') }}: {{ e($detail->course_display) }}
{{ __('telegram-default.messages.created_at') }}: {{ $detail->created_at->format('d M Y, H:i') }}
@if($detail->payment_requisites != null)
{{ __('telegram-default.messages.wallet') }}: {{ e($detail->payment_requisites->account_number) }}
@endif
@if($detail->payment_address != null)
{{ __('telegram-default.messages.wallet') }}: {{ e($detail->payment_address) }}
@endif
@if($detail->payment_address == null and $detail->payment_requisites == null)
{{ __('telegram-default.messages.wallet') }}: {{ __('telegram-default.messages.undefined') }}
@endif
--------------------------
{{ __('telegram-default.messages.direction') }}: {{ e(direction_name($detail, true)) }}

<b>{{ __('telegram-default.messages.give_client') }}:</b>
  — {{ __('telegram-default.messages.payment_title') }}: {{ e($detail->direction_exchange->currency1->payment->name.' '.$detail->direction_exchange->currency1->code_currency->name) }}
  — {{ __('telegram-default.messages.amount') }}: {{ e($detail->give_price.' '.$detail->direction_exchange->currency1->code_currency->name) }}
@if(strlen(strip_tags((string) $detail->from_shot)) > 0)
  — {{ __('telegram-default.messages.from_shot') }}: {{ e(strip_tags((string) $detail->from_shot)) }}
@endif

<b>{{ __('telegram-default.messages.out_title') }}:</b>
  — {{ __('telegram-default.messages.payment_title') }}: {{ e($detail->direction_exchange->currency2->payment->name.' '.$detail->direction_exchange->currency2->code_currency->name) }}
  — {{ __('telegram-default.messages.amount') }}: {{ e($detail->receiving_price.' '.$detail->direction_exchange->currency2->code_currency->name) }}
@if(strlen(strip_tags((string) $detail->to_shot)) > 0)
  — {{ __('telegram-default.messages.to_shot') }}: {{ e(strip_tags((string) $detail->to_shot)) }}
@endif
--------------------------
<b>{{ __('telegram-default.messages.user_info') }}</b>
— ID: {{ e($detail->user->id) }}
— E-mail: {{ e($detail->user->email) }}
— {{ __('telegram-default.messages.name') }}: {{ e($detail->user->name) }}
