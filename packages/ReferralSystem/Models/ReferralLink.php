<?php

namespace iEXPackages\ReferralSystem\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralLink extends Model
{
    protected $table = 'referral_links';

    protected $fillable = [
        'user_id',
        'referral_program_id',
        'code',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'referral_program_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->code)) {
                $model->code = shortCodeEncode($model->user_id);
            }
        });
    }

    public function getHashAttribute()
    {
        return $this->code;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(ReferralProgram::class, 'referral_program_id');
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(ReferralRelationship::class);
    }
}
