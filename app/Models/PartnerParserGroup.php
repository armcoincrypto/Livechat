<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $link
 * @property int $sorting
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PartnerParserRates> $rates
 * @property-read int|null $rates_count
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserGroup whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserGroup whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserGroup whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserGroup whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserGroup whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PartnerParserGroup extends Model
{
    protected $table = 'partner_parser_groups';

    protected $fillable = [
        'name',
        'status',
        'link',
        'sorting',
    ];

    public function rates()
    {
        return $this->hasMany(PartnerParserRates::class, 'partner_id', 'id');
    }
}
