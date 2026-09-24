<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;

/**
 * Permanent Wave-1 GRAM/TON DERIVED→BESTCHANGE persistence invariant.
 *
 * Fail-closed: none of the certified migration IDs may re-enter the
 * derived-market-baseline-directions.json registry (owns() / block_bestchange_overwrite).
 * Asserts the candidate worktree config that will ship — not live FPM memo.
 */
final class GramBestChangeMigratedRegistryInvariantTest extends TestCase
{
    /** @var list<int> */
    private const MIGRATED_IDS = [
        132, 410, 411, 412, 413, 414, 415, 416, 417, 418,
        419, 420, 421, 422, 423, 424, 425, 426,
    ];

    public function test_exclusion_manifest_lists_all_eighteen(): void
    {
        $manifest = $this->manifest();
        $ids = array_map('intval', $manifest['direction_ids'] ?? []);
        sort($ids);
        $expected = self::MIGRATED_IDS;
        sort($expected);
        $this->assertSame($expected, $ids);
        $this->assertSame(410, (int) ($manifest['golden_fixture_id'] ?? 0));
        $this->assertSame('BestChange', (string) ($manifest['expected_owner'] ?? ''));
    }

    public function test_none_of_migrated_gram_ids_appear_in_derived_registry(): void
    {
        $cfg = $this->directions();
        $present = [];
        foreach (self::MIGRATED_IDS as $id) {
            if (isset($cfg[(string) $id]) || isset($cfg[$id])) {
                $present[] = $id;
            }
        }
        $this->assertSame(
            [],
            $present,
            'GRAM Wave-1 IDs must be absent from derived-market-baseline-directions.json; present=' . json_encode($present)
        );
    }

    public function test_no_block_bestchange_overwrite_can_exist_for_migrated_ids(): void
    {
        $cfg = $this->directions();
        foreach (self::MIGRATED_IDS as $id) {
            $row = $cfg[(string) $id] ?? $cfg[$id] ?? null;
            if ($row === null) {
                continue;
            }
            $this->assertFalse(
                !empty($row['ownership']['block_bestchange_overwrite']),
                "migrated GRAM id {$id} must not carry block_bestchange_overwrite"
            );
        }
        // Explicit: registry absence is the primary invariant (already asserted).
        $this->assertTrue(true);
    }

    public function test_golden_410_absent_and_manifest_marks_bestchange(): void
    {
        $cfg = $this->directions();
        $this->assertArrayNotHasKey('410', $cfg);
        $manifest = $this->manifest();
        $this->assertSame(410, (int) $manifest['golden_fixture_id']);
        $this->assertContains(410, array_map('intval', $manifest['direction_ids']));
    }

    public function test_old_poison_registry_with_gram_ids_fails_invariant_helper(): void
    {
        $poison = [
            'directions' => [
                '410' => [
                    'ownership' => [
                        'parser_source_name' => 'DERIVED_MARKET_BASELINE',
                        'block_bestchange_overwrite' => true,
                    ],
                ],
                '132' => [
                    'ownership' => [
                        'block_bestchange_overwrite' => true,
                    ],
                ],
            ],
        ];
        $found = $this->findMigratedInRegistry($poison['directions']);
        $this->assertSame([132, 410], $found);
        $this->assertNotSame([], $found, 'OLD_REGISTRY_CANNOT_SHIP_SILENTLY helper must detect poison');
    }

    public function test_stripped_registry_helper_passes(): void
    {
        $found = $this->findMigratedInRegistry($this->directions());
        $this->assertSame([], $found);
    }

    /**
     * @param array<string|int, mixed> $directions
     * @return list<int>
     */
    private function findMigratedInRegistry(array $directions): array
    {
        $found = [];
        foreach (self::MIGRATED_IDS as $id) {
            if (isset($directions[(string) $id]) || isset($directions[$id])) {
                $found[] = $id;
            }
        }
        sort($found);

        return $found;
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        $path = dirname(__DIR__, 3) . '/resources/rates/gram-bestchange-migrated-ids.json';
        $this->assertFileExists($path);
        $json = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($json);

        return $json;
    }

    /** @return array<string, mixed> */
    private function directions(): array
    {
        $path = dirname(__DIR__, 3) . '/resources/rates/derived-market-baseline-directions.json';
        $this->assertFileExists($path);
        $json = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($json);
        $cfg = $json['directions'] ?? [];
        $this->assertIsArray($cfg);

        return $cfg;
    }
}
