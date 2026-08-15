<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\DTO;

/**
 * Нормализованная GeoIP-локация.
 *
 * Особенности:
 * - Поддерживает Debug/Explain mode через meta (payload['_meta'])
 * - Добавлены UI helpers (флаг, подписи, timezone/UTC offset, локальное время)
 *
 * @property-read Subdivision[] $subdivisions
 */
final class Location
{
    /**
     * Отладочная мета-информация (explain), заполняется из payload['_meta'].
     * Не участвует в toArray(), чтобы не смешивать бизнес-данные и отладку.
     *
     * @var array<string,mixed>
     */
    private array $meta = [];

    /**
     * @param Subdivision[] $subdivisions
     */
    public function __construct(
        public readonly string $ip,

        public readonly ?string $continentCode = null,
        public readonly ?string $continentName = null,
        public readonly ?int $continentGeonameId = null,

        public readonly ?string $countryIso = null,
        public readonly ?string $countryName = null,
        public readonly ?int $countryGeonameId = null,
        public readonly ?bool $countryIsEU = null,

        public readonly ?string $registeredCountryIso = null,
        public readonly ?string $registeredCountryName = null,
        public readonly ?int $registeredCountryGeonameId = null,
        public readonly ?bool $registeredCountryIsEU = null,

        public readonly ?string $cityName = null,
        public readonly ?int $cityGeonameId = null,

        public readonly ?string $regionIso = null,
        public readonly ?string $regionName = null,

        public readonly ?string $districtIso = null,
        public readonly ?string $districtName = null,

        public readonly array $subdivisions = [],

        public readonly ?string $postalCode = null,

        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?int $accuracyRadius = null,
        public readonly ?string $timeZone = null,

        public readonly ?string $network = null,
        public readonly ?int $asn = null,
        public readonly ?string $asnOrg = null,

        public readonly ?bool $isTorExitNode = null,
        public readonly ?bool $isAnonymousVpn = null,
        public readonly ?bool $isHostingProvider = null,
    ) {}

    public static function empty(string $ip): self
    {
        return new self(ip: $ip);
    }

    /**
     * Создаёт Location из массива (обычно из кеша).
     * Если присутствует ключ '_meta' — он будет доступен через explain().
     *
     * @param array<string,mixed> $a
     */
    public static function fromArray(array $a): self
    {
        $continent = is_array($a['continent'] ?? null) ? $a['continent'] : [];
        $country   = is_array($a['country'] ?? null) ? $a['country'] : [];
        $reg       = is_array($a['registered_country'] ?? null) ? $a['registered_country'] : [];
        $city      = is_array($a['city'] ?? null) ? $a['city'] : [];
        $region    = is_array($a['region'] ?? null) ? $a['region'] : [];
        $district  = is_array($a['district'] ?? null) ? $a['district'] : [];
        $loc       = is_array($a['location'] ?? null) ? $a['location'] : [];
        $traits    = is_array($a['traits'] ?? null) ? $a['traits'] : [];

        $subs = [];
        if (isset($a['subdivisions']) && is_array($a['subdivisions'])) {
            foreach ($a['subdivisions'] as $s) {
                if (is_array($s)) {
                    $subs[] = Subdivision::fromArray($s);
                }
            }
        }

        $obj = new self(
            ip: is_string($a['ip'] ?? null) ? $a['ip'] : '0.0.0.0',

            continentCode: is_string($continent['code'] ?? null) ? $continent['code'] : null,
            continentName: is_string($continent['name'] ?? null) ? $continent['name'] : null,
            continentGeonameId: is_numeric($continent['geoname_id'] ?? null) ? (int)$continent['geoname_id'] : null,

            countryIso: is_string($country['iso'] ?? null) ? $country['iso'] : null,
            countryName: is_string($country['name'] ?? null) ? $country['name'] : null,
            countryGeonameId: is_numeric($country['geoname_id'] ?? null) ? (int)$country['geoname_id'] : null,
            countryIsEU: is_bool($country['is_eu'] ?? null) ? $country['is_eu'] : null,

            registeredCountryIso: is_string($reg['iso'] ?? null) ? $reg['iso'] : null,
            registeredCountryName: is_string($reg['name'] ?? null) ? $reg['name'] : null,
            registeredCountryGeonameId: is_numeric($reg['geoname_id'] ?? null) ? (int)$reg['geoname_id'] : null,
            registeredCountryIsEU: is_bool($reg['is_eu'] ?? null) ? $reg['is_eu'] : null,

            cityName: is_string($city['name'] ?? null) ? $city['name'] : null,
            cityGeonameId: is_numeric($city['geoname_id'] ?? null) ? (int)$city['geoname_id'] : null,

            regionIso: is_string($region['iso'] ?? null) ? $region['iso'] : null,
            regionName: is_string($region['name'] ?? null) ? $region['name'] : null,

            districtIso: is_string($district['iso'] ?? null) ? $district['iso'] : null,
            districtName: is_string($district['name'] ?? null) ? $district['name'] : null,

            subdivisions: $subs,

            postalCode: is_string($a['postal'] ?? null) ? $a['postal'] : null,

            latitude: is_numeric($loc['lat'] ?? null) ? (float)$loc['lat'] : null,
            longitude: is_numeric($loc['lon'] ?? null) ? (float)$loc['lon'] : null,
            accuracyRadius: is_numeric($loc['accuracy_radius'] ?? null) ? (int)$loc['accuracy_radius'] : null,
            timeZone: is_string($loc['timezone'] ?? null) ? $loc['timezone'] : null,

            network: is_string($traits['network'] ?? null) ? $traits['network'] : null,
            asn: is_numeric($traits['asn'] ?? null) ? (int)$traits['asn'] : null,
            asnOrg: is_string($traits['asn_org'] ?? null) ? $traits['asn_org'] : null,

            isTorExitNode: is_bool($traits['is_tor_exit_node'] ?? null) ? $traits['is_tor_exit_node'] : null,
            isAnonymousVpn: is_bool($traits['is_anonymous_vpn'] ?? null) ? $traits['is_anonymous_vpn'] : null,
            isHostingProvider: is_bool($traits['is_hosting_provider'] ?? null) ? $traits['is_hosting_provider'] : null,
        );

        $meta = $a['_meta'] ?? null;
        if (is_array($meta)) {
            $obj->setMeta($meta);
        }

        return $obj;
    }

