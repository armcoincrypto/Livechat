<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 18.10.2019
 * Time: 19:42
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $link
 * @property int $status
 * @property int $sorting
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompetitorRates> $competitor_rates
 * @property-read int|null $competitor_rates_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompetitorRates> $rates
 * @property-read int|null $rates_count
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorLink newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorLink newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorLink query()
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorLink whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorLink whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorLink whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorLink whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorLink whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorLink whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompetitorLink whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CompetitorLink extends Model
{
    protected $table = 'competitor_links';

    protected $fillable = [
        'name',
        'status',
        'link',
        'sorting',
    ];

    public function rates()
    {
        return $this->hasMany(CompetitorRates::class, 'id_competitor', 'id');
    }

    public function rates_enabled()
    {
        return $this->hasMany(CompetitorRates::class, 'id_competitor', 'id')->where('status', 1);
    }


    public function competitor_rates()
    {
        return $this->hasMany(CompetitorRates::class, 'id_competitor', 'id')->where('status', '=', 1);
    }
}
