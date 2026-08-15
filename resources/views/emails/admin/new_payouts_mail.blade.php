<x-mail::message>
# {{ $subject }}

<p>
Поступила новая заявка №{{ $item->big_id }} от клиента {{$item->user->name}}, на выплату бонусных вознаграждений.
</p>

</x-mail::message>
