<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\AntiFraud;

use App\Models\GeoCountryList;
use Illuminate\Contracts\Cache\Factory as CacheFactory;

final class CountryNameResolver
{
    public function __construct(
        private readonly ?CacheFactory $cacheFactory = null,
    ) {}

    /**
     * Получить названия стран по ISO списку.
     *
     * @param string[] $isoList
     * @return array<string,string|null> map ISO => name|null
     */
    public function names(array $isoList, string $locale = 'ru'): array
    {
        $isoList = $this->normalizeIsoList($isoList);
        if ($isoList === []) {
            return [];
        }

        $locale = $locale !== '' ? $locale : 'ru';

        // Кешируем список целиком на локаль
        $cacheKey = 'geoip:countries:names:'.$locale.':'.md5(implode(',', $isoList));
        $ttl = 3600; // 1 час

        if ($this->cacheFactory) {
            return $this->cacheFactory->store()->remember($cacheKey, $ttl, fn () => $this->loadFromDb($isoList, $locale));
        }

        return $this->loadFromDb($isoList, $locale);
    }

    /**
     * @param string[] $isoList
     * @return array<string,string|null>
     */
    private function loadFromDb(array $isoList, string $locale): array
    {
        $rows = GeoCountryList::query()
            ->whereIn('code', $isoList)
            ->get(['code', 'value']);

        $map = [];
        foreach ($rows as $row) {
            $code = strtoupper((string) $row->code);

            // Spatie: value — переводимое поле, можно так:
            // $row->getTranslation('value', $locale, false)
            // Но у тебя value иногда содержит только ru, поэтому fallback:
            $name = null;

            try {
                $name = $row->getTranslation('value', $locale, false);
            } catch (\Throwable) {
                // ignore
            }

            if (!is_string($name) || trim($name) === '') {
                try {
                    $name = $row->getTranslation('value', 'ru', false);
                } catch (\Throwable) {
                    // ignore
                }
            }

            if (!is_string($name) || trim($name) === '') {
                try {
                    $name = $row->getTranslation('value', 'en', false);
                } catch (\Throwable) {
                    // ignore
                }
            }

            $map[$code] = (is_string($name) && trim($name) !== '') ? trim($name) : null;
        }

        // Заполним отсутствующие ISO null’ами (чтобы UI мог показать список полностью)
        foreach ($isoList as $iso) {
            $iso = strtoupper($iso);
            $map[$iso] = $map[$iso] ?? null;
        }

        ksort($map);

        return $map;
    }

    /**
     * @param string[] $list
     * @return string[]
     */
    private function normalizeIsoList(array $list): array
    {
        $out = [];
        foreach ($list as $iso) {
            if (!is_string($iso)) continue;
            $iso = strtoupper(trim($iso));
            if ($iso === '' || strlen($iso) !== 2) continue;
            $out[$iso] = true;
        }
        return array_keys($out);
    }
}
