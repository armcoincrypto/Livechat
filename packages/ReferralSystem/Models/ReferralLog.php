<?php

namespace iEXPackages\ReferralSystem\Models;

use App\Models\Filters\ReferralLogFilter;
use App\Models\Task;
use App\Models\User;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReferralLog extends Model
{
    use Filterable;

    protected $table = 'referral_log';

    protected $fillable = [
        'id_user',
        'id_referral',
        'id_referral_link',
        'id_task',
        'text',
        'bonus',
        'bonus_number',
        'fixed_bonus',
        'current_percent',

        'event_key',
        'status',
        'available_at',
        'confirmed_at',
        'reversed_at',
        'is_reversal',
        'reversal_of_id',
        'reason',
    ];

    protected $casts = [
        'bonus_number' => 'decimal:2',
        'fixed_bonus' => 'decimal:2',
        'current_percent' => 'decimal:4',

        'available_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'reversed_at' => 'datetime',
        'is_reversal' => 'boolean',
        'reversal_of_id' => 'integer',
    ];

    public function modelFilter()
    {
        return $this->provideFilter(ReferralLogFilter::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'id_referral');
    }

    public function user_admin(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'id_user');
    }

    public function tasks(): HasOne
    {
        return $this->hasOne(Task::class, 'id', 'id_task');
    }
}
