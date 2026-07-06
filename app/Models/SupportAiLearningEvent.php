<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $conversation_id
 * @property int|null $message_id
 * @property string|null $ai_request_id
 * @property string|null $intent
 * @property string|null $conversation_stage
 * @property string|null $language
 * @property list<array<string, mixed>>|null $suggestions
 * @property list<string>|null $suggestion_hashes
 * @property int|null $selected_suggestion_index
 * @property string|null $operator_reply
 * @property string|null $operator_reply_hash
 * @property bool $operator_edited
 * @property string|null $edit_distance_ratio
 * @property string|null $outcome
 * @property string|null $quality_score
 * @property list<string>|null $safety_flags
 * @property array<string, mixed>|null $metadata
 */
class SupportAiLearningEvent extends Model
{
    protected $table = 'support_ai_learning_events';

    protected $fillable = [
        'conversation_id',
        'message_id',
        'ai_request_id',
        'intent',
        'conversation_stage',
        'language',
        'suggestions',
        'suggestion_hashes',
        'selected_suggestion_index',
        'operator_reply',
        'operator_reply_hash',
        'operator_edited',
        'edit_distance_ratio',
        'outcome',
        'quality_score',
        'safety_flags',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'conversation_id' => 'integer',
            'message_id' => 'integer',
            'suggestions' => 'array',
            'suggestion_hashes' => 'array',
            'selected_suggestion_index' => 'integer',
            'operator_edited' => 'boolean',
            'edit_distance_ratio' => 'decimal:4',
            'quality_score' => 'decimal:2',
            'safety_flags' => 'array',
            'metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportMessage::class, 'message_id');
    }
}
