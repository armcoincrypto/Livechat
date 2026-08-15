<?php

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Illuminate\Database\Eloquent\Builder;

class TaskMessageFilter extends ModelFilter
{
    /**
     * Поиск по ID пользователя
     *
     * @return TaskMessageFilter
     */
    public function idTask($id)
    {
        return $this->where('id_task', '=', $id);
    }
}
