<?php
declare(strict_types=1);

namespace iEXPackages\AuthAudit\Jobs;

use iEXPackages\AuthAudit\Models\AuthEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class EnrichAuditEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $eventId) {}

    public function handle(): void
    {
        $event = AuthEvent::query()->find($this->eventId);
        if (! $event || ! $event->ip) {
            return;
        }

        // GeoIP включен?
        if (! (bool) iEXSetting('geoip.toggles.enabled', true)) {
            return;
        }

        try {
            // Берём существующий meta, чтобы не перетирать
            $meta = is_array($event->meta) ? $event->meta : [];

            // Локаль — из meta (если нет, пусть geoip решает сам)
            $locale = $meta['locale'] ?? null;
            $locale = is_string($locale) && trim($locale) !== '' ? $locale : null;

            /** @var \iEXPackages\GeoIp\DTO\Location $loc */
            $loc = geoip($event->ip, $locale);

            // Основные поля в колонках (не затираем, если geoip вернул null)
            $event->country  = $loc->countryName ?: $event->country;
            $event->city     = $loc->cityName ?: $event->city;
            $event->iso_code = $loc->countryIso ?: $event->iso_code;

            // Расширенный контекст — в meta
            $meta['geo'] = $loc->toCompactArray();
            $meta['geo_label'] = $loc->summaryExtended();
            $meta['geo_audit'] = $loc->toAuditContext();

            // Полезно для UI: флаг, timezone, local time
            $meta['geo_flag'] = $loc->flagEmoji();
            $meta['geo_timezone'] = $loc->timeZone;
            $meta['geo_timezone_label'] = $loc->timezoneLabel();
            $meta['geo_local_now'] = $loc->localNow();

            $event->meta = $meta;
            $event->save();
        } catch (\Throwable) {
            // enrichment не должен ломать авторизацию
        }
    }
}
