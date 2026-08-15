<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $name
 * @property string $type
 * @property string|null $link
 * @property int $sorting
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|SocialReview newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SocialReview newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SocialReview query()
 * @method static \Illuminate\Database\Eloquent\Builder|SocialReview whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialReview whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialReview whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialReview whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialReview whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialReview whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialReview whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialReview whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SocialReview extends Model
{
    protected $table = 'social_reviews';

    protected $fillable = [
        'name',
        'type',
        'sorting',
        'status',
        'link',
    ];
}
