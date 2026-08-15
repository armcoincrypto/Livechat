<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ExtraFieldValue extends Model
{
    protected $table = 'extra_field_values';

    protected $fillable = [
        'field_id',
        'owner_type',
        'owner_id',
        'field_value',
    ];

    protected $casts = [
        'field_id' => 'int',
        'owner_id' => 'int',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(ExtraField::class, 'field_id');
    }

    public function owner(): MorphTo
    {
        return $this->morphTo(null, 'owner_type', 'owner_id');
    }
}
