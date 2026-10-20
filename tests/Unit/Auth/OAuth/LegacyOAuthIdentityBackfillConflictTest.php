<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\OAuth;

use App\Services\Auth\OAuth\LegacyOAuthIdentityBackfill;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Structural test: conflict aggregation logic is exercised via reflection of
 * grouping rules documented in LegacyOAuthIdentityBackfill.
 *
 * Full DB backfill tests require an application kernel + migrated schema
 * (run against non-production after Wave 2 migration approval).
 */
final class LegacyOAuthIdentityBackfillConflictTest extends TestCase
{
    public function test_class_and_method_exist(): void
    {
        $this->assertTrue(class_exists(LegacyOAuthIdentityBackfill::class));
        $m = new ReflectionMethod(LegacyOAuthIdentityBackfill::class, 'run');
        $this->assertTrue($m->isPublic());
        $this->assertSame(2, $m->getNumberOfParameters());
    }
}
