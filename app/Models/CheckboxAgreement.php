<?php

namespace App\Models;

use App\Models\Traits\HasTranslations;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CheckboxAgreement extends Model
{
    use HasFactory, HasTranslations;

    protected $table = 'checkbox_agreements';

    protected $fillable = [
        'label',
        'description',
        'link',
        'page_type',
        'page_id',
        'checked',
        'required',
        'is_protected',
        'status',
        'key_id',
        'sorting',
        'text_error',
        'apply_mode',
    ];

    public $translatable = ['label', 'description', 'text_error'];

    protected $casts = [
        'checked' => 'boolean',
        'required' => 'boolean',
        'is_protected' => 'boolean',
        'status' => 'boolean',
        'apply_mode' => 'string', // 'all_except' или 'only_selected'
    ];


    public function page()
    {
        return $this->hasOne(Page::class, 'page_id', 'page_id');
    }

    public function excludedDirections(): BelongsToMany
    {
        return $this->belongsToMany(DirectionExchange::class, 'checkbox_agreement_direction_exchange');
    }

    public function allowedDirections(): BelongsToMany
    {
        return $this->belongsToMany(DirectionExchange::class, 'checkbox_agreement_direction_allowed');
    }
}
