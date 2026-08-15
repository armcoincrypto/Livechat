<?php

namespace App\Models;

use App\Jobs\Notifications\EmailResetPasswordJob;
use App\Jobs\VerifyEmailJob;
use App\Notifications\ResetPasswordNotification;
use Cog\Contracts\Ban\Bannable as BannableContract;
use Cog\Laravel\Ban\Traits\Bannable;
use DateTimeInterface;
use EloquentFilter\Filterable;
use iEXPackages\ReferralSystem\Models\ReferralRelationship;
use iEXPackages\ReferralSystem\Traits\ReferralsMember;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements BannableContract, HasLocalePreference, MustVerifyEmail
{
    use Bannable,
        Filterable,
        HasApiTokens,
        HasRoles,
        Notifiable,
        ReferralsMember;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'telegram',
        'last_login_at', // Последний вход
        'last_logout_at', // Последний выход
        'last_activity_at', // Последняя активность
        'google2fa_secret',
        'banned_at',
        'language',
        'safe_input',
        'restriction',
        'username',
        'ip_address',
        'deactivation',
        'is_order',
        'auto_withdrawal',
        'current_page',
        'is_unique_user',
        'order_num',
        'ip_changed',
        'role_expired_at',
        'is_follow_referral',
        'is_notify_email',
        'is_password_reset',
        'user_agent',
        'is_pay_referral',
        'is_backup',
        'num_auth',
        'is_verification',
        'provider',
        'provider_id',
        'is_download_codes',
        'backup_code_secret',
        'phone_code',
        'admin_phone',
        'phone_hash',
        'user_browser',
        'user_device',
        'user_style',
        'restapi_key',
        'is_enabled_restapi',
        'is_guest',
        'personal_discount',
        'personal_ref_discount',
        'max_ref_discount',
        'security_order_page_code',
        'is_enable_order_paginate',
        'is_verify_account',
        'is_hidden_ip_address',
        'email_verified_at',
        'id_reward_program',
        'order_total_exchanges',
        'is_active_role',
        'logged_ip_address',
        'allowed_ip_addresses',
        'is_frontend',
        'partner_method',
        'limit_profile_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'google2fa_secret',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_follow_referral' => 'boolean',
        'last_login_at' => 'datetime',
        'last_logout_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $dates = [
        'created_at', 'updated_at', 'role_expired_at',
    ];


    /**
     * Установка нижнего регистра для почты
     *
     * @param $value
     * @return void
     */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = strtolower($value);
    }

    /**
     * Получение почты
     *
     * @param $value
     * @return string
     */
    public function getEmailAttribute($value): string
    {
        return strtolower($value);
    }


    //Add the below function
    public function messages()
    {
        return $this->hasMany(TaskMessage::class);
    }

    /**
     * Отправляем уведомление для подтверждения почты
     */
    public function sendEmailVerificationNotification(): void
    {
        if ((int) iEXSetting('is_user_verified_email') == 1) {
            VerifyEmailJob::dispatch($this)
                ->delay(now()->addSeconds(2))->onQueue('low');
        }
    }

    public function modelFilter()
    {
        return $this->provideFilter(\App\Models\Filters\UserFilter::class);
    }

    public function getIsAdminAttribute()
    {
        return true;
    }

    public function user_balance()
    {
        return $this->hasOne(UserBalance::class, 'id_user', 'id');
    }

    public function withdrawal_request()
    {
        return $this->hasOne(WithdrawalRequest::class, 'id_user', 'id')->orderByDesc('id');
    }

    public function unpaid_withdrawal_request()
    {
        return $this->hasOne(WithdrawalRequest::class, 'id_user', 'id')->where('status', '=', 0);
    }

    public function isOnline()
    {
        return Cache::has('user-is-online-'.$this->id);
    }

    /**
     * Route notifications for the Slack channel.
     *
     * @return string
     */
    public function routeNotificationForSlack()
    {
        return config('services.slack.webhook_url');
    }

    /**
     * Ecrypt the user's google_2fa secret.
     *
     * @param  string  $value
     * @return string
     */
    public function setGoogle2faSecretAttribute($value)
    {
        $this->attributes['google2fa_secret'] = encrypt($value);
    }

    /**
     * Decrypt the user's google_2fa secret.
     *
     * @param  string  $value
     * @return string
     */
    public function getGoogle2faSecretAttribute($value)
    {
        try {
            if(!empty($value)) {
                return decrypt($value);
            }
        }catch(\Exception $e) {
            //
        }

        return null;
    }

    public function managerHistory($type = 'count', $time = null)
    {
        return (float) \App\Models\Task::where([
            ['id_who_completed', $this->getAttribute('id')],
            ['status', 4]])
            ->whereDate('updated_at', '=', \Illuminate\Support\Carbon::parse($time)->toDateString())->count();
    }

    /**
     * Количество успешных обменов
     *
     * @return float
     */
    public function successOrdersCount()
    {
        return $this->hasMany(Task::class, 'id_user', 'id')->count();
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'id_user', 'id');
    }

    /**
     * От реферала
     */
    public function fromReferral()
    {
        return $this->hasOne(ReferralRelationship::class, 'user_id', 'id');
    }

    /**
     * Количество удачных сделок
     *
     * @return int
     */
    public function countSuccessfulTransaction()
    {
        return $this->hasMany(Task::class, 'id_user', 'id')->where('status', '=', 4)->count();
    }

    /**
     * Количество отклоненных сделок
     *
     * @return int
     */
    public function countFailedTransaction()
    {
        return $this->hasMany(Task::class, 'id_user', 'id')->where('status', '=', 5)->count();
    }

    /**
     * Информация о последней заявке
     */
    public function lastedOrderAt()
    {
        return $this->hasOne(Task::class, 'id_user', 'id')->where('status', '=', 4)->orderByDesc('id');
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        dispatch(
            new EmailResetPasswordJob($this, $token)
                ->onQueue('low')
        );
    }

    public function notifyAuthenticationLogVia()
    {
        return ['mail'];
    }

    public function preferredLocale()
    {
        return $this->language;
    }

    public function getAuthPasswordName()
    {
        return 'password';
    }

    public function rewardProgram(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RewardProgram::class, 'id', 'id_reward_program');
    }

    public function sumsubIds()
    {
        return $this->hasMany(SumsubId::class);
    }

    /**
     * Create a new personal access token for the user.
     *
     * @param  string  $name
     * @param  array  $abilities
     * @param  \DateTimeInterface|null  $expiresAt
     * @return \Laravel\Sanctum\NewAccessToken
     */
    public function createToken(string $name, array $abilities = ['*'], ?DateTimeInterface $expiresAt = null)
    {
        $plainTextToken = $this->generateTokenString();

        $token = $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        $token->update([
            'token_code' => $token->getKey().'|'.$plainTextToken
        ]);

        return new NewAccessToken($token, $token->getKey().'|'.$plainTextToken);
    }

    /**
     * Все суммы обменов пользователя (через задачи).
     */
    public function exchangeTotals(): HasManyThrough
    {
        return $this->hasManyThrough(
            OrderExchangeTotal::class,
            Task::class,
            'id_user',
            'id_task',
            'id',
            'id'
        );
    }
}
