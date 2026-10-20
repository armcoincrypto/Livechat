<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_subject
 * @property string|null $provider_email
 * @property bool|null $provider_email_verified
 */
class UserOAuthIdentity extends Model
{
    protected $table = 'user_oauth_identities';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_subject',
        'provider_email',
        'provider_email_verified',
    ];

    protected $casts = [
        'provider_email_verified' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
