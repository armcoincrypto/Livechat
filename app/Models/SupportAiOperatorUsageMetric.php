<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LC-H: operator usage telemetry for AI drafts (hashes + redacted previews only).
 *
 * @property int $id
 * @property int|null $conversation_id
 * @property int|null $visitor_message_id
 * @property int|null $operator_message_id
 * @property int|null $suggestion_usage_id
 * @property string|null $intent
 * @property bool $order_lookup_used
 * @property bool $direction_lookup_used
 * @property \Illuminate\Support\Carbon|null $draft_generated_at
 * @property \Illuminate\Support\Carbon|null $operator_replied_at
 * @property int|null $response_time_seconds
 * @property string|null $operator_decision
 * @property string|null $suggestion_preview
 * @property string|null $operator_reply_preview
 * @property string|null $suggestion_text_hash
 * @property string|null $operator_text_hash
 * @property string|null $similarity_score
 * @property array<string, mixed>|null $metadata
 */
class SupportAiOperatorUsageMetric extends Model
{
    public const DECISION_ACCEPTED_EXACT = 'accepted_exact';

    public const DECISION_ACCEPTED_MODIFIED = 'accepted_modified';

    public const DECISION_IGNORED = 'ignored';

    public const DECISION_UNKNOWN = 'unknown';

    public const DECISION_PENDING = 'pending';

    protected $table = 'support_ai_operator_usage_metrics';

    protected $fillable = [
        'conversation_id',
        'visitor_message_id',
        'operator_message_id',
        'suggestion_usage_id',
        'intent',
        'order_lookup_used',
        'direction_lookup_used',
        'draft_generated_at',
        'operator_replied_at',
        'response_time_seconds',
        'operator_decision',
        'suggestion_preview',
        'operator_reply_preview',
        'suggestion_text_hash',
        'operator_text_hash',
        'similarity_score',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'conversation_id' => 'integer',
            'visitor_message_id' => 'integer',
            'operator_message_id' => 'integer',
            'suggestion_usage_id' => 'integer',
            'order_lookup_used' => 'boolean',
            'direction_lookup_used' => 'boolean',
            'draft_generated_at' => 'datetime',
            'operator_replied_at' => 'datetime',
            'response_time_seconds' => 'integer',
            'similarity_score' => 'decimal:4',
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
    public function scopeSince(Builder $query, \Illuminate\Support\Carbon $since): Builder
    {
        return $query->where('draft_generated_at', '>=', $since);
    }
}
