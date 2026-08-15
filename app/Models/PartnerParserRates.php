<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $exchange_in
 * @property string|null $exchange_out
 * @property int $id_group
 * @property float $rate
 * @property string|null $partner_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $partner_id
 * @property string|null $group_name
 * @property int $number_format
 * @property \Illuminate\Support\Carbon|null $last_updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DirectionExchange> $direction_exchange
 * @property-read int|null $direction_exchange_count
 * @property-read \App\Models\PartnerParserGroup|null $partner_parser_group
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserRates newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserRates newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserRates query()
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserRates whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserRates whereExchangeIn($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserRates whereExchangeOut($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserRates whereGroupName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserRates whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserRates whereIdGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserRates whereLastUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserRates whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserRates whereNumberFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserRates wherePartnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserRates wherePartnerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserRates whereRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerParserRates whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PartnerParserRates extends Model
{
    protected $table = 'partner_parser_rates';

    protected $fillable = [
        'name',
        'exchange_in',
        'exchange_out',
        'id_group',
        'rate',
        'status',
        'partner_type',
        'partner_id',
        'group_name',
        'number_format',
        'last_updated_at',
    ];

    protected $casts = [
        'last_updated_at' => 'datetime',
    ];

    public function direction_exchange()
    {
        return $this->hasMany(DirectionExchange::class, 'id_partner_parser_rate', 'id');
    }

    public function partner_parser_group()
    {
        return $this->hasOne(PartnerParserGroup::class, 'id', 'partner_id');
    }
}
