<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAttachment;
use App\Models\SupportMessage;
use Illuminate\Support\Carbon;

final class SupportChatDeliveryDiagnostics
{
    public function markMessageTelegramFailed(int $supportMessageId, ?string $error, ?string $errorCode = null): void
    {
        $reason = SupportChatDiagnosticsLog::sanitizeError($error) ?? 'telegram_delivery_failed';
        if ($errorCode !== null && $errorCode !== '') {
            $reason = substr($errorCode.': '.$reason, 0, 255);
        }

        SupportMessage::query()->whereKey($supportMessageId)->update([
            'telegram_delivery_failed_at' => Carbon::now(),
            'telegram_delivery_error' => $reason,
        ]);
    }

    public function markAttachmentTelegramFailed(int $supportAttachmentId, ?string $error, ?string $errorCode = null): void
    {
        $reason = SupportChatDiagnosticsLog::sanitizeError($error) ?? 'telegram_attachment_failed';
        if ($errorCode !== null && $errorCode !== '') {
            $reason = substr($errorCode.': '.$reason, 0, 255);
        }

        SupportAttachment::query()->whereKey($supportAttachmentId)->update([
            'telegram_delivery_failed_at' => Carbon::now(),
            'telegram_delivery_error' => $reason,
        ]);
    }

    public function clearMessageTelegramFailure(int $supportMessageId): void
    {
        SupportMessage::query()->whereKey($supportMessageId)->update([
            'telegram_delivery_failed_at' => null,
            'telegram_delivery_error' => null,
        ]);
    }

    public function clearAttachmentTelegramFailure(int $supportAttachmentId): void
    {
        SupportAttachment::query()->whereKey($supportAttachmentId)->update([
            'telegram_delivery_failed_at' => null,
            'telegram_delivery_error' => null,
        ]);
    }
}
