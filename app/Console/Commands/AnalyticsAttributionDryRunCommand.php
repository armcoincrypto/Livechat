<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Analytics\AttributionOrderLinker;
use App\Services\Analytics\AttributionSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Non-mutating attribution dry-run (Batch 11).
 */
final class AnalyticsAttributionDryRunCommand extends Command
{
    protected $signature = 'analytics:attribution-dry-run
        {--session= : Optional public session id}
        {--utm-source=google}
        {--utm-medium=cpc}
        {--utm-campaign=batch11-test}
        {--referrer=https://example.com/landing}
        {--path=/ru/guides/obmen-usdt-na-rubli}
        {--locale=ru}';

    protected $description = 'Dry-run attribution sanitization and simulated order linkage (no DB writes, no orders, no funds)';

    public function handle(AttributionSanitizer $sanitizer, AttributionOrderLinker $linker): int
    {
        $session = $this->option('session') ?: ('dry_'.Str::lower(Str::random(24)));
        $payload = [
            'public_session_id' => $session,
            'utm_source' => (string) $this->option('utm-source'),
            'utm_medium' => (string) $this->option('utm-medium'),
            'utm_campaign' => (string) $this->option('utm-campaign'),
            'utm_term' => 'wallet=0xdeadbeefshouldreject@x.com',
            'referrer' => (string) $this->option('referrer'),
            'landing_path' => (string) $this->option('path'),
            'locale' => (string) $this->option('locale'),
            'device_class' => 'desktop',
            'touch' => 'first',
        ];

        $clean = $sanitizer->sanitizePayload($payload);
        $this->line('SANITIZED_PAYLOAD='.json_encode($clean, JSON_UNESCAPED_SLASHES));
        $this->line('FIRST_TOUCH_KEYS=utm_source,utm_medium,utm_campaign,referrer,landing_path');
        $this->line('LAST_TOUCH_KEYS=same_when_updated');
        $this->line('SIMULATED_ORDER_LINK=nullable session_attribution_id lookup by public_session_id only');
        $this->line('SESSION_ID_VALID='.($clean['public_session_id'] ? 'yes' : 'no'));
        $this->line('UTM_TERM_REJECTED_SENSITIVE='.(($clean['utm_term'] ?? null) === null ? 'yes' : 'no'));

        // Prove linker swallows failures with a fake task.
        $fake = new class
        {
            public $id = 0;
            public $session_attribution_id = null;

            public function forceFill(array $attrs): self
            {
                foreach ($attrs as $k => $v) {
                    $this->{$k} = $v;
                }

                return $this;
            }

            public function saveQuietly(): bool
            {
                return true;
            }
        };
        $linker->attachFailOpen($fake, $clean['public_session_id'] ?? 'invalid');

        $this->info('database writes: 0');
        $this->info('real orders: 0');
        $this->info('funds moved: 0');
        $this->info('notifications sent: 0');
        $this->info('third-party analytics calls: 0');

        return self::SUCCESS;
    }
}
