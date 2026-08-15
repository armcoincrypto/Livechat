<b>Статистика за сегодня:</b>
--------------------------------
Всего заявок: {{ $options['today_order'] }}
Выполненных заявок: {{ $options['order_success'] }}
Заявок в ожидании: {{ $options['order_waiting'] }}
Отклоненных заявок: {{ $options['cancel_order'] }}
Новых пользователей {{ $options['new_users'] }}
--------------------------------
Общая сумма обменов: ${{ iex_number_format((float)$options['amount_exchanges'], 2, true) }}.
