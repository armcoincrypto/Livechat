<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class DirectionExchangeFilter extends ModelFilter
{
    public function isErrorRate($value)
    {
        return $this->where('is_error_rate', '=', 1);
    }

    /**
     * Техническое название
     *
     * @param $value
     * @return DirectionExchangeFilter
     */
    public function techName($value): DirectionExchangeFilter
    {
        return $this->where('tech_name', 'like', '%'.$value.'%');
    }

    /**
     * Фильтр валюты (Отдаю)
     *
     * @param $value
     * @return DirectionExchangeFilter
     */
    public function currenciesIn($value): DirectionExchangeFilter
    {
        return $this->whereIn('id_currency1', $value);
    }

    /**
     * Фильтр валюты (Получаю)
     *
     * @param $value
     * @return DirectionExchangeFilter
     */
    public function currenciesOut($value): DirectionExchangeFilter
    {
        return $this->whereIn('id_currency2', $value);
    }

    /**
     * Фильтр по активности
     *
     * @param $value
     * @return DirectionExchangeFilter
     */
    public function status($value): DirectionExchangeFilter
    {
        return $this->whereIn('status', $value);
    }

    public function groups($values)
    {
        return $this->whereIn('direction_exchange.id', function ($query) use ($values) {
            $query->select('direction_exchange_id')
                ->from('direction_exchange_group_pivot')
                ->whereIn('group_id', $values);
        });
    }


    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return DirectionExchangeFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): DirectionExchangeFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
