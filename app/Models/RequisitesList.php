<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_requisites
 * @property string|null $address
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $status
 * @property-read \App\Models\Requisites|null $requisites
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesList newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesList newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesList query()
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesList whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesList whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesList whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesList whereIdRequisites($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesList whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequisitesList whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class RequisitesList extends Model
{
    protected $table = 'requisites_list';

    protected $fillable = [
        'id_requisites', 'address',
    ];

    public function requisites()
    {
        return $this->hasOne(Requisites::class, 'id', 'id_requisites');
    }
}
