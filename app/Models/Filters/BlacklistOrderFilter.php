<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;

class BlacklistOrderFilter extends ModelFilter
{
    /**
     * Искать значение
     *
     * @return BlacklistOrderFilter
     */
    public function fromValue($value)
    {
        return $this->where('value', '=', $value);
    }

    /**
     * Искать значение
     *
     * @return BlacklistOrderFilter
     */
    public function types($value)
    {
        return $this->whereIn('type', $value);
    }

    /**
     * Фильтр по дате создания (От)
     *
     * @return BlacklistOrderFilter
     */
    public function fromCreatedAt($value)
    {
        return $this->where('created_at', '>=', $value);
    }

    /**
     * Фильтр по дате создания (До)
     *
     * @return BlacklistOrderFilter
     */
    public function toCreatedAt($value)
    {
        return $this->where('created_at', '<=', $value);
    }
}
