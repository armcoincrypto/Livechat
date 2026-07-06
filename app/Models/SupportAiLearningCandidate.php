<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $candidate_type
 * @property string $status
 * @property string|null $source
 * @property string|null $intent
 * @property string|null $language
 * @property string|null $title
 * @property string|null $problem_summary
 * @property string|null $proposed_rule
 * @property string|null $proposed_example
 * @property string|null $before_example
 * @property string|null $after_example
 * @property array<string, mixed>|null $evidence
 * @property string|null $score
 * @property string|null $evaluation_score
 * @property string|null $evaluation_result
 * @property string|null $evaluation_summary
 * @property array<string, mixed>|null $evaluation_flags
 * @property \Illuminate\Support\Carbon|null $evaluated_at
 * @property \Illuminate\Support\Carbon|null $auto_promoted_at
 * @property string|null $risk_level
 * @property string|null $review_notes
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $rejected_at
 */
class SupportAiLearningCandidate extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_STAGED = 'staged';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_APPLIED_LATER = 'applied_later';

    public const TYPE_PLAYBOOK_EXAMPLE = 'playbook_example';

    public const TYPE_TONE_RULE = 'tone_rule';

    public const TYPE_SAFETY_RULE = 'safety_rule';

    public const TYPE_INTENT_RULE = 'intent_rule';

    public const TYPE_FOLLOWUP_RULE = 'followup_rule';

    public const TYPE_OPERATOR_ACTION_RULE = 'operator_action_rule';

    public const TYPE_EDGE_CASE_RULE = 'edge_case_rule';

    protected $table = 'support_ai_learning_candidates';

    protected $fillable = [
        'candidate_type',
        'status',
        'source',
        'intent',
        'language',
        'title',
        'problem_summary',
        'proposed_rule',
        'proposed_example',
        'before_example',
        'after_example',
        'evidence',
        'score',
        'evaluation_score',
        'evaluation_result',
        'evaluation_summary',
        'evaluation_flags',
        'evaluated_at',
        'auto_promoted_at',
        'risk_level',
        'review_notes',
        'approved_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'score' => 'decimal:2',
            'evaluation_score' => 'decimal:2',
            'evaluation_flags' => 'array',
            'evaluated_at' => 'datetime',
            'auto_promoted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }
}
