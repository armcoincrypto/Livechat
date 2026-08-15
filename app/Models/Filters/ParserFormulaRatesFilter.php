<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class ParserFormulaRatesFilter extends ModelFilter
{
    /**
     * Фильтровать по категориям
     *
     * @param $value
     * @return ParserFormulaRatesFilter
     */
    public function name($value): ParserFormulaRatesFilter
    {
        return $this->where('name', 'like', '%'.$value.'%');
    }


    /**
     * Фильтровать по категориям
     *
     * @param $value
     * @return ParserFormulaRatesFilter
     */
    public function title($value): ParserFormulaRatesFilter
    {
        return $this->where('title', 'like', '%'.$value.'%');
    }

    /**
     * Фильтр по активности
     *
     * @param mixed $value
     * @return ParserFormulaRatesFilter
     */
    public function status(mixed $value): ParserFormulaRatesFilter
    {
        return $this->whereIn('status', $value);
    }


    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return ParserFormulaRatesFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): ParserFormulaRatesFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
