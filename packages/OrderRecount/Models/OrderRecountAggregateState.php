<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount\Models;

use Illuminate\Database\Eloquent\Model;

final class OrderRecountAggregateState extends Model
{
    protected $table = 'order_recount_aggregate_state';
    protected $primaryKey = 'key';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['key','last_audit_id','updated_at'];
    protected $casts = [
        'last_audit_id' => 'int',
        'updated_at' => 'datetime',
    ];
}
