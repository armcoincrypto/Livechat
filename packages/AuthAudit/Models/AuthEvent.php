<?php
declare(strict_types=1);

namespace iEXPackages\AuthAudit\Models;

use App\Models\Filters\AuthEventFilter;
use App\Models\User;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AuthEvent extends Model
{
    use Filterable;

    protected $table = 'auth_audit_events';

    protected $fillable = [
        'user_id','email','guard','channel','event','result','reason_code','message',
        'ip','ip_prev','user_agent','device_id','is_new_device',
        'browser','os','device','country','city','iso_code','meta',
        'session_id','session_prev_id',
    ];

    protected $casts = [
        'is_new_device' => 'bool',
        'meta' => 'array',
    ];

    public function modelFilter()
    {
        return $this->provideFilter(AuthEventFilter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
