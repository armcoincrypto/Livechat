<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 10.11.2020
 * Time: 9:01
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class ActiveUserModel extends Model
{
    /**
     * The activity model uses the 'sessions' database.
     *
     * @var string
     */
    protected $table = 'sessions';

    /**
     * There are no timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * BelongsTo relationship with the user model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Constrain the query to retrieve only sessions of guests who
     * have been active within the specified number of seconds.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $seconds
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeGuestsBySeconds($query, $seconds = 60)
    {
        return $query->where('last_activity', '>=', time() - $seconds)->whereNull('user_id');
    }

    /**
     * Alias for the `guestsByMinutes` query method.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $minutes
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeGuests($query, $minutes = 5)
    {
        return $query->guestsByMinutes($minutes);
    }

    /**
     * Constrain the query to retrieve only sessions of guests who
     * have been active within the specified number of minutes.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $minutes
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeGuestsByMinutes($query, $minutes = 5)
    {
        return $query->guestsBySeconds($minutes * 60);
    }

    /**
     * Constrain the query to retrieve only sessions of guests who
     * have been active within the specified number of hours.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $hours
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeGuestsByHours($query, $hours = 1)
    {
        return $query->guestsByMinutes($hours * 60);
    }

    /**
     * Constrain the query to retrieve only sessions of users who
     * have been active within the specified number of seconds.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $seconds
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeUsersBySeconds($query, $seconds = 60)
    {
        return $query->with(['user'])->where('last_activity', '>=', time() - $seconds)->whereNotNull('user_id');
    }

    /**
     * Alias for the `usersByMinutes` query method.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $minutes
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeUsers($query, $minutes = 5)
    {
        return $query->usersByMinutes($minutes);
    }

    /**
     * Constrain the query to retrieve only sessions of users who
     * have been active within the specified number of minutes.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $minutes
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeUsersByMinutes($query, $minutes = 5)
    {
        return $query->usersBySeconds($minutes * 60);
    }

    /**
     * Constrain the query to retrieve only sessions of users who
     * have been active within the specified number of hours.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $hours
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeUsersByHours($query, $hours = 1)
    {
        return $query->usersByMinutes($hours * 60);
    }

    /**
     * Add an "order by" clause to retrieve most recent sessions.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $column
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function scopeMostRecent($query, $column = 'last_activity')
    {
        return $query->latest($column);
    }

    /**
     * Add an "order by" clause to retrieve least recent sessions.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $column
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function scopeLeastRecent($query, $column = 'last_activity')
    {
        return $query->oldest($column);
    }

    /**
     * Use joins to order by the users' column attributes.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $column
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function scopeOrderByUsers($query, $column, $dir = 'ASC')
    {
        $table = $this->getTable();

        $userModel = config('auth.providers.users.model');
        $user = new $userModel;
        $userTable = $user->getTable();
        $userKey = $user->getKeyName();

        return $query->join($userTable, "{$table}.user_id", '=', "{$userTable}.{$userKey}")->orderBy("{$userTable}.{$column}", $dir);
    }
}