    /**
     * Привязка мета-информации (explain).
     *
     * @param array<string,mixed> $meta
     * @internal
     */
    public function setMeta(array $meta): void
    {
        $this->meta = $meta;
    }

    /**
     * Возвращает отладочную информацию (Debug/Explain mode).
     *
     * @return array<string,mixed>
     */
    public function explain(): array
    {
        return $this->meta;
    }

    public function toArray(): array
    {
        return [
            'ip' => $this->ip,

            'continent' => [
                'code' => $this->continentCode,
                'name' => $this->continentName,
                'geoname_id' => $this->continentGeonameId,
            ],
            'country' => [
                'iso' => $this->countryIso,
                'name' => $this->countryName,
                'geoname_id' => $this->countryGeonameId,
                'is_eu' => $this->countryIsEU,
            ],
            'registered_country' => [
                'iso' => $this->registeredCountryIso,
                'name' => $this->registeredCountryName,
                'geoname_id' => $this->registeredCountryGeonameId,
                'is_eu' => $this->registeredCountryIsEU,
            ],
            'city' => [
                'name' => $this->cityName,
                'geoname_id' => $this->cityGeonameId,
            ],
            'region' => [
                'iso' => $this->regionIso,
                'name' => $this->regionName,
            ],
            'district' => [
                'iso' => $this->districtIso,
                'name' => $this->districtName,
            ],
            'subdivisions' => array_map(static fn (Subdivision $s) => $s->toArray(), $this->subdivisions),
            'postal' => $this->postalCode,
            'location' => [
                'lat' => $this->latitude,
                'lon' => $this->longitude,
                'accuracy_radius' => $this->accuracyRadius,
                'timezone' => $this->timeZone,
            ],
            'traits' => [
                'network' => $this->network,
                'asn' => $this->asn,
                'asn_org' => $this->asnOrg,
                'is_tor_exit_node' => $this->isTorExitNode,
                'is_anonymous_vpn' => $this->isAnonymousVpn,
                'is_hosting_provider' => $this->isHostingProvider,
            ],
        ];
    }

    public function toCompactArray(): array
    {
        return [
            'country_iso' => $this->countryIso,
            'country' => $this->countryName,
            'city' => $this->cityName,
            'region' => $this->regionName,
            'timezone' => $this->timeZone,
            'lat' => $this->latitude,
            'lon' => $this->longitude,
            'asn' => $this->asn,
            'asn_org' => $this->asnOrg,
            'risk' => [
                'tor' => $this->isTorExitNode,
                'vpn' => $this->isAnonymousVpn,
                'hosting' => $this->isHostingProvider,
            ],
        ];
    }

