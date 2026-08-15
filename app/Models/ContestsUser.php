<?php

namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * 
 *
 * @property int $id
 * @property int $id_user
 * @property int $id_monitoring
 * @property int $status
 * @property string|null $name
 * @property string|null $email
 * @property string|null $link
 * @property float $bonus
 * @property int $id_contest
 * @property string|null $code_sign_bonus
 * @property string|null $code_name_bonus
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ContestModel> $contests_user_winner
 * @property-read int|null $contests_user_winner_count
 * @property-read \App\Models\LinksReview|null $link_review
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser filter(array $input = [], $filter = null)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser paginateFilter($perPage = null, $columns = [], $pageName = 'page', $page = null)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser query()
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser simplePaginateFilter($perPage = null, $columns = [], $pageName = 'page', $page = null)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser whereBeginsWith($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser whereBonus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser whereCodeNameBonus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser whereCodeSignBonus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser whereEndsWith($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser whereIdContest($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser whereIdMonitoring($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser whereLike($column, $value, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContestsUser whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ContestsUser extends Model
{
    use Filterable;

    protected $table = 'contests_users';

    protected $fillable = [
        'id_user',
        'id_monitoring',
        'status',
        'name',
        'email',
        'link',
        'bonus',
        'id_contest',
        'code_sign_bonus',
        'code_name_bonus',
        'percent',
        'status',
    ];

    public function modelFilter()
    {
        return $this->provideFilter(\App\Models\Filters\ContestsUserFilter::class);
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'id_user');
    }

    public function link_review()
    {
        return $this->hasOne(LinksReview::class, 'id', 'id_monitoring');
    }

    public function contests_user_winner(): MorphToMany
    {
        return $this->morphedByMany(ContestModel::class, 'model', 'contests_has_contests_users', 'contests_user_id');
    }
}
