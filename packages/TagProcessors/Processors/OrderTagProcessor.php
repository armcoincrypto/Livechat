<?php

namespace iEXPackages\TagProcessors\Processors;

use Illuminate\Http\Request;
use iEXPackages\TagProcessors\Contracts\DescribableTagProcessorInterface;
use iEXPackages\TagProcessors\Support\TagDefinition;

class OrderTagProcessor extends AbstractTagProcessor implements DescribableTagProcessorInterface
{
    /**
     * Описания всех тегов этого процессора.
     *
     * @return TagDefinition[]
     */
    public function definitions(): array
    {
        return [
            new TagDefinition(
                key: '[order_id]',
                name: 'Внутренний ID заявки',
                description: 'Внутренний ID заявки в базе (для поддержки и админки).',
                example: '12345',
                group: 'order',
                resolver: fn($data) => $data['id'] ?? null,
            ),

            new TagDefinition(
                key: '[public_id]',
                name: 'Публичный номер заявки',
                description: 'Публичный номер заявки, видимый клиенту.',
                example: 'EX-2025-00001',
                group: 'order',
                resolver: fn($data) => $data['public_id'] ?? null,
            ),

            new TagDefinition(
                key: '[created_at]',
                name: 'Дата создания заявки',
                description: 'Дата и время создания заявки.',
                example: '2025-11-17 10:15:00',
                group: 'order',
                resolver: fn($data) => $data['created_at'] ?? null,
            ),

            new TagDefinition(
                key: '[updated_at]',
                name: 'Дата обновления заявки',
                description: 'Дата и время последнего изменения заявки.',
                example: '2025-11-17 10:20:00',
                group: 'order',
                resolver: fn($data) => $data['updated_at'] ?? null,
            ),

            new TagDefinition(
                key: '[ip_address]',
                name: 'IP-адрес клиента',
                description: 'IP-адрес, с которого была создана заявка.',
                example: '192.168.0.1',
                group: 'order',
                resolver: fn($data) => $data['ip'] ?? null,
            ),

            new TagDefinition(
                key: '[email]',
                name: 'Email клиента',
                description: 'Email, указанный при создании заявки.',
                example: 'user@example.com',
                group: 'order',
                resolver: fn($data) => $data['email'] ?? null,
            ),

            new TagDefinition(
                key: '[income_amount]',
                name: 'Сумма, которую клиент отдаёт',
                description: 'Сумма, которую клиент отдаёт по заявке.',
                example: '1000.00',
                group: 'order',
                resolver: fn($data) => $data['give_price'] ?? null,
            ),

            new TagDefinition(
                key: '[outcome_amount]',
                name: 'Сумма, которую клиент получает',
                description: 'Сумма, которую клиент получает по заявке.',
                example: '998.50',
                group: 'order',
                resolver: fn($data) => $data['receiving_price'] ?? null,
            ),

            new TagDefinition(
                key: '[income_code]',
                name: 'Код валюты «отдаю»',
                description: 'Код/тикер валюты «отдаю» (например: USD, RUB, USDT).',
                example: 'USD',
                group: 'order',
                resolver: function ($data) {
                    if (!is_object($data) || !isset($data->direction_exchange)) {
                        return null;
                    }
                    $direction = $data->direction_exchange;
                    return $direction->currency1->code_currency->name
                        ?? null;
                },
            ),

            new TagDefinition(
                key: '[outcome_code]',
                name: 'Код валюты «получаю»',
                description: 'Код/тикер валюты «получаю».',
                example: 'USDT',
                group: 'order',
                resolver: function ($data) {
                    if (!is_object($data) || !isset($data->direction_exchange)) {
                        return null;
                    }
                    $direction = $data->direction_exchange;
                    return $direction->currency2->code_currency->name
                        ?? null;
                },
            ),

            new TagDefinition(
                key: '[city]',
                name: 'Город клиента',
                description: 'Город клиента, в котором оформлена заявка.',
                example: 'Дубай',
                group: 'order',
                resolver: function ($data) {
                    if (!is_object($data) || !isset($data->task_info)) {
                        return null;
                    }
                    return $data->task_info->city_name ?? null;
                },
            ),

            new TagDefinition(
                key: '[country]',
                name: 'Страна клиента',
                description: 'Страна клиента.',
                example: 'ОАЭ',
                group: 'order',
                resolver: function ($data) {
                    if (!is_object($data) || !isset($data->task_info)) {
                        return null;
                    }
                    return $data->task_info->country_name ?? null;
                },
            ),

            new TagDefinition(
                key: '[direction_name]',
                name: 'Название направления обмена',
                description: 'Название направления обмена в формате «Отдаю → Получаю».',
                example: 'BTC → RUB (карта)',
                group: 'order',
                resolver: function ($data) {
                    if (!is_object($data) || !isset($data->direction_exchange)) {
                        return null;
                    }
                    return $data->direction_exchange->tech_name ?? null;
                },
            ),

            new TagDefinition(
                key: '[income_currency]',
                name: 'Полное название валюты «отдаю»',
                description: 'Полное название валюты и платёжной системы для направления «отдаю».',
                example: 'Tinkoff RUB',
                group: 'order',
                resolver: function ($data) {
                    if (!is_object($data) || !isset($data->direction_exchange)) {
                        return null;
                    }
                    $direction = $data->direction_exchange;
                    $c1        = $direction->currency1 ?? null;
                    if (!$c1 || !$c1->payment || !$c1->code_currency) {
                        return null;
                    }
                    return $c1->payment->name . ' ' . $c1->code_currency->name;
                },
            ),

            new TagDefinition(
                key: '[outcome_currency]',
                name: 'Полное название валюты «получаю»',
                description: 'Полное название валюты и платёжной системы для направления «получаю».',
                example: 'Binance USDT',
                group: 'order',
                resolver: function ($data) {
                    if (!is_object($data) || !isset($data->direction_exchange)) {
                        return null;
                    }
                    $direction = $data->direction_exchange;
                    $c2        = $direction->currency2 ?? null;
                    if (!$c2 || !$c2->payment || !$c2->code_currency) {
                        return null;
                    }
                    return $c2->payment->name . ' ' . $c2->code_currency->name;
                },
            ),

            new TagDefinition(
                key: '[course]',
                name: 'Итоговый курс обмена',
                description: 'Итоговый курс обмена, применённый в заявке (course_display).',
                example: '1 BTC = 6 000 000 RUB',
                group: 'order',
                resolver: fn($data) => $data['course_display'] ?? null,
            ),

            new TagDefinition(
                key: '[unique_code]',
                name: 'Уникальный защитный код',
                description: 'Уникальный защитный код заявки.',
                example: 'ABCD-1234',
                group: 'order',
                resolver: fn($data) => $data['unique_security_code'] ?? null,
            ),

            new TagDefinition(
                key: '[check_url]',
                name: 'Ссылка на страницу заявки',
                description: 'Полная ссылка на страницу просмотра заявки на сайте.',
                example: config('app.frontend_url') . '/order/EX-2025-00001',
                group: 'order',
                resolver: fn($data) => config('app.frontend_url') . '/order/' . ($data['public_id'] ?? ''),
            ),

            new TagDefinition(
                key: '[app_name]',
                name: 'Название проекта',
                description: 'Человекочитаемое название обменника (sitename).',
                example: 'iEXExchanger',
                group: 'order',
                resolver: fn() => iEXContentLanguage('sitename'),
            ),

            new TagDefinition(
                key: '[profit_percent]',
                name: 'Процент прибыли направления',
                description: 'Эффективное числовое значение процента прибыли по направлению (учитывает профиль и индивидуальные настройки).',
                example: '1.5',
                group: 'order',
                resolver: function ($data) {
                    if (!is_object($data) || !isset($data->direction_exchange)) {
                        return null;
                    }
                    // Процентная прибыль: используем поле profit / profit из профиля
                    return $this->resolveDirectionProfitValue($data->direction_exchange, 'profit', 'profit');
                },
            ),

            new TagDefinition(
                key: '[profit_s]',
                name: 'Фиксированная прибыль направления (profit_s)',
                description: 'Числовое значение фиксированной прибыли направления (profit_s), учитывает профиль и индивидуальные настройки. Без знаков, только значение.',
                example: '10',
                group: 'order',
                resolver: function ($data) {
                    if (!is_object($data) || !isset($data->direction_exchange)) {
                        return null;
                    }
                    // Фиксированная прибыль: используем поле profit_s / profit_s из профиля
                    return $this->resolveDirectionProfitValue($data->direction_exchange, 'profit_s', 'profit_s');
                },
            ),

            new TagDefinition(
                key: '[city_profit_percent]',
                name: 'Процент прибыли по городу',
                description: 'Процент прибыли по направлению, рассчитанный для текущего города.',
                example: '0.5',
                group: 'order',
                resolver: function ($data) {
                    if (!is_object($data) || !isset($data->task_info)) {
                        return 0;
                    }
                    $city = $data->task_info->directionCity ?? null;
                    return $city->profit ?? 0;
                },
            ),

            new TagDefinition(
                key: '[city_profit_percent_s]',
                name: 'Форматированная прибыль по городу',
                description: 'Прибыль по городу в человекочитаемом формате (например: +0.5% или -0.3%).',
                example: '+0.5%',
                group: 'order',
                resolver: function ($data) {
                    if (!is_object($data) || !isset($data->task_info)) {
                        return 0;
                    }
                    $city = $data->task_info->directionCity ?? null;
                    return $city->profit_s ?? 0;
                },
            ),

            new TagDefinition(
                key: '[city_add_comm]',
                name: 'Дополнительная комиссия по городу',
                description: 'Дополнительная комиссия по городу (add_comm).',
                example: '0.2',
                group: 'order',
                resolver: function ($data) {
                    if (!is_object($data) || !isset($data->task_info)) {
                        return 0;
                    }
                    $city = $data->task_info->directionCity ?? null;
                    return $city->add_comm ?? 0;
                },
            ),

            new TagDefinition(
                key: '[account]',
                name: 'Реквизит назначения',
                description: 'Реквизит (счёт/карта), на который клиент должен перевести средства.',
                example: '4276 **** **** 1234',
                group: 'order',
                resolver: fn($data) => $data->transfer_to_account ?? null,
            ),

            new TagDefinition(
                key: '[from_account]',
                name: 'Реквизиты клиента (Откуда)',
                description: 'Краткое обозначение счёта/кошелька клиента, с которого он платит («отдаю»).',
                example: 'Tinkoff ****1234',
                group: 'order',
                resolver: fn($data) => $data->from_shot ?? null,
            ),

            new TagDefinition(
                key: '[to_account]',
                name: 'Реквизиты клиента (Куда)',
                description: 'Краткое обозначение счёта/кошелька, на который клиент получает средства («получаю»).',
                example: 'Binance USDT',
                group: 'order',
                resolver: fn($data) => $data->to_shot ?? null,
            ),
        ];
    }

