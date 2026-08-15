# 📘 Payments Engine — Гайд по `config.php`

> **Назначение:** этот документ — эталон формата `config.php` для платёжных шлюзов в движке **Payments**.
> Подходит для **crypto / fiat / merchant** шлюзов, масштабируется и готов к UI, cron, callback.

---

## 🧭 Оглавление
1. [Общая структура](#общая-структура)
2. [`schema`](#schema)
3. [`meta`](#meta)
4. [`capabilities`](#capabilities)
5. [`operations`](#operations)
6. [`inputs`](#inputs)
   - [`inputs.merchant`](#inputsmerchant)
   - [`inputs.merchant.callback`](#inputsmerchantcallback)
   - [`inputs.merchant.fields`](#inputsmerchantfields)
   - [`inputs.merchant.options_fields`](#inputsmerchantoptions_fields)
   - [`inputs.pay`](#inputspay)
7. [Архитектурные правила](#архитектурные-правила)
8. [Частые паттерны](#частые-паттерны)
9. [Итог](#итог)

---

## Общая структура

```php
return [
    'schema' => 1,
    'meta' => [...],
    'capabilities' => [...],
    'operations' => [...],
    'inputs' => [...],
];
```

---

## `schema`

```php
'schema' => 1,
```

**Описание:** версия формата `config.php`.

- Используется для обратной совместимости.
- Меняется **только** при серьёзных изменениях структуры.

---

## `meta`

```php
'meta' => [
    'name'        => 'AppexBit',
    'alias'       => 'appexbit',
    'version'     => '1.0.0',
    'description' => 'Шлюз AppexBit для приёма платежей',
    'category'    => 'fiat',
    'sorting'     => 10,
    'recommended' => true,
    'group'       => 'Рекомендуемые', // опционально
],
```

### Ключи

| Ключ | Описание |
|---|---|
| `name` | Отображаемое имя шлюза |
| `alias` | Уникальный системный идентификатор (lowercase, без `_`) |
| `version` | Версия адаптера шлюза |
| `description` | Описание для UI / документации |
| `category` | Тип шлюза: `crypto`, `fiat`, `merchant` |
| `sorting` | Порядок отображения в UI |
| `recommended` | Пометка «рекомендуемый» |
| `group` | Группа в UI (опционально) |

---

## `capabilities`

```php
'capabilities' => [
    'incoming' => true,
    'outgoing' => false,

    'features' => [
        'is_crypto'         => false,
        'supports_checkout' => true,
        'supports_balance'  => false,
        'auto_operations'   => true,
        'polling' => [
            'incoming' => true,
            'outgoing' => false,
        ],
    ],

    'callbacks' => [
        'ipn'        => true,
        'return_url' => false,
        'cancel_url' => false,
    ],
],
```

### Основные флаги

| Ключ | Назначение |
|---|---|
| `incoming` | Поддерживает приём платежей |
| `outgoing` | Поддерживает выплаты |

### `features`

| Ключ | Описание |
|---|---|
| `is_crypto` | Крипто-шлюз |
| `supports_checkout` | Есть checkout / форма оплаты |
| `supports_balance` | Есть API баланса |
| `auto_operations` | Разрешить магические методы (`__call`) |
| `polling.incoming` | Polling входящих платежей |
| `polling.outgoing` | Polling выплат |

> 💡 **Источник истины для polling** — наличие операций `fetch_payment` / `fetch_payout`.

### `callbacks`

| Ключ | Описание |
|---|---|
| `ipn` | Поддерживается IPN / webhook |
| `return_url` | Возврат пользователя |
| `cancel_url` | Отмена |

---

## `operations`

```php
'operations' => [
    'purchase' => [
        'type'           => 'incoming',
        'label'          => 'Создание платежа',
        'description'    => 'Создание платёжных реквизитов',
        'request_class'  => PurchaseRequest::class,
        'response_class' => PurchaseResponse::class,
    ],
],
```

### Поддерживаемые операции

| Ключ | Назначение |
|---|---|
| `purchase` | Создание платежа |
| `complete_purchase` | Callback / IPN |
| `fetch_payment` | Polling входящих |
| `payout` | Выплата |
| `fetch_payout` | Polling выплат |
| `options` | Загрузка опций (select) |

### Поля операции

| Ключ | Описание |
|---|---|
| `type` | `incoming` / `outgoing` |
| `label` | Название для UI |
| `description` | Описание |
| `request_class` | Класс запроса |
| `response_class` | Класс ответа |

---

## `inputs`

```php
'inputs' => [
    'merchant' => [...],
    'pay' => [...],
],
```

---

## `inputs.merchant`

```php
'merchant' => [
    'callback' => [...],
    'fields' => [...],
    'options_fields' => [...],
],
```

### `inputs.merchant.callback`

```php
'callback' => [
    'enabled' => true,
    'order_id_field' => 'externalId',
    'route_name' => 'merchant.receive_money',
    'ip_whitelist_enabled' => true,
],
```

| Ключ | Назначение |
|---|---|
| `enabled` | Callback разрешён |
| `order_id_field` | Поле заявки в payload |
| `route_name` | Laravel route name |
| `ip_whitelist_enabled` | Проверка IP |

---

### `inputs.merchant.fields`

```php
[
    'type' => 'input',
    'label' => 'API Key',
    'key' => 'api_key',
    'is_hidden' => true,
    'value_type' => 'string',
    'required' => true,
]
```

| Ключ | Описание |
|---|---|
| `type` | `input` / `select` |
| `label` | Название поля |
| `key` | Ключ в Vault |
| `is_hidden` | Скрытое поле |
| `value_type` | `string`, `int`, `bool` |
| `required` | Обязательное |

---

### `inputs.merchant.options_fields`

```php
[
    'type' => 'select',
    'label' => 'Код валюты',
    'key' => 'wallet_unique_id',
    'options_api_method' => 'getCurrencies',
]
```

| Ключ | Описание |
|---|---|
| `options` | Статические варианты |
| `options_file` | JSON-файл |
| `options_api_method` | Метод `OptionsRequest` |

---

## `inputs.pay`

Используется для **выплат** (`GatewayPayment`). Структура полностью аналогична `inputs.merchant`.

---

## Архитектурные правила

- ❌ Никакой бизнес-логики в `sendData()`
- ✅ Вся логика успеха — в `Response`
- ✅ Все ID выплат — через `getWithdrawalId()`
- ✅ Polling / callback — через интерфейсы
- ✅ `config.php` — декларативный, без логики

---

## Частые паттерны

- **Отложенный успех (fiat payout):**
  - `isSuccessful() === true`
  - `isDeferredSuccess() === true`
  - включается cron polling

- **Callback (incoming):**
  - `inputs.merchant.callback.enabled = true`
  - используется `order_id_field`

---

## Итог

Этот формат `config.php`:
- масштабируется на десятки шлюзов,
- одинаково работает для crypto и fiat,
- удобен для UI, cron, callback,
- минимизирует дублирование кода,
- готов для долгосрочной поддержки.

