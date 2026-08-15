<?php

namespace App\Models;

use App\Models\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $id_category
 * @property array|null $name
 * @property array|null $text
 * @property array|null $notice_text
 * @property int $status
 * @property int $sorting
 * @property string|null $image
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\VerificationCardCategory|null $category
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardInstruction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardInstruction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardInstruction query()
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardInstruction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardInstruction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardInstruction whereIdCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardInstruction whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardInstruction whereJsonContainsLocale(string $column, string $locale, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardInstruction whereJsonContainsLocales(string $column, array $locales, ?mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardInstruction whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardInstruction whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardInstruction whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardInstruction whereNoticeText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardInstruction whereSorting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardInstruction whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardInstruction whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VerificationCardInstruction whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class VerificationCardInstruction extends Model
{
    use HasTranslations;

    protected $table = 'verification_card_instructions';

    public $translatable = ['name', 'text', 'notice_text'];

    protected $fillable = [
        'id_category',
        'name',
        'text',
        'notice_text',
        'status',
        'sorting',
        'image',
    ];

    public function category()
    {
        return $this->hasOne(VerificationCardCategory::class, 'id', 'id_category');
    }
}
