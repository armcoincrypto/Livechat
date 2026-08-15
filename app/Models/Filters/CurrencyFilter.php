<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;

class CurrencyFilter extends ModelFilter
{
    /**
     * Установночный фильтр
     */
    public function setup()
    {
    }

    /**
     * Фильтр по активности
     *
     * @return CurrencyFilter
     */
    public function status($value)
    {
        if (is_array($value)) {
            return $this->whereIn('status', $value);
        }

        return $this->where('status', $value);
    }

    /**
     * Фильтровать по платежным системам
     *
     * @return CurrencyFilter
     */
    public function payments($value)
    {
        if (is_array($value)) {
            return $this->whereIn('id_payment', $value);
        }

        return $this->where('id_payment', $value);
    }

    /**
     * Фильтровать по коду валют
     *
     * @return CurrencyFilter
     */
    public function codeCurrency($value)
    {
        if (is_array($value)) {
            return $this->whereIn('id_code_currency', $value);
        }

        return $this->where('id_code_currency', $value);
    }

    /**
     * Фильтровать по фильтрам валют
     *
     * @return CurrencyFilter
     */
    public function filterCurrency($value)
    {
        return $this->whereHas('filters', function ($query) use ($value) {
            if (is_array($value)) {
                $query->whereIn('filter_currency.id', $value);
            } else {
                $query->where('filter_currency.id', $value);
            }
        });
    }

    /**
     * Фильтр групп сетей
     *
     * @param $value
     * @return CurrencyFilter
     */
    public function groupNetworks($value): CurrencyFilter
    {
        if (is_array($value)) {
            return $this->whereIn('id_group_network', $value);
        }

        return $this->where('id_group_network', $value);
    }

    /**
     * Фильтровать по группам
     *
     * @return CurrencyFilter
     */
    public function idGroup($value)
    {
        if (is_array($value)) {
            return $this->whereIn('id_group', $value);
        }

        return $this->where('id_group', $value);
    }

    /**
     * Сортировка по колонкам
     *
     * @return CurrencyFilter
     */
    public function sortingOrder($value)
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }

    public function designationXml($value)
    {
        return $this->where('designation_xml', 'LIKE', "{$value}%");
    }
}
