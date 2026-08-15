<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;

class EventReserveFilter extends ModelFilter
{
    /**
     * Установночный фильтр
     */
    public function setup()
    {
        //
    }

    /**
     * Фильтровать по типу события
     *
     * @return EventReserveFilter
     */
    public function types($value)
    {
        if (! is_array($value)) {
            return $this->whereIn('type', $value);
        }

        return $this->where('type', '=', $value);
    }

    /**
     * Фильтровать по валюте
     *
     * @return EventReserveFilter
     */
    public function currencies($value)
    {
        if (! is_array($value)) {
            return $this->whereIn('id_currency', $value);
        }

        return $this->where('id_currency', '=', $value);
    }

    /**
     * Фильтровать по дате создания (От)
     *
     * @return EventReserveFilter
     */
    public function fromCreatedAt($value)
    {
        return $this->where('created_at', '>=', $value);
    }

    /**
     * Фильтровать по дате создания (До)
     *
     * @return EventReserveFilter
     */
    public function toCreatedAt($value)
    {
        return $this->where('created_at', '<=', $value);
    }
}
