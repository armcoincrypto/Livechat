<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;

class ContestsUserFilter extends ModelFilter
{
    /**
     * Искать значение
     */
    public function email($value): ContestsUserFilter
    {
        return $this->where('email', $value);
    }
}
