{{ __('telegram-default.messages.order_id') }}: {{ iEXSetting('client_id_type_for_order') == 1 ? $detail->public_id : $detail->id }}
{{ __('telegram-default.messages.created_at') }}: {{ $detail->created_at->format('d M Y, H:i') }}
{{ __('telegram-default.messages.course') }}: {{ $detail->course_display  }}
@if(isset($detail->status))
{{ __('telegram-default.messages.status') }}: {{ $detail->status }}
@endif
@if(isset($detail->manager) && $detail->manager)
{{ __('telegram-default.messages.operator') }}: {{ $detail->manager->name ?? $detail->manager->email }}
@endif
@if($detail->payment_requisites != null)
{{ __('telegram-default.messages.wallet') }}: {{ $detail->payment_requisites->account_number }}
@endif
@if($detail->payment_address != null)
{{ __('telegram-default.messages.wallet') }}: {{ $detail->payment_address }}
@endif
@if($detail->payment_address == null and $detail->payment_requisites == null)
{{ __('telegram-default.messages.wallet') }}: {{ __('telegram-default.messages.undefined') }}
@endif
--------------------------
{{ __('telegram-default.messages.direction') }}: {{ direction_name($detail, true) }}

<b>{{ __('telegram-default.messages.give_client') }}:</b>
  — {{ __('telegram-default.messages.payment_title') }}: {{$detail->direction_exchange->currency1->payment->name.' '.$detail->direction_exchange->currency1->code_currency->name}}
  — {{ __('telegram-default.messages.amount') }}: {{$detail->give_price.' '.$detail->direction_exchange->currency1->code_currency->name}}
@if(strlen(strip_tags($detail->from_shot)) > 0)
  — {{ __('telegram-default.messages.from_shot') }}: {{$detail->from_shot}}
@endif

<b>{{ __('telegram-default.messages.out_title') }}:</b>
  — {{ __('telegram-default.messages.payment_title') }}: {{$detail->direction_exchange->currency2->payment->name.' '.$detail->direction_exchange->currency2->code_currency->name}}
  — {{ __('telegram-default.messages.amount') }}: {{$detail->receiving_price.' '.$detail->direction_exchange->currency2->code_currency->name}}
@if(strlen(strip_tags($detail->to_shot)) > 0)
  — {{ __('telegram-default.messages.to_shot') }}: {{$detail->to_shot}}
@endif
--------------------------
<b>{{ __('telegram-default.messages.user_info') }}</b>
— ID: {{ $detail->user->id }}
— E-mail: {{ $detail->user->email }}
— {{ __('telegram-default.messages.name') }}: {{ $detail->user->name }}
