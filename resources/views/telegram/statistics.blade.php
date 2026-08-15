==============================
<b>Статистика за {{\Illuminate\Support\Carbon::parse(\Illuminate\Support\Carbon::now())->translatedFormat('d M Y')}}</b>
==============================

 * Заявок за сегодня: {{ \App\Models\Task::whereDate('created_at', '=', \Illuminate\Support\Carbon::today())->count()}}
 * Исполненных: {{ \App\Models\Task::where('status',4)->whereDate('created_at', '=', \Illuminate\Support\Carbon::today())->count() }}
 * Отклоненых: {{ \App\Models\Task::where('status', 5)->whereDate('created_at', '=', \Illuminate\Support\Carbon::today())->count() }}

------------------------------
<b>📊 Итоговый отчет:</b>
------------------------------
Сумма обменов в RUB: {{ number_format($amount->rub, 0,'.', ' ') }} руб
Сумма обменов в USD: {{ number_format($amount->usd, 0,'.', ' ') }} $
------------------------------
<b>Активные операторы:</b>
@foreach(\App\Models\User::role(\Spatie\Permission\Models\Role::all())->get() as $user)
    @if($user->managerHistory('count', $amount->created_at) > 0)
{{$user->name}} - <i>заявок: {{ $user->managerHistory('count', $amount->created_at) }}</i>
    @endif
@endforeach

------------------------------
Ссылка на отчет: <a href="{{config('app.url')}}archive/operator/{{$archive->name}}">Открыть</a>
Событии по резервам: <a href="{{config('app.url')}}archive/logs/reserves">Открыть</a>