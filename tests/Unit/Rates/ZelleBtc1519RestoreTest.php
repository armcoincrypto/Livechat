<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\PublicDuplicateExclusion;
use PHPUnit\Framework\TestCase;

final class ZelleBtc1519RestoreTest extends TestCase
{
    public function test_1519_not_in_public_exclusions_v9(): void
    {
        PublicDuplicateExclusion::clearCache();
        $path = dirname(__DIR__, 3).'/resources/rates/public-duplicate-exclusions.json';
        $json = json_decode((string) file_get_contents($path), true);
        $this->assertSame(9, (int) ($json['version'] ?? 0));
        $ids = array_map('intval', $json['exclude_direction_ids'] ?? []);
        $this->assertNotContains(1519, $ids);
        $this->assertContains(1971, $ids);
        foreach ([1540, 1541, 1542] as $id) {
            $this->assertNotContains($id, $ids, "ZELLE RUB bank {$id} restored");
        }
        $this->assertFalse(PublicDuplicateExclusion::isExcluded(1519));
    }
}
