<?php
namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class ParserExchangeFilter extends ModelFilter
{
    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return ParserExchangeFilter
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function sortingOrder($value): ParserExchangeFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }

    /**
     * Номер карты
     *
     * @return ParserExchangeFilter
     */
    public function name($value)
    {
        return array_key_exists('checkbox_name', $this->input) ?
            $this->where('name', $value) :
            $this->where('name', 'like', '%'.$value.'%');
    }

    public function idGroup($value)
    {
        return $this->whereIn('id_group', $value);
    }
}
