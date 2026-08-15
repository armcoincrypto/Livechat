<?php

declare(strict_types=1);

namespace App\Services\Orders\Transitions;

use Illuminate\Support\Facades\File;

/**
 * Low-volume operational counters. Not a customer SLA.
 */
final class PaymentTransitionMetrics
{
    public static function path(): string
    {
        return storage_path('app/orders/payment-transition-metrics.json');
    }

    public static function increment(string $key, int $by = 1): void
    {
        if (\defined('PHPUNIT_COMPOSER_INSTALL') || app()->runningUnitTests()) {
            return;
        }
        $data = self::snapshot();
        $data[$key] = (int) ($data[$key] ?? 0) + $by;
        $data['updated_at'] = now()->toIso8601String();
        File::ensureDirectoryExists(dirname(self::path()));
        File::put(self::path(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string,mixed>
     */
    public static function snapshot(): array
    {
        if (!is_file(self::path())) {
            return [
                'confirmed_funds_wrong_status' => 0,
                'payment_detected_transition_failed' => 0,
                'duplicate_payment_success_noop' => 0,
                'payment_detector_transition_errors' => 0,
            ];
        }
        $decoded = json_decode((string) file_get_contents(self::path()), true);

        return is_array($decoded) ? $decoded : [];
    }
}
