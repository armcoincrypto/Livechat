<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $id_task
 * @property string|null $card_number
 * @property string|null $payment_system
 * @property string|null $type_card
 * @property string|null $brand_card
 * @property string|null $country_name
 * @property string|null $country_currency
 * @property string|null $bank_name
 * @property string|null $bank_url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $bank_phone
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCardDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCardDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCardDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCardDetail whereBankName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCardDetail whereBankPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCardDetail whereBankUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCardDetail whereBrandCard($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCardDetail whereCardNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCardDetail whereCountryCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCardDetail whereCountryName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCardDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCardDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCardDetail whereIdTask($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCardDetail wherePaymentSystem($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCardDetail whereTypeCard($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskCardDetail whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TaskCardDetail extends Model
{
    protected $table = 'tasks_card_details';

    protected $fillable = [
        'id_task',
        'card_number',
        'payment_system',
        'type_card',
        'brand_card',
        'country_name',
        'country_currency',
        'bank_name',
        'bank_url',
        'bank_phone',
        'type_column'
    ];
}
