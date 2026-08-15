<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_code_currency
 * @property int $id_user
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $balance
 * @property-read \App\Models\CodeCurrency|null $code_currency
 * @method static \Illuminate\Database\Eloquent\Builder|InternalAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|InternalAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|InternalAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder|InternalAccount whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InternalAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InternalAccount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InternalAccount whereIdCodeCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InternalAccount whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InternalAccount whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InternalAccount whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class InternalAccount extends Model
{
    protected $table = 'internal_accounts';

    protected $fillable = [
        'id_code_currency',
        'id_user',
        'status',
        'balance',
    ];

    public function code_currency()
    {
        return $this->hasOne(CodeCurrency::class, 'id', 'id_code_currency');
    }
}
