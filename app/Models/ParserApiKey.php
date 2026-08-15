<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_type
 * @property string|null $api_key
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $view_count
 * @property string|null $provider_id
 * @method static \Illuminate\Database\Eloquent\Builder|ParserApiKey newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ParserApiKey newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ParserApiKey query()
 * @method static \Illuminate\Database\Eloquent\Builder|ParserApiKey whereApiKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserApiKey whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserApiKey whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserApiKey whereIdType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserApiKey whereProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserApiKey whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserApiKey whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ParserApiKey whereViewCount($value)
 * @mixin \Eloquent
 */
class ParserApiKey extends Model
{
    protected $table = 'parser_api_keys';

    protected $fillable = [
        'id_type',
        'api_key',
        'status',
        'view_count',
        'provider_id',
    ];
}
