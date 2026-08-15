<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class DirectionExchangeScope implements Scope
{
    /**
     * Применение заготовки к данному построителю запросов Eloquent.
     *
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->where('status', '!=', 2);
    }
}
