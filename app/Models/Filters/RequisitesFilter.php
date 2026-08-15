<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class RequisitesFilter extends ModelFilter
{
    /**
     * Установночный фильтр
     */
    public function setup()
    {
        //
    }

    /**
     * Фильтр по ID валюты
     *
     * @return RequisitesFilter
     */
    public function idCurrencies($ids)
    {
        return $this->whereIn('id_currency', $ids);
    }

    public function idGroups($ids)
    {
        return $this->whereIn('id_group', $ids);
    }

    /**
     * Фильтр по номеру счета
     *
     * @return RequisitesFilter
     */
    public function accountNumber($value)
    {
        return $this->where('account_number', '=', $value);
    }

    /**
     * Фильтр по статусу
     *
     * @return RequisitesFilter
     */
    public function status($value)
    {
        return $this->whereIn('status', $value);
    }

    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return RequisitesFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value)
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
