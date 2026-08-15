<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $public_support_id
 * @property string $status
 * @property string $visitor_name
 * @property string $visitor_email
 * @property string|null $visitor_ip
 * @property string|null $user_agent
 * @property string|null $page_url
 * @property string|null $locale
 * @property string $access_token_hash
 * @property int $access_token_version
 * @property \Illuminate\Support\Carbon|null $last_message_at
 * @property string|null $last_message_sender_type
 * @property \Illuminate\Support\Carbon|null $last_visitor_message_at
 * @property \Illuminate\Support\Carbon|null $last_operator_message_at
 * @property int|null $last_operator_telegram_user_id
 * @property string|null $last_operator_telegram_username
 * @property string|null $last_operator_display_name
 * @property int|null $telegram_forum_topic_id
 * @property \Illuminate\Support\Carbon|null $telegram_forum_topic_created_at
 * @property \Illuminate\Support\Carbon|null $telegram_topic_closed_at
 * @property \Illuminate\Support\Carbon|null $telegram_topic_reopened_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SupportConversation extends Model
{
    /** @deprecated Prefer waiting_operator / waiting_visitor; migrated from legacy "open" */
    public const STATUS_OPEN = 'open';

    public const STATUS_WAITING_OPERATOR = 'waiting_operator';

    public const STATUS_WAITING_VISITOR = 'waiting_visitor';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'support_conversations';

    protected $fillable = [
        'uuid',
        'public_support_id',
        'status',
        'visitor_name',
        'visitor_email',
        'visitor_ip',
        'user_agent',
        'page_url',
        'locale',
        'access_token_hash',
        'access_token_version',
        'last_message_at',
        'last_message_sender_type',
        'last_visitor_message_at',
        'last_operator_message_at',
        'last_operator_telegram_user_id',
        'last_operator_telegram_username',
        'last_operator_display_name',
        'telegram_forum_topic_id',
        'telegram_forum_topic_created_at',
        'telegram_topic_closed_at',
        'telegram_topic_reopened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_visitor_message_at' => 'datetime',
            'last_operator_message_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_operator_telegram_user_id' => 'integer',
            'telegram_forum_topic_id' => 'integer',
            'telegram_forum_topic_created_at' => 'datetime',
            'telegram_topic_closed_at' => 'datetime',
            'telegram_topic_reopened_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (SupportConversation $conversation): void {
            if ($conversation->public_support_id !== null && $conversation->public_support_id !== '') {
                return;
            }
            $conversation->public_support_id = 'S-'.str_pad((string) $conversation->id, 7, '0', STR_PAD_LEFT);
            $conversation->saveQuietly();
        });
    }

    public static function findByPublicIdOrUuid(string $raw): ?self
    {
        $trim = trim($raw);
        if ($trim === '') {
            return null;
        }

        if (preg_match('/^s-\d+$/i', $trim) === 1) {
            return static::query()
                ->whereRaw('LOWER(public_support_id) = ?', [mb_strtolower($trim)])
                ->first();
        }

        return static::query()->where('uuid', $trim)->first();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'support_conversation_id')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportAttachment::class, 'support_conversation_id')->orderByDesc('id');
    }

    /**
     * Active (non-closed) conversations: operator may reply from Telegram; visitor may message (reopen if closed handled separately).
     */
    public function scopeOpen($query)
    {
        return $query->where('status', '!=', self::STATUS_CLOSED);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Whether the conversation accepts operator Telegram replies (not closed).
     */
    public function isOpen(): bool
    {
        return ! $this->isClosed();
    }

    /**
     * Human-oriented wait direction for APIs: operator | visitor | null when closed.
     */
    public function waitingOn(): ?string
    {
        if ($this->isClosed()) {
            return null;
        }

        return match ($this->status) {
            self::STATUS_WAITING_VISITOR => 'visitor',
            self::STATUS_WAITING_OPERATOR, self::STATUS_OPEN, 'open' => 'operator',
            default => 'operator',
        };
    }

    /**
     * Queue/triage: operator is expected to reply next (includes legacy {@see STATUS_OPEN}).
     */
    public function needsOperatorReply(): bool
    {
        return $this->status === self::STATUS_WAITING_OPERATOR
            || $this->status === self::STATUS_OPEN;
    }

    /**
     * Message-based triage: visitor spoke last and operator has not answered since.
     */
    public function isUnansweredWaiting(): bool
    {
        if ($this->isClosed() || $this->last_visitor_message_at === null) {
            return false;
        }

        if ($this->last_operator_message_at === null) {
            return true;
        }

        return $this->last_visitor_message_at->gt($this->last_operator_message_at);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeUnansweredWaiting($query)
    {
        return $query
            ->where('status', '!=', self::STATUS_CLOSED)
            ->whereNotNull('last_visitor_message_at')
            ->where(function ($q): void {
                $q->whereNull('last_operator_message_at')
                    ->orWhereColumn('last_visitor_message_at', '>', 'last_operator_message_at');
            });
    }

    public function operatorWaitMinutes(): ?int
    {
        if (! $this->isUnansweredWaiting()) {
            return null;
        }

        return (int) $this->last_visitor_message_at->diffInMinutes(now());
    }

    /**
     * @return null|'normal'|'warning'|'danger'|'critical'
     */
    public function operatorWaitPriority(): ?string
    {
        $minutes = $this->operatorWaitMinutes();
        if ($minutes === null) {
            return null;
        }

        if ($minutes >= 60) {
            return 'critical';
        }

        if ($minutes >= 30) {
            return 'danger';
        }

        if ($minutes >= 15) {
            return 'warning';
        }

        return 'normal';
    }

    public function operatorWaitLabel(): ?string
    {
        $minutes = $this->operatorWaitMinutes();
        if ($minutes === null) {
            return null;
        }

        if ($minutes < 60) {
            return 'Waiting '.$minutes.'m';
        }

        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        if ($remainder === 0) {
            return 'Waiting '.$hours.'h';
        }

        return 'Waiting '.$hours.'h '.$remainder.'m';
    }
}
