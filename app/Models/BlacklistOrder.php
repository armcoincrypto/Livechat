<?php

namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;

class BlacklistOrder extends Model
{
    use Filterable;

    protected $table = 'blacklist_order';

    protected $fillable = [
        'value',
        'type',
        'text',
        'is_bestchange',
        'is_iex',
        'hash_id',
        'id_task'
    ];

    public function modelFilter()
    {
        return $this->provideFilter(\App\Models\Filters\BlacklistOrderFilter::class);
    }
}
