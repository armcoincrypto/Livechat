<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\AntiFraud;

use iEXPackages\GeoIp\DTO\Location;

final class GeoPolicy
{
    public function __construct(
        private readonly CountryNameResolver $countries
    ) {}

    /**
     * Проверка по ISO (allow/deny) + отдаёт списки с названиями (для UI).
     *
     * Правила:
     * 1) deny имеет приоритет
     * 2) если allow задан (не пустой) — разрешаем только allow
     */
    public function check(
        Location $loc,
        array $allowIso = [],
        array $denyIso = [],
        string $locale = 'ru',
        bool $denyIfUnknown = true,
    ): GeoCheckResult {
        $countryIso  = $this->normIso($loc->countryIso);
        $countryName = $loc->countryName;

        $allow = $this->normalizeIsoList($allowIso);
        $deny  = $this->normalizeIsoList($denyIso);

        $namesAllow = $this->countries->names($allow, $locale);
        $namesDeny  = $this->countries->names($deny, $locale);

        $allowList = $this->decorate($allow, $namesAllow);
        $denyList  = $this->decorate($deny, $namesDeny);

        if ($countryIso === null) {
            return new GeoCheckResult(
                allowed: !$denyIfUnknown,
                reason: $denyIfUnknown ? 'country_unknown_denied' : 'country_unknown_allowed',
                countryIso: null,
                countryName: null,
                allowList: $allowList,
                denyList: $denyList
            );
        }

        if ($deny !== [] && in_array($countryIso, $deny, true)) {
            return new GeoCheckResult(
                allowed: false,
                reason: 'country_denied',
                countryIso: $countryIso,
                countryName: $countryName,
                allowList: $allowList,
                denyList: $denyList
            );
        }

        if ($allow !== [] && !in_array($countryIso, $allow, true)) {
            return new GeoCheckResult(
                allowed: false,
                reason: 'country_not_in_allow_list',
                countryIso: $countryIso,
                countryName: $countryName,
                allowList: $allowList,
                denyList: $denyList
            );
        }

        return new GeoCheckResult(
            allowed: true,
            reason: 'ok',
            countryIso: $countryIso,
            countryName: $countryName,
            allowList: $allowList,
            denyList: $denyList
        );
    }

    /**
     * @param string[] $isoList
     * @param array<string,string|null> $names
     * @return array<int,array{iso:string,name:string|null}>
     */
    private function decorate(array $isoList, array $names): array
    {
        $out = [];
        foreach ($isoList as $iso) {
            $out[] = [
                'iso' => $iso,
                'name' => $names[$iso] ?? null,
            ];
        }
        usort($out, static fn ($a, $b) => strcmp($a['iso'], $b['iso']));
        return $out;
    }

    /**
     * @param string[] $list
     * @return string[]
     */
    private function normalizeIsoList(array $list): array
    {
        $out = [];
        foreach ($list as $iso) {
            $n = $this->normIso(is_string($iso) ? $iso : null);
            if ($n !== null) $out[$n] = true;
        }
        return array_keys($out);
    }

    private function normIso(?string $iso): ?string
    {
        $iso = $iso !== null ? strtoupper(trim($iso)) : '';
        return ($iso !== '' && strlen($iso) === 2) ? $iso : null;
    }
}
