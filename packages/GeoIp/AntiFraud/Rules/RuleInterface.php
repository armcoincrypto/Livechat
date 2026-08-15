<?php
declare(strict_types=1);

namespace iEXPackages\GeoIp\AntiFraud\Rules;

use iEXPackages\GeoIp\AntiFraud\GeoDecision;
use iEXPackages\GeoIp\AntiFraud\PolicyContext;

interface RuleInterface
{
    public function name(): string;

    /**
     * Возвращает GeoDecision если правило сработало, иначе null.
     */
    public function apply(PolicyContext $ctx): ?GeoDecision;
}
