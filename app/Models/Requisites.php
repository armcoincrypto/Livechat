<?php

namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Requisites extends Model
{
    use Filterable, SoftDeletes;

    protected $table = 'requisites';

    /**
     * Атрибуты, которые должны быть видоизменены по датам.
     *
     * @var array
     */
    protected $casts = [
        'deleted_at' => 'datetime',
        'history_at' => 'datetime',
    ];

    protected $fillable = [
        'name',
        'account_number',
        'id_currency',
        'id_user',
        'id_group',
        'status',
        'limit_day',
        'limit_week',
        'limit_month',
        'view',
        'limit_views',
        'recipient',
        'is_history',
        'history_at',
        'is_enabled_merchant',
        'id_proxy',
        'is_drain',
        'max_wallet_limit',
        'drain_comment',
        'drain_room',
        'is_unique_shot',
        'photo_name',
        'photo_status',
        'is_already_used'
    ];

    public function modelFilter()
    {
        return $this->provideFilter(\App\Models\Filters\RequisitesFilter::class);
    }

    public function drain_requisites()
    {
        return $this->hasOne(Requisites::class, 'id', 'drain_room');
    }

    /**
     * Запрос на включенение только активных счетов
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActiveWallet($query)
    {
        return $query->where('status', '=', 1)
            ->where('is_history', '=', 0);
    }

    /**
     * Информационные поля
     */
    public function requisites_info_fields(): MorphToMany
    {
        return $this->morphToMany(
            RequisiteInfoField::class,
            'model',
            'requisites_has_info_fields',
            'model_id',
            'field_id'
        );
    }

    /**
     * Запрос на включение только архивированных реквизитов
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIsHistory($query)
    {
        return $query->where('is_history', '=', 1);
    }

    /**
     * Запрос на отключение архивированных реквизитов
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIsNotHistory($query)
    {
        return $query->where('is_history', '=', 0);
    }

    public function currency()
    {
        return $this->hasOne(Currency::class, 'id', 'id_currency');
    }

    /**
     * Группы реквизитов
     *
     * @return HasOne
     */
    public function group(): HasOne
    {
        return $this->hasOne(RequisitesGroup::class,'id','id_group');
    }

    public function requisites_list()
    {
        return $this->hasOne(RequisitesList::class, 'id_requisites', 'id')->inRandomOrder();
    }

    public function requisites_single()
    {
        return $this->hasOne(RequisitesList::class, 'id_requisites', 'id')->where('status', '=', 0);
    }

    public function requisites_list2()
    {
        return $this->hasMany(RequisitesList::class, 'id_requisites', 'id')->where('status', '=', 0);
    }

    public function tasks()
    {
        return $this->hasOne(Task::class, 'id_payment_requisites', 'id');
    }

    public function tasks_many()
    {
        return $this->hasMany(Task::class, 'id_payment_requisites', 'id');
    }

    public function proxy()
    {
        return $this->hasOne(ProxyModel::class, 'id', 'id_proxy');
    }
}
