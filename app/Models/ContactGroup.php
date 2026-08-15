<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property array|null $name
 * @property int $sorting
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Contact> $contacts
 * @property-read int|null $contacts_count
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|ContactGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ContactGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ContactGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|ContactGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContactGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContactGroup whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContactGroup whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContactGroup whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|ContactGroup whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|ContactGroup whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContactGroup whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContactGroup whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ContactGroup extends Model
{
    use HasTranslations;

    protected $table = 'contacts_groups';

    protected $fillable = [
        'name',
        'sorting',
        'status'
    ];

    public array $translatable = ['name'];

    protected $casts = [
        'sorting' => 'integer',
        'status' => 'integer',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'id_group', 'id');
    }
}
