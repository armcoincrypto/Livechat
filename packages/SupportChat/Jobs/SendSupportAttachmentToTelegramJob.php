<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Jobs;

use iEXPackages\SupportChat\Services\SupportTelegramAttachmentOutboundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Forwards a visitor attachment to Telegram (forum topic). Failures are non-fatal for the upload API.
 */
final class SendSupportAttachmentToTelegramJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $supportAttachmentId,
    ) {}

    public function handle(SupportTelegramAttachmentOutboundService $telegram): void
    {
        $telegram->sendVisitorAttachmentIfApplicable($this->supportAttachmentId);
    }
}
