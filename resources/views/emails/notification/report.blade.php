@component('mail::message')
# Итоговый отчет за {{ \Illuminate\Support\Carbon::now()->format('d.m.Y') }}

Здравствуйте, ежедневый итоговый отчет по резервам и заявкам успешно сформирован.
Копия отчета прикреплена к данной теме.

С уважением,<br>
{{ config('app.name') }}
@endcomponent
