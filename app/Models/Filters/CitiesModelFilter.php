<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class CitiesModelFilter extends ModelFilter
{
    /**
     * Поиск по названию
     *
     * @param $value
     * @return CitiesModelFilter
     */
    public function name($value): CitiesModelFilter
    {
        return array_key_exists('checkbox_name', $this->input) ?
            $this->where('name->'.app()->getLocale(), $value) :
            $this->where('name->'.app()->getLocale(), 'like', '%'.$value.'%');
    }

    /**
     * Искать Обозначение XML
     *
     * @param $value
     * @return CitiesModelFilter
     */
    public function designationXml($value): CitiesModelFilter
    {
        return array_key_exists('checkbox_designation_xml', $this->input) ?
            $this->where('designation_xml', $value) :
            $this->where('designation_xml', 'like', '%'.$value.'%');
    }

    /**
     * Фильтр по активности
     *
     * @param mixed $value
     * @return CitiesModelFilter
     */
    public function status(mixed $value): CitiesModelFilter
    {
        return $this->whereIn('status', $value);
    }

    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return CitiesModelFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): CitiesModelFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
