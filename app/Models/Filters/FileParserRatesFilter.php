<?php

namespace App\Models\Filters;

use _PHPStan_18cddd6e5\Psr\Container\NotFoundExceptionInterface;
use EloquentFilter\ModelFilter;
use Psr\Container\ContainerExceptionInterface;

class FileParserRatesFilter extends ModelFilter
{
    /**
     * Фильтр по активности
     *
     * @param mixed $value
     * @return FileParserRatesFilter
     */
    public function status(mixed $value): FileParserRatesFilter
    {
        return $this->whereIn('status', $value);
    }

    /**
     * Фильтр паре
     *
     * @param string $value
     * @return FileParserRatesFilter
     */
    public function name(string $value): FileParserRatesFilter
    {
        return $this->where('name', 'LIKE', "%{$value}%");
    }

    /**
     * Фильтр коду
     *
     * @param string $value
     * @return FileParserRatesFilter
     */
    public function code(string $value): FileParserRatesFilter
    {
        return $this->where('code', 'LIKE', "%{$value}%");
    }

    /**
     * Фильтр по группам
     *
     * @param array $ids
     * @return FileParserRatesFilter
     */
    public function idGroup(array $ids): FileParserRatesFilter
    {
        return $this->whereIn('id_group', $ids);
    }

    /**
     * Сортировка по колонкам
     *
     * @param $value
     * @return FileParserRatesFilter
     * @throws ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function sortingOrder($value): FileParserRatesFilter
    {
        return $this->orderBy($value, request()->get('sorting_type') ?? 'asc');
    }
}
