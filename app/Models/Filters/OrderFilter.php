<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class OrderFilter extends ModelFilter
{
    protected $allowedSorting = [
        'sorting_ids',
        'sorting_status',
        'sorting_created_at',
        'sorting_updated_at',
        'sorting_order_amount',
    ];

    /**
     * Установночный фильтр только по выбраннным статусам
     */
    public function setup()
    {
        $this->where('is_archive', '=', 0);

        // Не отображать спам заявки
        if (iEXSetting('order_is_allow_spam') == 0) {
            $this->where('is_spam', '=', 0);
        }

        //        // Допускаем только выбранные статусы
        //        $order = explode(',', iEXSetting('order_priority'));
        //        if(count($order) > 0)
        //            $this->whereIn('status', $order);

        // Сортировка по позициям
        if (! empty(iex_sorting_order('sorting_order_id'))) {
            $this->orderBy('id', iex_sorting_order('sorting_order_id'));
        }

        // Сортировка по статусу
        if (! empty(iex_sorting_order('sorting_order_status'))) {
            $this->orderBy('status', iex_sorting_order('sorting_order_status'));
        }

        // Сортировка по дате создания
        if (! empty(iex_sorting_order('sorting_created_at'))) {
            $this->orderBy('created_at', iex_sorting_order('sorting_created_at'));
        }

        // Сортировка по дате посл. Обновления
        if (! empty(iex_sorting_order('sorting_updated_at'))) {
            $this->orderBy('updated_at', iex_sorting_order('sorting_updated_at'));
        }

    }

    /**
     * Поиск по ID
     *
     * @return OrderFilter
     */
    public function id($id)
    {
        if (is_numeric($id)) {
            return $this->where('id', '=', $id);
        }
        $ids = explode('-', $id);

        return $this->whereBetween('id', $ids);
    }

    /**
     * Поиск по Public ID
     *
     * @return OrderFilter
     */
    public function IdPublic($id)
    {
        if (is_numeric($id)) {
            return $this->where('public_id', '=', $id);
        }
        $ids = explode('-', $id);

        return $this->whereBetween('public_id', $ids);
    }

    /**
     * Фильтр по статусам
     */
    public function status($ids): self
    {
        if ($ids === null || $ids === '') {
            return $this;
        }

        if (!is_array($ids)) {
            return $this->where('status', (int) $ids);
        }

        if (!$ids) {
            return $this;
        }

        return $this->whereIn(
            'status',
            array_map('intval', $ids)
        );
    }

    /**
     * Фильтр по статусам платежей через мерчант
     *
     * @return OrderFilter
     */
    public function merchantOverpayment($ids)
    {
        return $this->whereIn('merchant_overpayment', $ids);
    }

    /**
     * Поиск по ID (От)
     *
     * @return OrderFilter
     */
    public function fromOrderId($id)
    {
        return $this->where('id', '>=', $id);
    }

    /**
     * Поиск по ID (Да)
     *
     * @return OrderFilter
     */
    public function toOrderId($id)
    {
        return $this->where('id', '<=', $id);
    }

    /**
     * Минимальная сумма (Отдаю)
     *
     * @return OrderFilter
     */
    public function minAmountIn($value)
    {
        return $this->where('give_price', '>=', $value);
    }

    /**
     * Максимальная сумма (Отдаю)
     *
     * @return OrderFilter
     */
    public function maxAmountIn($value)
    {
        return $this->where('give_price', '<=', $value);
    }

    /**
     * Минимальная сумма (Получаю)
     */
    public function minAmountOut($value): OrderFilter
    {
        return $this->where('receiving_price', '>=', $value);
    }

    /**
     * Максимальная сумма (Получаю)
     */
    public function maxAmountOut($value): OrderFilter
    {
        return $this->where('receiving_price', '<=', $value);
    }

    /**
     * Фильтр по дате создания
     */
    public function createdAt($value): OrderFilter
    {
        return $this->whereDate('created_at', '=', Carbon::parse($value)->toDateString());
    }

    /**
     * Фильтр по дате обновления
     */
    public function updatedAt($value): OrderFilter
    {
        return $this->whereDate('updated_at', '=', Carbon::parse($value)->toDateString());
    }

    /**
     * Фильтр по дате создания (От)
     */
    public function fromCreatedAt($value): OrderFilter
    {
        return $this->whereDate('created_at', '>=', Carbon::parse($value)->toDateString());
    }

    /**
     * Фильтр по дате создания (До)
     */
    public function toCreatedAt($value): OrderFilter
    {
        return $this->whereDate('created_at', '<=', Carbon::parse($value)->toDateString());
    }

    /**
     * Фильтр по дате обновления (От)
     */
    public function fromUpdatedAt($value): OrderFilter
    {
        return $this->whereDate('updated_at', '>=', Carbon::parse($value)->toDateString());
    }

    /**
     * Фильтр по дате обновления (До)
     */
    public function toUpdatedAt($value): OrderFilter
    {
        return $this->whereDate('updated_at', '<=', Carbon::parse($value)->toDateString());
    }

    /**
     * Фильтр по направлениям
     */
    public function directionExchange($values): OrderFilter
    {
        if (! is_array($values)) {
            return $this->whereIn('id_direction_exchange', $values);
        }

        return $this->where('id_direction_exchange', $values);
    }

    /**
     * Фильтр по валюте (Отдаю)
     *
     * @return OrderFilter|Builder
     */
    public function currenciesIn($value): OrderFilter
    {
        if (is_array($value)) {
            return $this->whereHas('direction_exchange', function (Builder $query) use ($value) {
                $query->whereIn('id_currency1', $value);
            });
        }

        return $this->whereHas('direction_exchange', function (Builder $query) use ($value) {
            $query->where('id_currency1', '=', $value);
        });
    }

    public function isNewMessages($value)
    {
        if (! empty($value)) {
            return $this->whereHas('task_messages', function ($builder) {
                $builder->where('is_view', '=', 0);
            });
        }
    }

    /**
     * Фильтр по валюте (Получаю)
     *
     * @return OrderFilter|Builder
     */
    public function currenciesOut($value): OrderFilter
    {
        if (is_array($value)) {
            return $this->whereHas('direction_exchange', function (Builder $query) use ($value) {
                $query->whereIn('id_currency2', $value);
            });
        }

        return $this->whereHas('direction_exchange', function (Builder $query) use ($value) {
            $query->where('id_currency2', '=', $value);
        });
    }

    /**
     * Фильтры по Имени партнера
     *
     * @return OrderFilter|Builder
     */
    public function partnerName($value): OrderFilter
    {
        return $this->whereHas('user.fromReferral.referral_link.user', function (Builder $query) use ($value) {
            array_key_exists('checkbox_partner_name', $this->input) ?
                $query->where('name', $value) :
                $query->where('name', 'like', '%'.$value.'%');
        });
    }

    /**
     * IP Адрес партнера
     *
     * @return OrderFilter|Builder
     */
    public function partnerIpAddress($value): OrderFilter
    {
        return $this->whereHas('user.fromReferral.referral_link.user', function (Builder $query) use ($value) {
            $query->where('ip_address', '=', $value);
        });
    }

    /**
     * Фильтры по E-mail партнера
     *
     * @return OrderFilter|Builder
     */
    public function partnerEmail($value)
    {
        return $this->whereHas('user.fromReferral.referral_link.user', function (Builder $query) use ($value) {
            array_key_exists('checkbox_partner_email', $this->input) ?
                $query->where('email', $value) :
                $query->where('email', 'like', '%'.$value.'%');
        });
    }

    /**
     * Фильтры по Реферальному хэшу
     *
     * @return OrderFilter|Builder
     */
    public function referralHash($value)
    {
        return $this->whereHas('user.fromReferral.referral_link', function (Builder $query) use ($value) {
            $query->where('code', $value);
        });
    }

    /**
     * Фильтры по ID партнера
     *
     * @return OrderFilter|Builder
     */
    public function partnerId($value)
    {
        return $this->whereHas('user.fromReferral.referral_link.user', function (Builder $query) use ($value) {
            $query->where('id', $value);
        });
    }

    public function idPaymentRequisites($id)
    {
        return $this->where('id_payment_requisites', (int) $id);
    }

    /**
     * Фильтр по платежной системе (Отдаю)
     *
     * @return OrderFilter|Builder
     */
    public function paymentIn($value)
    {
        if (is_array($value)) {
            return $this->whereHas('direction_exchange.currency1', function (Builder $query) use ($value) {
                $query->whereIn('id_payment', $value);
            });
        }

        return $this->whereHas('direction_exchange.currency1', function (Builder $query) use ($value) {
            $query->where('id_payment', '=', $value);
        });
    }

    /**
     * Фильтр по платежной системе (Получаю)
     *
     * @return OrderFilter|Builder
     */
    public function paymentOut($value)
    {
        if (is_array($value)) {
            return $this->whereHas('direction_exchange.currency2', function (Builder $query) use ($value) {
                $query->whereIn('id_payment', $value);
            });
        }

        return $this->whereHas('direction_exchange.currency2', function (Builder $query) use ($value) {
            $query->where('id_payment', '=', $value);
        });
    }

    /**
     * Фильтр по коду валют (Отдаю)
     *
     * @return OrderFilter|Builder
     */
    public function codeCurrencyIn($value)
    {
        if (is_array($value)) {
            return $this->whereHas('direction_exchange.currency1', function (Builder $query) use ($value) {
                $query->whereIn('id_code_currency', $value);
            });
        }

        return $this->whereHas('direction_exchange.currency1', function (Builder $query) use ($value) {
            $query->where('id_code_currency', '=', $value);
        });
    }

    /**
     * Фильтр по коду валют (Получаю)
     *
     * @return OrderFilter|Builder
     */
    public function codeCurrencyOut($value)
    {
        if (is_array($value)) {
            return $this->whereHas('direction_exchange.currency2', function (Builder $query) use ($value) {
                $query->whereIn('id_code_currency', $value);
            });
        }

        return $this->whereHas('direction_exchange.currency2', function (Builder $query) use ($value) {
            $query->where('id_code_currency', '=', $value);
        });
    }

    /**
     * Фильтр по альтернативных кодов валют  (Отдаю)
     *
     * @return OrderFilter|Builder
     */
    public function designationIn($value)
    {
        if (is_array($value)) {
            return $this->whereHas('direction_exchange.currency1', function (Builder $query) use ($value) {
                $query->whereIn('designation_xml', $value);
            });
        }

        return $this->whereHas('direction_exchange.currency1', function (Builder $query) use ($value) {
            $query->where('designation_xml', '=', $value);
        });
    }

    /**
     * Фильтр по альтернативных кодов валют  (Получаю)
     *
     * @return OrderFilter|Builder
     */
    public function designationOut($value)
    {
        if (is_array($value)) {
            return $this->whereHas('direction_exchange.currency2', function (Builder $query) use ($value) {
                $query->whereIn('designation_xml', $value);
            });
        }

        return $this->whereHas('direction_exchange.currency2', function (Builder $query) use ($value) {
            $query->where('designation_xml', '=', $value);
        });
    }

    /**
     * Фильтрованные валюты (Отдаю)
     *
     * @return OrderFilter|Builder
     */
    public function filterCurrencyIn($value)
    {
        if (is_array($value)) {
            if (empty($value)) {
                return $this; // пустой массив — ничего не фильтруем
            }
            return $this->whereHas('direction_exchange.currency1.filters', function (Builder $query) use ($value) {
                $query->whereIn('filter_currency.id', $value);
            });
        }

        return $this->whereHas('direction_exchange.currency1.filters', function (Builder $query) use ($value) {
            $query->where('filter_currency.id', '=', $value);
        });
    }

    /**
     * Фильтрованные валюты (Получаю)
     *
     * @return OrderFilter|Builder
     */
    public function filterCurrencyOut($value)
    {
        if (is_array($value)) {
            if (empty($value)) {
                return $this; // пустой массив — ничего не фильтруем
            }
            return $this->whereHas('direction_exchange.currency2.filters', function (Builder $query) use ($value) {
                $query->whereIn('filter_currency.id', $value);
            });
        }

        return $this->whereHas('direction_exchange.currency2.filters', function (Builder $query) use ($value) {
            $query->where('filter_currency.id', '=', $value);
        });
    }

    /**
     * Фильтр по номеру денежного перевода
     *
     * @param  null  $value
     * @return OrderFilter|Builder
     */
    public function numTransaction($value = null)
    {
        return $this->whereHas('task_info', function (Builder $query) use ($value) {
            $query->where('num_transaction', $value);
        });
    }

    public function uniqueSecurityCode($value = null)
    {
        return $this->where('unique_security_code', '=', $value);
    }

    /**
     * Фильтр по отключенным партнерским выплатам
     *
     * @return OrderFilter|Builder
     */
    public function isNotPartner()
    {
        return $this->whereHas('task_info', function (Builder $query) {
            $query->where('is_not_partner', '=', 1);
        });
    }

    /**
     * Фильтр по IP Адресу
     *
     * @return OrderFilter
     */
    public function ipAddress($value)
    {
        return $this->where('ip', '=', $value);
    }

    /**
     * Фильтр по ID клиента
     *
     * @return OrderFilter
     */
    public function idUser($value)
    {
        return $this->where('id_user', '=', $value);
    }

    /**
     * Количество заявок (От)
     *
     * @return OrderFilter|Builder
     */
    public function fromOrderNum($value)
    {
        return $this->whereHas('user', function (Builder $query) use ($value) {
            $query->where('order_num', '>=', $value);
        });
    }

    /**
     * Количество заявок (До)
     *
     * @return OrderFilter|Builder
     */
    public function toOrderNum($value)
    {
        return $this->whereHas('user', function (Builder $query) use ($value) {
            $query->where('order_num', '<=', $value);
        });
    }

    /**
     * Фильтр заявок по забаненному клиенту
     *
     * @return OrderFilter|Builder
     */
    public function isBan()
    {
        return $this->whereHas('user', function (Builder $query) {
            $query->onlyBanned();
        });
    }

    /**
     * Фильтр заявок по верифицированному клиенту
     *
     * @return OrderFilter|Builder
     */
    public function isVerified()
    {
        return $this->whereHas('user', function (Builder $query) {
            $query->whereNotNull('email_verified_at');
        });
    }

    /**
     * Фильтр заявок по telegram
     *
     * @return OrderFilter|Builder
     */
    public function telegram(string $value)
    {
        return $this->whereHas('user', function (Builder $query) use ($value) {
            $query->where('telegram', '=', $value);
        });
    }

    /**
     * Фильтр по Email адресу клиента
     *
     * @return OrderFilter
     */
    public function emailAddress($value)
    {
        return $this->whereHas('user', function (Builder $query) use ($value) {
            array_key_exists('checkbox_full_email', $this->input) ?
                $query->where('email', $value) :
                $query->where('email', 'like', '%'.$value.'%');
        });
    }

    /**
     * Фильтр по имени клиента
     *
     * @return OrderFilter
     */
    public function userName($value)
    {
        return $this->whereHas('user', function (Builder $query) use ($value) {
            array_key_exists('checkbox_full_name', $this->input) ?
                $query->where('name', $value) :
                $query->where('name', 'like', '%'.$value.'%');
        });
    }

    /**
     * Фильтр по провайдерам
     *
     * @param  string  $value
     * @return OrderFilter|Builder
     */
    public function userProvider($value)
    {
        return $this->whereHas('user', function (Builder $query) use ($value) {
            $query->whereIn('provider', $value);
        });
    }

    /**
     * Фильтр (Со счета)
     *
     * @return OrderFilter
     */
    public function fromShot($value)
    {
        return $this->where('from_shot', 'like', '%'.$value.'%');
    }

    /**
     * Фильтр (На счет)
     *
     * @return OrderFilter
     */
    public function toShot($value)
    {
        return $this->where('to_shot', '=', $value);
    }

    /**
     * Фильтр по то кто создал заявку "Новичок" или "Постоянный клиент"
     *
     * @return OrderFilter|Builder
     */
    public function newbie($value)
    {
        return $this->whereHas('task_info', function (Builder $query) {
            $query->where('newbie', '=', 1);
        });
    }

    /**
     * Замороженные средства.
     *
     * @return OrderFilter
     */
    public function isFrozen()
    {
        return $this->where('is_frozen', '=', 1);
    }

    /**
     * Арестованные средства.
     *
     * @return OrderFilter
     */
    public function isFreezeScam()
    {
        return $this->where('is_freeze_scam', '=', 1);
    }

    /**
     * Статус отправки чека
     *
     * @return OrderFilter
     */
    public function checkStatus()
    {
        return $this->where('check_status', '=', 1);
    }

    /**
     * Фильтр записей по странам
     *
     * @return OrderFilter|Builder
     */
    public function userCountry($value)
    {
        if (is_array($value)) {
            return $this->whereHas('task_info', function (Builder $query) use ($value) {
                $query->whereIn('country', $value);
            });
        }

        return $this->whereHas('task_info', function (Builder $query) use ($value) {
            $query->where('country', '=', $value);
        });
    }

    /**
     * Фильтр по девайсам
     *
     * @return OrderFilter|Builder
     */
    public function devices($value)
    {
        if (is_array($value)) {
            return $this->whereHas('task_info', function (Builder $query) use ($value) {
                $query->whereIn('device', $value);
            });
        }

        return $this->whereHas('task_info', function (Builder $query) use ($value) {
            $query->where('device', '=', $value);
        });
    }

    /**
     * Фильтр по ID транзакции
     *
     * @return OrderFilter|Builder
     */
    public function idTransaction($value)
    {
        return $this->whereHas('transaction', function (Builder $query) use ($value) {
            $query->where('transaction', '=', $value);
        });
    }

    /**
     * Фильтр по ID Транзакции мерчанта
     *
     * @return OrderFilter|Builder
     */
    public function idTransactionMerchant($value)
    {
        return $this->whereHas('history_payment_transaction', function (Builder $query) use ($value) {
            $query->where('transfer', '=', $value);
        });
    }

    /**
     * Фильтр по ID автовыплаты транзакции
     *
     * @return OrderFilter|Builder
     */
    public function idTransactionAutoPayment($value)
    {
        return $this->whereHas('wallet_history', function (Builder $query) use ($value) {
            $query->where('txid', '=', $value);
        });
    }

    /**
     * Фильтр по ID проверки оплаты транзакции
     *
     * @return OrderFilter|Builder
     */
    public function idTransactionCheckPayment($value)
    {
        return $this->whereHas('wallet_transaction', function (Builder $query) use ($value) {
            $query->where('txid', '=', $value);
        });
    }

    /**
     * Только заявки из Telegram
     *
     * @return OrderFilter|Builder
     */
    public function isTelegram()
    {
        return $this->whereNotNull('telegram_id');
    }
}
