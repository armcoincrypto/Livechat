<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class DirectionExchangeGroup extends Model
{
    protected $table = 'direction_exchange_groups';

    protected $fillable = [
        'name',
        'sorting',
        'status',
    ];

    public function directions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            DirectionExchange::class,
            'direction_exchange_group_pivot',
            'group_id',
            'direction_exchange_id'
        )->withTimestamps();
    }
}