    /**
     * Собираем map [tag => resolver] из TagDefinition.
     * Это используется существующей логикой AbstractTagProcessor/TagProcessors.
     */
    protected function tagHandlers(): array
    {
        $handlers = [];

        foreach ($this->definitions() as $definition) {
            if ($definition->resolver instanceof \Closure) {
                $handlers[$definition->key] = $definition->resolver;
            }
        }

        return $handlers;
    }


    /**
     * Возвращает эффективное значение прибыли по направлению
     * с учётом индивидуальных значений и профиля для заданного поля.
     * Всегда нормализованная строка ("0", "1", "1.5").
     *
     * @param  object|null  $direction
     * @param  string       $field        Поле в самом направлении (например: 'profit' или 'profit_s')
     * @param  string       $profileField Поле в профиле прибыли (например: 'profit' или 'profit_s')
     * @return string
     */
    protected function resolveDirectionProfitValue(?object $direction, string $field, string $profileField): string
    {
        if (!$direction) {
            return '0';
        }

        // 1. Приоритет: индивидуальная прибыль → профиль → 0
        $raw = $direction->{$field}
            ?? ($direction->profitProfile?->{$profileField} ?? null)
            ?? '0';

        $value = trim((string) $raw);
        if ($value === '' || $value === null) {
            return '0';
        }

        // заменяем запятую на точку
        $value = str_replace(',', '.', $value);

        // только корректные числа
        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            return '0';
        }

        // удаляем хвостовые нули
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return $value === '' ? '0' : $value;
    }
}
