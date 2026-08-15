<b>Заявка на выплату бонусных вознаграждений.</b>

- № Заявки: {{ $detail->id }}
------------
- ПС. {{$detail->currency->payment->name}} {{$detail->currency->code_currency->name}}
- Реферальные: {{ $detail->base_referral }}  {{$detail->currency->code_currency->name}}
- Итоговая сумма выплаты {{ $detail->base_referral}}  {{$detail->currency->code_currency->name}}

<b>О пользователе:</b>
— Имя: {{ $detail->user->name }}
— E-mail: {{ $detail->user->email }}
