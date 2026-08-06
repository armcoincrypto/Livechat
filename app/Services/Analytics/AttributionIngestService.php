<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\SessionAttribution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Idempotent first/last-touch attribution upsert.
 * Fail-open: never throws to callers that must stay available.
 */
final class AttributionIngestService
{
    public function __construct(private readonly AttributionSanitizer $sanitizer)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, public_session_id: ?string}
     */
    public function ingest(array $payload): array
    {
        if (! AttributionFeatures::ingestEnabled()) {
            return ['ok' => true, 'public_session_id' => null, 'disabled' => true];
        }

        try {
            $clean = $this->sanitizer->sanitizePayload($payload);
            $sessionId = $clean['public_session_id'] ?? null;
            if (! is_string($sessionId) || $sessionId === '') {
                return ['ok' => false, 'public_session_id' => null];
            }

            if (AttributionFeatures::canaryOnly() && ! AttributionFeatures::isCanarySessionId($sessionId)) {
                Log::info('attribution_ingest_rejected_non_canary', ['reason' => 'prefix']);

                return ['ok' => false, 'public_session_id' => null, 'canary_rejected' => true];
            }

            $now = Carbon::now();
            /** @var SessionAttribution|null $row */
            $row = SessionAttribution::query()->where('public_session_id', $sessionId)->first();

            if ($row === null) {
                if (AttributionFeatures::canaryOnly()) {
                    $count = SessionAttribution::query()->count();
                    if ($count >= AttributionFeatures::canaryMaxRows()) {
                        Log::warning('attribution_canary_row_cap_reached', ['count' => $count]);

                        return ['ok' => false, 'public_session_id' => null, 'cap' => true];
                    }
                }

                SessionAttribution::query()->create([
                    'public_session_id' => $sessionId,
                    'first_utm_source' => $clean['utm_source'],
                    'first_utm_medium' => $clean['utm_medium'],
                    'first_utm_campaign' => $clean['utm_campaign'],
                    'first_utm_term' => $clean['utm_term'],
                    'first_utm_content' => $clean['utm_content'],
                    'last_utm_source' => $clean['utm_source'],
                    'last_utm_medium' => $clean['utm_medium'],
                    'last_utm_campaign' => $clean['utm_campaign'],
                    'last_utm_term' => $clean['utm_term'],
                    'last_utm_content' => $clean['utm_content'],
                    'first_referrer' => $clean['referrer'],
                    'last_referrer' => $clean['referrer'],
                    'first_landing_path' => $clean['landing_path'],
                    'last_landing_path' => $clean['landing_path'],
                    'locale' => $clean['locale'],
                    'device_class' => $clean['device_class'],
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                ]);

                return ['ok' => true, 'public_session_id' => $sessionId];
            }

            $updates = [
                'last_seen_at' => $now,
            ];
            if (! empty($clean['locale'])) {
                $updates['locale'] = $clean['locale'];
            }
            if (! empty($clean['device_class'])) {
                $updates['device_class'] = $clean['device_class'];
            }
            if (! empty($clean['landing_path'])) {
                $updates['last_landing_path'] = $clean['landing_path'];
                if (empty($row->first_landing_path)) {
                    $updates['first_landing_path'] = $clean['landing_path'];
                }
            }

            $hasCampaign = ! empty($clean['utm_source']) || ! empty($clean['utm_medium']) || ! empty($clean['utm_campaign']);
            $hasReferrer = ! empty($clean['referrer']);
            if ($hasCampaign || $hasReferrer) {
                if ($hasCampaign) {
                    $updates['last_utm_source'] = $clean['utm_source'];
                    $updates['last_utm_medium'] = $clean['utm_medium'];
                    $updates['last_utm_campaign'] = $clean['utm_campaign'];
                    $updates['last_utm_term'] = $clean['utm_term'];
                    $updates['last_utm_content'] = $clean['utm_content'];
                    if (empty($row->first_utm_source) && empty($row->first_utm_medium) && empty($row->first_utm_campaign)) {
                        $updates['first_utm_source'] = $clean['utm_source'];
                        $updates['first_utm_medium'] = $clean['utm_medium'];
                        $updates['first_utm_campaign'] = $clean['utm_campaign'];
                        $updates['first_utm_term'] = $clean['utm_term'];
                        $updates['first_utm_content'] = $clean['utm_content'];
                    }
                }
                if ($hasReferrer) {
                    $updates['last_referrer'] = $clean['referrer'];
                    if (empty($row->first_referrer)) {
                        $updates['first_referrer'] = $clean['referrer'];
                    }
                }
            }

            $row->fill($updates)->save();

            return ['ok' => true, 'public_session_id' => $sessionId];
        } catch (\Throwable $e) {
            Log::warning('attribution_ingest_failed', [
                'class' => $e::class,
            ]);

            return ['ok' => false, 'public_session_id' => null];
        }
    }
}
