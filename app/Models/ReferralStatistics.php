<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 18.05.2019
 * Time: 20:50
 */

namespace App\Models;

use App\Models\Filters\ReferralStatisticsFilter;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;

class ReferralStatistics extends Model
{
    use Filterable;

    protected $table = 'referral_statistics';

    protected $fillable = [
        'ref_hash',
        'ip_address',
        'id_user',
        'user_agent',
        'is_archive',
        'cur_from',
        'cur_to',
        'from_and_to',
    ];

    protected $casts = [
        'ref_hash'   => 'string',
        'id_user'    => 'integer',
        'is_archive' => 'boolean',
    ];

    /**
     * Фильтры
     */
    public function modelFilter()
    {
        return $this->provideFilter(ReferralStatisticsFilter::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }
}
