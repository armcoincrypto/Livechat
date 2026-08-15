<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $support_conversation_id
 * @property string $sender_type
 * @property string $body
 * @property array<string, mixed>|null $metadata
 * @property int|null $telegram_outbound_message_id
 * @property \Illuminate\Support\Carbon|null $telegram_delivery_failed_at
 * @property string|null $telegram_delivery_error
 * @property int|null $telegram_inbound_message_id
 * @property int|null $telegram_reply_to_message_id
 * @property \Illuminate\Support\Carbon|null $seen_by_visitor_at
 * @property \Illuminate\Support\Carbon $created_at
 */
class SupportMessage extends Model
{
    public const SENDER_VISITOR = 'visitor';

    public const SENDER_OPERATOR = 'operator';

    public const SENDER_SYSTEM = 'system';

    public const UPDATED_AT = null;

    protected $table = 'support_messages';

    protected $fillable = [
        'support_conversation_id',
        'sender_type',
        'body',
        'metadata',
        'telegram_outbound_message_id',
        'telegram_delivery_failed_at',
        'telegram_delivery_error',
        'telegram_inbound_message_id',
        'telegram_reply_to_message_id',
        'seen_by_visitor_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'telegram_outbound_message_id' => 'integer',
            'telegram_delivery_failed_at' => 'datetime',
            'telegram_inbound_message_id' => 'integer',
            'telegram_reply_to_message_id' => 'integer',
            'seen_by_visitor_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'support_conversation_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportAttachment::class, 'support_message_id');
    }
}
