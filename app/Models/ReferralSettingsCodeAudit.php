<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ReferralSettingsCodeAudit extends Model
{
    protected $table = 'referral_settings_code_audits';

    protected $fillable = [
        'actor_id',
        'actor_email',
        'before_code_currency_id',
        'after_code_currency_id',
        'before_fallback_codes',
        'after_fallback_codes',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'before_fallback_codes' => 'array',
        'after_fallback_codes' => 'array',
    ];

    /**
     * Пользователь, который изменил настройки.
     */
    public function actor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Код валюты ДО изменения.
     */
    public function beforeCodeCurrency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CodeCurrency::class, 'before_code_currency_id');
    }

    /**
     * Код валюты ПОСЛЕ изменения.
     */
    public function afterCodeCurrency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CodeCurrency::class, 'after_code_currency_id');
    }
}
