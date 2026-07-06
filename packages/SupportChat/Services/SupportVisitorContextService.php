<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use iEXPackages\GeoIp\GeoIp;
use Throwable;

/**
 * Admin-only visitor context (country/locale/page/timezone). Does not expose raw IP in UI.
 */
final class SupportVisitorContextService
{
    public function __construct(
        private readonly GeoIp $geoIp,
    ) {}

    /**
     * @return array{
     *     country_code: string|null,
     *     country_name: string,
     *     flag_emoji: string|null,
     *     country_display: string,
     *     locale: string|null,
     *     page_url: string|null,
     *     timezone: string|null
     * }
     */
    public function resolve(SupportConversation $conversation): array
    {
        $countryCode = null;
        $countryName = null;
        $flagEmoji = null;
        $geoTimezone = null;

        $ip = trim((string) ($conversation->visitor_ip ?? ''));
        if ($ip !== '') {
            try {
                $location = $this->geoIp->country($ip);
                $countryCode = $location->countryIso();
                $countryName = $location->countryName();
                $flagEmoji = $location->flagEmoji();
                $geoTimezone = $location->timeZone;
            } catch (Throwable) {
                // GeoIP optional — fall back to Unknown
            }
        }

        $timezone = $this->resolveVisitorTimezone($conversation) ?? $geoTimezone;

        $displayName = $countryName !== null && $countryName !== ''
            ? $countryName
            : 'Unknown';

        $countryDisplay = $flagEmoji !== null && $flagEmoji !== ''
            ? trim($flagEmoji.' '.$displayName)
            : $displayName;

        return [
            'country_code' => $countryCode,
            'country_name' => $displayName,
            'flag_emoji' => $flagEmoji,
            'country_display' => $countryDisplay,
            'locale' => $conversation->locale,
            'page_url' => $conversation->page_url,
            'timezone' => $timezone,
        ];
    }

    private function resolveVisitorTimezone(SupportConversation $conversation): ?string
    {
        if ($conversation->relationLoaded('messages')) {
            foreach ($conversation->messages as $message) {
                if ($message->sender_type !== SupportMessage::SENDER_VISITOR) {
                    continue;
                }

                $tz = $this->timezoneFromMetadata($message->metadata);
                if ($tz !== null) {
                    return $tz;
                }
            }

            return null;
        }

        $first = $conversation->messages()
            ->where('sender_type', SupportMessage::SENDER_VISITOR)
            ->orderBy('id')
            ->value('metadata');

        return $this->timezoneFromMetadata($first);
    }

    /**
     * @param  array<string, mixed>|string|null  $metadata
     */
    private function timezoneFromMetadata(mixed $metadata): ?string
    {
        if (! is_array($metadata)) {
            return null;
        }

        $tz = $metadata['visitor_timezone'] ?? $metadata['timezone'] ?? null;
        if (! is_string($tz)) {
            return null;
        }

        $tz = trim($tz);
        if ($tz === '' || strlen($tz) > 64) {
            return null;
        }

        return $tz;
    }
}
