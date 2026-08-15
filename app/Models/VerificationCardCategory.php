<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property array|null $name
 * @property int $status
 * @property int $sorting
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VerificationCardInstruction> $instructions
 * @property-read int|null $instructions_count
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardCategory whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardCategory whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardCategory whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardCategory whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardCategory whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardCategory whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardCategory whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardCategory whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class VerificationCardCategory extends Model
{
    use HasTranslations;

    protected $table = 'verification_card_category';

    public $translatable = ['name'];

    protected $fillable = [
        'name', 'sorting', 'status',
    ];

    public function instructions()
    {
        return $this->hasMany(VerificationCardInstruction::class, 'id_category', 'id');
    }
}
