<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $rule_code
 * @property string $category
 * @property string $title
 * @property string|null $intent
 * @property string|null $language
 * @property string $rule_text
 * @property string|null $answer_template
 * @property string|null $safe_phrasing
 * @property list<string>|null $question_patterns
 * @property list<int>|null $source_conversation_ids
 * @property int $source_message_count
 * @property string $confidence
 * @property bool $requires_validation
 * @property bool $active
 * @property string $risk_level
 * @property list<string>|null $tags
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $last_reviewed_at
 */
class SupportAiKnowledgeRule extends Model
{
    public const CONFIDENCE_HIGH = 'high';

    public const CONFIDENCE_MEDIUM = 'medium';

    public const CONFIDENCE_LOW = 'low';

    protected $table = 'support_ai_knowledge_rules';

    protected $fillable = [
        'rule_code',
        'category',
        'title',
        'intent',
        'language',
        'rule_text',
        'answer_template',
        'safe_phrasing',
        'question_patterns',
        'source_conversation_ids',
        'source_message_count',
        'confidence',
        'requires_validation',
        'active',
        'risk_level',
        'tags',
        'metadata',
        'last_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'question_patterns' => 'array',
            'source_conversation_ids' => 'array',
            'tags' => 'array',
            'metadata' => 'array',
            'requires_validation' => 'boolean',
            'active' => 'boolean',
            'source_message_count' => 'integer',
            'last_reviewed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeValidated(Builder $query): Builder
    {
        return $query->where('requires_validation', false);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForIntent(Builder $query, string $intent): Builder
    {
        return $query->where('intent', $intent);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForLanguage(Builder $query, ?string $language): Builder
    {
        if ($language === null || $language === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($language): void {
            $q->whereNull('language')->orWhere('language', $language);
        });
    }
}
