<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class CompetitorRatesFilter extends ModelFilter
{
    /**
     * Фильтровать по категориям
     *
     * @param $value
     * @return CompetitorRatesFilter
     */
    public function name($value): CompetitorRatesFilter
    {
        return $this->where('name', 'like', '%'.$value.'%');
    }


    /**
     * Фильтр коду
     *
     * @param string $value
     * @return CompetitorRatesFilter
     */
    public function code(string $value): CompetitorRatesFilter
    {
        return $this->where('code', 'LIKE', "%{$value}%");
    }

    /**
     * Фильтр по группам
     *
     * @param array $ids
     * @return CompetitorRatesFilter
     */
    public function idCompetitor(array $ids): CompetitorRatesFilter
    {
        return $this->whereIn('id_competitor', $ids);
    }

    /**
     * Фильтр по активности
     *
     * @param mixed $value
     * @return CompetitorRatesFilter
     */
    public function status(mixed $value): CompetitorRatesFilter
    {
        return $this->whereIn('status', $value);
    }


    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return CompetitorRatesFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): CompetitorRatesFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