    public function summary(): string
    {
        $parts = [];
        if ($this->countryIso) $parts[] = $this->countryIso;
        if ($this->cityName) $parts[] = $this->cityName;
        if ($this->regionName && $this->regionName !== $this->cityName) $parts[] = $this->regionName;
        if ($this->timeZone) $parts[] = $this->timeZone;

        return $parts !== [] ? implode(', ', $parts) : 'Unknown';
    }

    public function summaryExtended(): string
    {
        $parts = [];

        // Континент + ISO страны
        if ($this->continentName) {
            $iso = $this->countryIso;
            $parts[] = $iso ? sprintf('%s (%s)', $this->continentName, $iso) : $this->continentName;
        }

        // Страна
        if ($this->countryName) {
            $parts[] = $this->countryName;
        }

        // Регион (область) — у тебя это Ида-Вирумаа
        if ($this->regionName) {
            $parts[] = $this->regionName;
        }

        // “Республика/район” — у тебя это district (Jõhvi vald)
        // добавляем только если не совпадает с регионом/городом
        if ($this->districtName && $this->districtName !== $this->regionName && $this->districtName !== $this->cityName) {
            $parts[] = $this->districtName;
        }

        // Город
        if ($this->cityName) {
            $parts[] = $this->cityName;
        }

        return $parts !== [] ? implode(', ', $parts) : 'Unknown';
    }

    public function toAuditContext(): array
    {
        return [
            'geoip_country_iso' => $this->countryIso,
            'geoip_city' => $this->cityName,
            'geoip_region' => $this->regionName,
            'geoip_timezone' => $this->timeZone,
            'geoip_asn' => $this->asn,
            'geoip_asn_org' => $this->asnOrg,
            'geoip_risk' => [
                'tor' => $this->isTorExitNode,
                'vpn' => $this->isAnonymousVpn,
                'hosting' => $this->isHostingProvider,
            ],
        ];
    }

    public function toOrderPayload(): array
    {
        return $this->toCompactArray();
    }

    // ---------------------------
    // UI helpers
    // ---------------------------

    /**
     * Emoji флаг страны по ISO (пример: EE => 🇪🇪).
     */
    public function flagEmoji(): ?string
    {
        $iso = $this->countryIso();
        if (!$iso || strlen($iso) !== 2) {
            return null;
        }

        $iso = strtoupper($iso);
        $a = ord($iso[0]) - 65 + 0x1F1E6;
        $b = ord($iso[1]) - 65 + 0x1F1E6;

        if ($a < 0x1F1E6 || $a > 0x1F1FF || $b < 0x1F1E6 || $b > 0x1F1FF) {
            return null;
        }

        return mb_chr($a, 'UTF-8') . mb_chr($b, 'UTF-8');
    }

    /**
     * Подпись страны (например: "Estonia (EE)").
     */
    public function countryLabel(bool $withIso = true): ?string
    {
        $name = $this->countryName ?? null;
        $iso  = $this->countryIso();

        if ($name && $withIso && $iso) {
            return "{$name} ({$iso})";
        }

        return $name ?: $iso;
    }

    /**
     * UTC offset по таймзоне (например: "+02:00").
     */
    public function utcOffset(): ?string
    {
        $tz = $this->timeZone ?? null;
        if (!$tz) return null;

        try {
            $dtz = new \DateTimeZone($tz);
            $now = new \DateTimeImmutable('now', $dtz);

            $seconds = $dtz->getOffset($now);
            $sign = $seconds >= 0 ? '+' : '-';
            $seconds = abs($seconds);

            $hours = intdiv($seconds, 3600);
            $mins  = intdiv($seconds % 3600, 60);

            return sprintf('%s%02d:%02d', $sign, $hours, $mins);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Подпись таймзоны (например: "Europe/Tallinn (UTC+02:00)").
     */
    public function timezoneLabel(): ?string
    {
        $tz = $this->timeZone ?? null;
        if (!$tz) return null;

        $offset = $this->utcOffset();
        return $offset ? "{$tz} (UTC{$offset})" : $tz;
    }

    /**
     * Текущее локальное время в найденной таймзоне.
     */
    public function localNow(string $format = 'Y-m-d H:i:s'): ?string
    {
        $tz = $this->timeZone ?? null;
        if (!$tz) return null;

        try {
            return (new \DateTimeImmutable('now', new \DateTimeZone($tz)))->format($format);
        } catch (\Throwable) {
            return null;
        }
    }

    // ---------------------------
    // Shortcuts
    // ---------------------------

    public function countryIso(): ?string { return $this->countryIso; }
    public function city(): ?string { return $this->cityName; }
}
