<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property string $outcome
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property int|null $last_operator_message_id
 * @property int|null $last_visitor_message_id
 * @property int|null $time_to_resolution_seconds
 * @property string|null $source
 * @property array<string, mixed>|null $metadata
 */
class SupportAiConversationOutcome extends Model
{
    public const OUTCOME_RESOLVED = 'resolved';

    public const OUTCOME_PENDING = 'pending';

    public const OUTCOME_ESCALATED = 'escalated';

    public const OUTCOME_FAILED = 'failed';

    public const OUTCOME_REOPENED = 'reopened';

    public const OUTCOME_UNKNOWN = 'unknown';

    protected $table = 'support_ai_conversation_outcomes';

    protected $fillable = [
        'conversation_id',
        'outcome',
        'resolved_at',
        'last_operator_message_id',
        'last_visitor_message_id',
        'time_to_resolution_seconds',
        'source',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'conversation_id' => 'integer',
            'resolved_at' => 'datetime',
            'last_operator_message_id' => 'integer',
            'last_visitor_message_id' => 'integer',
            'time_to_resolution_seconds' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('outcome', self::OUTCOME_RESOLVED);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('outcome', [
            self::OUTCOME_PENDING,
            self::OUTCOME_ESCALATED,
            self::OUTCOME_REOPENED,
            self::OUTCOME_UNKNOWN,
        ]);
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
        return $query->where('updated_at', '>=', now()->subDays(max(1, $days)));
    }

    public function isResolved(): bool
    {
        return $this->outcome === self::OUTCOME_RESOLVED;
    }

    public function isUnresolved(): bool
    {
        return in_array($this->outcome, [
            self::OUTCOME_PENDING,
            self::OUTCOME_ESCALATED,
            self::OUTCOME_REOPENED,
            self::OUTCOME_UNKNOWN,
        ], true);
    }
}
