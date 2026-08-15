<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class ExtraField extends Model
{
    use HasTranslations;

    protected $table = 'extra_fields';

    protected $fillable = [
        'key_id',
        'name',
        'scope',
        'attach_to_order',
        'min_char',
        'max_char',
        'obligatory_field',
        'remove_spaces',
        'start_with',
        'end_with',
        'validator_type',
        'sorting',
        'status',
    ];

    public array $translatable = [
        'name',
    ];

    protected $casts = [
        'attach_to_order' => 'bool',
        'min_char' => 'int',
        'max_char' => 'int',
        'obligatory_field' => 'int',
        'remove_spaces' => 'int',
        'sorting' => 'int',
        'status' => 'int',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(ExtraFieldValue::class, 'field_id');
    }

    public function scopeActive($q)
    {
        // status=1 выключено
        return $q->where('status', '!=', 1);
    }
}
