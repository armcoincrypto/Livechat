<?php
declare(strict_types=1);

namespace iEXPackages\GeoIp\AntiFraud\Rules;

use iEXPackages\GeoIp\AntiFraud\GeoDecision;
use iEXPackages\GeoIp\AntiFraud\PolicyContext;

final class ReadonlyCacheRule implements RuleInterface
{
    public function name(): string { return 'readonly_cache'; }

    public function apply(PolicyContext $ctx): ?GeoDecision
    {
        if (!$ctx->readonlyCache) return null;

        // Например, если GeoIP пустой — не deny, а review
        $iso = $ctx->location->countryIso;
        if ($iso === null || trim($iso) === '') {
            return GeoDecision::review('readonly_cache_country_unknown', [], $this->name());
        }

        return null;
    }
}
