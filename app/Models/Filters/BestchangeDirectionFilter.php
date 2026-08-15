<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class BestchangeDirectionFilter extends ModelFilter
{
    /**
     * Фильтр по активности
     *
     * @param mixed $value
     * @return GatewayPaymentFilter
     */
    public function status(mixed $value): BestchangeDirectionFilter
    {
        return $this->whereIn('status', $value);
    }

    /**
     * Фильтр по отдаю
     *
     * @param array $ids
     * @return BestchangeDirectionFilter
     */
    public function idCurrencyIn(array $ids): BestchangeDirectionFilter
    {
        return $this->whereIn('id_currency_in', $ids);
    }

    /**
     * Фильтр по получаю
     *
     * @param array $ids
     * @return BestchangeDirectionFilter
     */
    public function idCurrencyOut(array $ids): BestchangeDirectionFilter
    {
        return $this->whereIn('id_currency_out', $ids);
    }


    /**
     * Фильтр по направлению
     *
     * @param int $id
     * @return BestchangeDirectionFilter
     */
    public function idDirectionExchange(int $id): BestchangeDirectionFilter
    {
        return $this->where('id_direction_exchange', $id);
    }

    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return GatewayPaymentFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): BestchangeDirectionFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
