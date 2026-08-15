<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Illuminate\Database\Eloquent\Builder;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class OrderStatusLogFilter extends ModelFilter
{
    /**
     * Поиск по номеру заявки
     *
     * @return OrderStatusLogFilter
     */
    public function idOrder($id)
    {
        return $this->where('id_task', '=', $id);
    }

    /**
     * Фильтр по Email адресу клиента
     *
     * @return OrderStatusLogFilter
     */
    public function emailAddress($value)
    {
        return $this->whereHas('user', function (Builder $query) use ($value) {
            $query->where('email', 'like', '%'.$value.'%');
        });
    }

    /**
     * Фильтр по IP Адресу
     *
     * @return OrderStatusLogFilter
     */
    public function ipAddress($value)
    {
        return $this->whereHas('user', function (Builder $query) use ($value) {
            $query->where('ip_address', $value);
        });
    }

    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return OrderStatusLogFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): OrderStatusLogFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
