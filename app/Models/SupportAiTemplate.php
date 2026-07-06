<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SupportAiTemplate extends Model
{
    protected $table = 'support_ai_templates';

    protected $fillable = [
        'template_code',
        'intent',
        'category',
        'title',
        'template_text',
        'template_type',
        'language',
        'frequency',
        'confidence',
        'active',
        'requires_validation',
        'source_conversation_ids',
        'metadata',
        'last_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'requires_validation' => 'boolean',
            'frequency' => 'integer',
            'source_conversation_ids' => 'array',
            'metadata' => 'array',
            'last_reviewed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<SupportAiTemplate>  $query
     * @return Builder<SupportAiTemplate>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * @param  Builder<SupportAiTemplate>  $query
     * @return Builder<SupportAiTemplate>
     */
    public function scopeValidated(Builder $query): Builder
    {
        return $query->where('requires_validation', false);
    }

    /**
     * @param  Builder<SupportAiTemplate>  $query
     * @return Builder<SupportAiTemplate>
     */
    public function scopeForIntent(Builder $query, string $intent): Builder
    {
        return $query->where('intent', $intent);
    }

    /**
     * @param  Builder<SupportAiTemplate>  $query
     * @return Builder<SupportAiTemplate>
     */
    public function scopeForLanguage(Builder $query, ?string $language): Builder
    {
        if ($language === null || $language === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($language): void {
            $inner->where('language', $language)
                ->orWhere('language', 'any');
        });
    }
}
