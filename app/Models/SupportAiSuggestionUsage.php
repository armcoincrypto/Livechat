<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $conversation_id
 * @property int|null $visitor_message_id
 * @property int|null $suggestion_id
 * @property int|null $operator_message_id
 * @property int|null $learning_event_id
 * @property string $decision
 * @property int|null $edit_distance
 * @property string|null $similarity_score
 * @property string|null $matched_by
 * @property string|null $suggestion_text_hash
 * @property string|null $operator_text_hash
 * @property string|null $suggestion_preview
 * @property string|null $operator_reply_preview
 * @property array<string, mixed>|null $metadata
 */
class SupportAiSuggestionUsage extends Model
{
    public const DECISION_ACCEPTED_EXACT = 'accepted_exact';

    public const DECISION_ACCEPTED_MODIFIED = 'accepted_modified';

    public const DECISION_IGNORED = 'ignored';

    public const DECISION_UNKNOWN = 'unknown';

    public const MATCHED_BY_LINEAGE = 'lineage';

    public const MATCHED_BY_SAME_VISITOR_MESSAGE = 'same_visitor_message';

    public const MATCHED_BY_TELEGRAM_AI_MESSAGE = 'telegram_ai_message';

    public const MATCHED_BY_VISITOR_ANCHOR = 'visitor_anchor';

    public const MATCHED_BY_EVENT_FINGERPRINT = 'event_fingerprint';

    public const MATCHED_BY_SAME_CONVERSATION_RECENT = 'same_conversation_recent';

    public const MATCHED_BY_TEXT_SIMILARITY = 'text_similarity';

    public const MATCHED_BY_FALLBACK_UNKNOWN = 'fallback_unknown';

    public const MATCHED_BY_OPERATOR_TELEGRAM_BUTTON = 'operator_telegram_button';

    protected $table = 'support_ai_suggestion_usages';

    protected $fillable = [
        'conversation_id',
        'visitor_message_id',
        'suggestion_id',
        'operator_message_id',
        'learning_event_id',
        'decision',
        'edit_distance',
        'similarity_score',
        'matched_by',
        'suggestion_text_hash',
        'operator_text_hash',
        'suggestion_preview',
        'operator_reply_preview',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'conversation_id' => 'integer',
            'visitor_message_id' => 'integer',
            'suggestion_id' => 'integer',
            'operator_message_id' => 'integer',
            'learning_event_id' => 'integer',
            'edit_distance' => 'integer',
            'similarity_score' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function learningEvent(): BelongsTo
    {
        return $this->belongsTo(SupportAiLearningEvent::class, 'learning_event_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->whereIn('decision', [
            self::DECISION_ACCEPTED_EXACT,
            self::DECISION_ACCEPTED_MODIFIED,
        ]);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeIgnored(Builder $query): Builder
    {
        return $query->where('decision', self::DECISION_IGNORED);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForConversation(Builder $query, int $conversationId): Builder
    {
        return $query->where('conversation_id', $conversationId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays(max(1, $days)));
    }
}
