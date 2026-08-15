<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportMessage;
use Illuminate\Support\Carbon;

/**
 * Classifies visitor message Telegram delivery state for admin UI.
 * LC-P4 remediation (2026-05-29 UTC) restored outbound telemetry columns.
 */
final class SupportTelegramDeliveryStatusService
{
    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PENDING = 'pending';

    public const STATUS_HISTORICAL_UNTRACKED = 'historical_untracked';

    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    /** UTC — migrations applied (LC-P4). Messages before this with null outbound are historical. */
    private const TELEMETRY_TRACKING_START_UTC = '2026-05-29 00:24:00';

    public function __construct(
        private readonly SupportChatSchemaReadinessService $schemaReadiness,
    ) {}

    public function classify(SupportMessage $message): string
    {
        if ($message->sender_type !== SupportMessage::SENDER_VISITOR) {
            return self::STATUS_NOT_APPLICABLE;
        }

        if ($message->telegram_delivery_failed_at !== null || $message->telegram_delivery_error !== null) {
            return self::STATUS_FAILED;
        }

        if ($message->telegram_outbound_message_id !== null) {
            return self::STATUS_DELIVERED;
        }

        $createdAt = $message->created_at;
        if ($createdAt !== null && $createdAt->lt($this->telemetryTrackingStart())) {
            return self::STATUS_HISTORICAL_UNTRACKED;
        }

        return self::STATUS_PENDING;
    }

    /**
     * @return array{
     *     label: string,
     *     badge_class: string,
     *     title: string|null
     * }
     */
    public function presentation(SupportMessage $message): array
    {
        $status = $this->classify($message);

        return match ($status) {
            self::STATUS_DELIVERED => [
                'label' => 'Telegram delivered',
                'badge_class' => 'tg-delivered',
                'title' => $message->telegram_outbound_message_id !== null
                    ? 'Outbound message #'.$message->telegram_outbound_message_id
                    : null,
            ],
            self::STATUS_FAILED => [
                'label' => 'Telegram failed',
                'badge_class' => 'tg-failed',
                'title' => $message->telegram_delivery_error,
            ],
            self::STATUS_PENDING => [
                'label' => 'Telegram pending',
                'badge_class' => 'tg-pending',
                'title' => 'No outbound Telegram message id recorded yet',
            ],
            self::STATUS_HISTORICAL_UNTRACKED => [
                'label' => 'Historical untracked',
                'badge_class' => 'tg-historical',
                'title' => 'Created before LC-P4 delivery telemetry was restored',
            ],
            default => [
                'label' => '',
                'badge_class' => '',
                'title' => null,
            ],
        };
    }

    /**
     * @return array<string, int|null>|null null when schema diagnostics unavailable
     */
    public function summary(): ?array
    {
        if (! $this->schemaReadiness->isDiagnosticsAvailable()) {
            return null;
        }

        $since24h = Carbon::now()->subDay();
        $trackingStart = $this->telemetryTrackingStart();

        $visitor = SupportMessage::query()->where('sender_type', SupportMessage::SENDER_VISITOR);

        $deliveredLast24h = (int) (clone $visitor)
            ->where('created_at', '>=', $since24h)
            ->whereNotNull('telegram_outbound_message_id')
            ->whereNull('telegram_delivery_failed_at')
            ->whereNull('telegram_delivery_error')
            ->count();

        $failedLast24h = (int) (clone $visitor)
            ->where('created_at', '>=', $since24h)
            ->where(function ($q): void {
                $q->whereNotNull('telegram_delivery_failed_at')
                    ->orWhereNotNull('telegram_delivery_error');
            })
            ->count();

        $pendingLast24h = (int) (clone $visitor)
            ->where('created_at', '>=', $since24h)
            ->where('created_at', '>=', $trackingStart)
            ->whereNull('telegram_outbound_message_id')
            ->whereNull('telegram_delivery_failed_at')
            ->whereNull('telegram_delivery_error')
            ->count();

        $historicalUntrackedCount = (int) (clone $visitor)
            ->where('created_at', '<', $trackingStart)
            ->whereNull('telegram_outbound_message_id')
            ->whereNull('telegram_delivery_failed_at')
            ->whereNull('telegram_delivery_error')
            ->count();

        return [
            'telegram_delivered_last_24h' => $deliveredLast24h,
            'telegram_failed_last_24h' => $failedLast24h,
            'telegram_pending_last_24h' => $pendingLast24h,
            'telegram_historical_untracked_count' => $historicalUntrackedCount,
        ];
    }

    private function telemetryTrackingStart(): Carbon
    {
        return Carbon::parse(self::TELEMETRY_TRACKING_START_UTC, 'UTC');
    }
}
