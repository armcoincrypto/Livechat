<?php

namespace App\Models;

use App\Models\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated Подлежит удалению из-за старой системы
*/
class CurrencyGroup extends Model
{
    use HasTranslations;

    protected $table = 'currencies_groups';

    protected $fillable = [
        'name',
        'id_user',
        'sorting',
    ];

    protected $translatable = [
        'name',
    ];

    public function currencies()
    {
        return $this->hasMany(Currency::class, 'id_group', 'id');
    }
}
