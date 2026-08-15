<?php

namespace iEXPackages\ReferralSystem\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class ReferralProgram extends Model
{
    use HasTranslations, SoftDeletes;

    protected $fillable = [
        'name',
        'lifetime_minutes',
        'title',
        'description',
        'percent'
    ];

    protected array $translatable = [
        'title',
        'description',
    ];

    public function links(): HasMany
    {
        return $this->hasMany(ReferralLink::class, 'referral_program_id', 'id');
    }
}
