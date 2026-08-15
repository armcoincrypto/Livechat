<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $name
 * @property int $status
 * @property int $sorting
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $requisites_paginated
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Requisites> $requisites
 * @property-read int|null $requisites_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Requisites> $requisites_all
 * @property-read int|null $requisites_all_count
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesGroup whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesGroup whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesGroup whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesGroup whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class RequisitesGroup extends Model
{
    protected $table = 'requisites_group';

    protected $fillable = [
        'name',
        'status',
        'sorting',
    ];

    public function requisites()
    {
        return $this->hasMany(Requisites::class, 'id_group', 'id')->where('is_history', '=', 0)->filter([]);
    }

    public function requisites_all()
    {
        return $this->hasMany(Requisites::class, 'id_group', 'id');
    }

    public function getRequisitesPaginatedAttribute()
    {
        return $this->requisites()->paginate(10);
    }
}
