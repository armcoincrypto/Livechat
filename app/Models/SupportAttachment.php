<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $support_conversation_id
 * @property int|null $support_message_id
 * @property string $sender_type
 * @property string $disk
 * @property string $path
 * @property string|null $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string|null $sha256
 * @property string|null $caption
 * @property int|null $telegram_message_id
 * @property string|null $telegram_file_id
 * @property \Illuminate\Support\Carbon|null $telegram_delivery_failed_at
 * @property string|null $telegram_delivery_error
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class SupportAttachment extends Model
{
    use SoftDeletes;

    public const SENDER_VISITOR = 'visitor';

    public const SENDER_OPERATOR = 'operator';

    protected $table = 'support_attachments';

    protected $fillable = [
        'support_conversation_id',
        'support_message_id',
        'sender_type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'sha256',
        'caption',
        'telegram_message_id',
        'telegram_file_id',
        'telegram_delivery_failed_at',
        'telegram_delivery_error',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'support_message_id' => 'integer',
            'support_conversation_id' => 'integer',
            'telegram_message_id' => 'integer',
            'telegram_delivery_failed_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'support_conversation_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportMessage::class, 'support_message_id');
    }
}
