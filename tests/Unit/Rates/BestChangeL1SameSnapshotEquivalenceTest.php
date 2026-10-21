<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Services\Rates\BestChangeExternalTopNController;
use App\Services\Rates\BestChangeL1SameSnapshotDiagnostic;
use App\Services\Rates\BestChangeTopNBookHelper;
use App\Settings\BestChangeConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Offline same-snapshot harness: AUTHORITATIVE(book X) ≡ L1(book X).
 * No network. No DB. Shares production BestChangeExternalTopNController::adjust.
 */
final class BestChangeL1SameSnapshotEquivalenceTest extends TestCase
{
    private string $evidenceDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evidenceDir = sys_get_temp_dir().'/l1_same_snap_test_'.bin2hex(random_bytes(4));
        @mkdir($this->evidenceDir, 0750, true);
        putenv(BestChangeL1SameSnapshotDiagnostic::FLAG.'=false');
        $_ENV[BestChangeL1SameSnapshotDiagnostic::FLAG] = 'false';
    }

    protected function tearDown(): void
    {
        putenv(BestChangeL1SameSnapshotDiagnostic::FLAG);
        unset($_ENV[BestChangeL1SameSnapshotDiagnostic::FLAG]);
        foreach (glob($this->evidenceDir.'/mismatch_*.json') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->evidenceDir);
        parent::tearDown();
    }

    public function test_diagnostic_flag_defaults_false(): void
    {
        putenv(BestChangeL1SameSnapshotDiagnostic::FLAG);
        unset($_ENV[BestChangeL1SameSnapshotDiagnostic::FLAG]);
        $this->assertFalse(BestChangeL1SameSnapshotDiagnostic::enabled());
    }

    public function test_inspect_flag_state_detects_shared_true_effective_false_mismatch(): void
    {
        $dir = sys_get_temp_dir().'/l1_flag_insp_'.bin2hex(random_bytes(4));
        @mkdir($dir, 0750, true);
        $shared = $dir.'/shared.env';
        $live = $dir.'/live.env';
        file_put_contents($shared, "BESTCHANGE_L1_SAME_SNAPSHOT_DIAGNOSTIC=true\n");
        file_put_contents($live, "BESTCHANGE_L1_SAME_SNAPSHOT_DIAGNOSTIC=false\n");
        putenv(BestChangeL1SameSnapshotDiagnostic::FLAG.'=false');
        $_ENV[BestChangeL1SameSnapshotDiagnostic::FLAG] = 'false';

        $state = BestChangeL1SameSnapshotDiagnostic::inspectFlagState($live, $shared);
        $this->assertSame(1, $state['diagnostic_requested']);
        $this->assertSame(0, $state['diagnostic_effective']);
        $this->assertSame(1, $state['mismatch']);
        $this->assertStringContainsString('release-local-file', $state['config_source']);

        @unlink($shared);
        @unlink($live);
        @rmdir($dir);
    }

    public function test_inspect_flag_state_aligned_when_both_false(): void
    {
        $dir = sys_get_temp_dir().'/l1_flag_insp_'.bin2hex(random_bytes(4));
        @mkdir($dir, 0750, true);
        $shared = $dir.'/shared.env';
        $live = $dir.'/live.env';
        file_put_contents($shared, "BESTCHANGE_L1_SAME_SNAPSHOT_DIAGNOSTIC=false\n");
        file_put_contents($live, "BESTCHANGE_L1_SAME_SNAPSHOT_DIAGNOSTIC=false\n");
        putenv(BestChangeL1SameSnapshotDiagnostic::FLAG.'=false');
        $_ENV[BestChangeL1SameSnapshotDiagnostic::FLAG] = 'false';

        $state = BestChangeL1SameSnapshotDiagnostic::inspectFlagState($live, $shared);
        $this->assertSame(0, $state['diagnostic_requested']);
        $this->assertSame(0, $state['diagnostic_effective']);
        $this->assertSame(0, $state['mismatch']);

        @unlink($shared);
        @unlink($live);
        @rmdir($dir);
    }

    public function test_parse_env_file_bool_reads_last_assignment(): void
    {
        $path = sys_get_temp_dir().'/l1_env_parse_'.bin2hex(random_bytes(4)).'.env';
        file_put_contents($path, "FOO=1\nBESTCHANGE_L1_SAME_SNAPSHOT_DIAGNOSTIC=false\nBESTCHANGE_L1_SAME_SNAPSHOT_DIAGNOSTIC=true\n");
        $this->assertTrue(BestChangeL1SameSnapshotDiagnostic::parseEnvFileBool(
            $path,
            BestChangeL1SameSnapshotDiagnostic::FLAG
        ));
        @unlink($path);
    }

    public function test_deterministic_sampling_rotates_by_salt(): void
    {
        $stride = BestChangeL1SameSnapshotDiagnostic::SAMPLE_STRIDE;
        // (id + salt) % stride === 0
        $this->assertTrue(BestChangeL1SameSnapshotDiagnostic::shouldSample($stride, 0));
        $this->assertFalse(BestChangeL1SameSnapshotDiagnostic::shouldSample($stride, 1));
        $this->assertTrue(BestChangeL1SameSnapshotDiagnostic::shouldSample($stride - 1, 1));
    }

    /**
     * @dataProvider sameBookFixtureProvider
     *
     * @param  array<int, array<string, mixed>>  $rawBook
     */
    public function test_same_book_authoritative_equals_l1(
        string $case,
        array $rawBook,
        string $baseCourse,
        int $position,
    ): void {
        $topN = $this->controller();
        $dir = $this->fakeDirection(1526);
        $auth = BestChangeL1SameSnapshotDiagnostic::evaluateSelection(
            $topN, $dir, $baseCourse, $rawBook, 4, $position, 'authoritative'
        );
        $l1 = BestChangeL1SameSnapshotDiagnostic::evaluateSelection(
            $topN, $dir, $baseCourse, $rawBook, 4, $position, 'l1'
        );

        $this->assertSame($auth['book_hash'], $l1['book_hash'], $case.': book_hash');
        $classified = BestChangeL1SameSnapshotDiagnostic::compare($auth, $l1, 1526);
        $this->assertTrue($classified['same_book_hash'], $case);
        $this->assertTrue($classified['match'], $case.' class='.$classified['class']);
        $this->assertContains(
            $classified['class'],
            [
                BestChangeL1SameSnapshotDiagnostic::CLASS_OK,
                BestChangeL1SameSnapshotDiagnostic::CLASS_REPRESENTATION_ONLY,
            ],
            $case
        );
    }

    /**
     * @return array<string, array{0: string, 1: array<int, array<string, mixed>>, 2: string, 3: int}>
     */
    public static function sameBookFixtureProvider(): array
    {
        $happy = [
            ['changer' => 1, 'rate' => '0.998901', 'reserve' => '10'],
            ['changer' => 2, 'rate' => '0.9990009192', 'reserve' => '20'],
            ['changer' => 3, 'rate' => '0.9991', 'reserve' => '30'],
            ['changer' => 4, 'rate' => '0.9992', 'reserve' => '40'],
            ['changer' => 1522, 'rate' => '0.9995', 'reserve' => '50'],
        ];

        return [
            'happy_path_depth5' => ['happy_path_depth5', $happy, '1.0005', 3],
            'fewer_rows_than_position' => [
                'fewer_rows_than_position',
                [
                    ['changer' => 1, 'rate' => '0.99'],
                    ['changer' => 1522, 'rate' => '1.01'],
                ],
                '1.00',
                5,
            ],
            'equal_rates' => [
                'equal_rates',
                [
                    ['changer' => 10, 'rate' => '1.0000'],
                    ['changer' => 11, 'rate' => '1.0000'],
                    ['changer' => 12, 'rate' => '1.0000'],
                    ['changer' => 1522, 'rate' => '1.0000'],
                ],
                '1.0001',
                2,
            ],
            'duplicate_exchangers' => [
                'duplicate_exchangers',
                [
                    ['changer' => 7, 'rate' => '0.990'],
                    ['changer' => 7, 'rate' => '0.991'],
                    ['changer' => 8, 'rate' => '0.992'],
                    ['changer' => 1522, 'rate' => '0.995'],
                ],
                '1.00',
                2,
            ],
            'own_exchanger_filtered' => [
                'own_exchanger_filtered',
                [
                    ['changer' => 1522, 'rate' => '0.50'],
                    ['changer' => 1, 'rate' => '0.99'],
                    ['changer' => 2, 'rate' => '1.00'],
                    ['changer' => 3, 'rate' => '1.01'],
                ],
                '1.02',
                2,
            ],
            'reserve_noise_ignored' => [
                'reserve_noise_ignored',
                [
                    ['changer' => 1, 'rate' => '0.99', 'reserve' => '1'],
                    ['changer' => 2, 'rate' => '1.00', 'reserve' => '999999'],
                    ['changer' => 3, 'rate' => '1.01', 'minamount' => '0'],
                    ['changer' => 1522, 'rate' => '1.02', 'maxamount' => '1'],
                ],
                '1.03',
                3,
            ],
            'min_rate_zero_dropped' => [
                'min_rate_zero_dropped',
                [
                    ['changer' => 1, 'rate' => '0'],
                    ['changer' => 2, 'rate' => '-1'],
                    ['changer' => 3, 'rate' => '1.0'],
                    ['changer' => 4, 'rate' => '1.1'],
                    ['changer' => 1522, 'rate' => '1.2'],
                ],
                '1.3',
                2,
            ],
            'missing_fields' => [
                'missing_fields',
                [
                    ['rate' => '1.0'],
                    ['changer' => 5],
                    ['changer' => 6, 'rate' => '1.05'],
                    ['changer' => 7, 'rate' => '1.06'],
                    ['changer' => 1522, 'rate' => '1.07'],
                ],
                '1.08',
                2,
            ],
            'malformed_rows' => [
                'malformed_rows',
                [
                    'not-an-array',
                    null,
                    ['changer' => 'abc', 'rate' => 'xyz'],
                    ['changer' => 9, 'rate' => '0.95'],
                    ['changer' => 10, 'rate' => '0.96'],
                    ['changer' => 1522, 'rate' => '0.97'],
                ],
                '1.0',
                2,
            ],
            'ascending_descending_reorder' => [
                'ascending_descending_reorder',
                array_reverse($happy),
                '1.0005',
                3,
            ],
            'source_extra_keys' => [
                'source_extra_keys',
                array_map(static function (array $r): array {
                    $r['rankrate'] = $r['rate'];
                    $r['presence'] = 'ignored';
                    $r['source_name'] = 'BestChange';

                    return $r;
                }, $happy),
                '1.0005',
                3,
            ],
            'thin_book_fallback' => [
                'thin_book_fallback',
                [
                    ['changer' => 1, 'rate' => '1.0'],
                ],
                '1.0',
                3,
            ],
            'already_at_target' => [
                'already_at_target',
                [
                    ['changer' => 1522, 'rate' => '0.90'],
                    ['changer' => 1, 'rate' => '0.95'],
                    ['changer' => 2, 'rate' => '1.00'],
                    ['changer' => 3, 'rate' => '1.05'],
                ],
                '1.10',
                3,
            ],
        ];
    }

    public function test_reorder_preserves_book_hash_and_result(): void
    {
        $asc = [
            ['changer' => 1, 'rate' => '0.99'],
            ['changer' => 2, 'rate' => '1.00'],
            ['changer' => 3, 'rate' => '1.01'],
            ['changer' => 1522, 'rate' => '1.02'],
        ];
        $desc = array_reverse($asc);
        $this->assertSame(
            BestChangeTopNBookHelper::fingerprint($asc),
            BestChangeTopNBookHelper::fingerprint($desc)
        );
        $topN = $this->controller();
        $dir = $this->fakeDirection(100);
        $a = BestChangeL1SameSnapshotDiagnostic::evaluateSelection($topN, $dir, '1.05', $asc, 4, 2, 'a');
        $b = BestChangeL1SameSnapshotDiagnostic::evaluateSelection($topN, $dir, '1.05', $desc, 4, 2, 'b');
        $c = BestChangeL1SameSnapshotDiagnostic::compare($a, $b, 100);
        $this->assertTrue($c['match']);
        $this->assertTrue($c['same_book_hash']);
    }

    public function test_different_books_flag_hash_mismatch_not_timing_race(): void
    {
        $topN = $this->controller();
        $dir = $this->fakeDirection(200);
        $a = BestChangeL1SameSnapshotDiagnostic::evaluateSelection(
            $topN, $dir, '1.0',
            [['changer' => 1, 'rate' => '1.0'], ['changer' => 2, 'rate' => '1.1'], ['changer' => 1522, 'rate' => '1.2']],
            4, 2, 'auth'
        );
        $b = BestChangeL1SameSnapshotDiagnostic::evaluateSelection(
            $topN, $dir, '1.0',
            [['changer' => 1, 'rate' => '1.0'], ['changer' => 9, 'rate' => '9.9'], ['changer' => 1522, 'rate' => '1.2']],
            4, 2, 'l1'
        );
        $c = BestChangeL1SameSnapshotDiagnostic::compare($a, $b, 200);
        $this->assertFalse($c['same_book_hash']);
        $this->assertNotSame('TIMING_RACE', $c['class']);
    }

    public function test_flag_false_runFailSoft_is_noop(): void
    {
        putenv(BestChangeL1SameSnapshotDiagnostic::FLAG.'=false');
        $_ENV[BestChangeL1SameSnapshotDiagnostic::FLAG] = 'false';
        $metrics = [];
        BestChangeL1SameSnapshotDiagnostic::runFailSoft(
            $this->controller(),
            $this->fakeDirection(BestChangeL1SameSnapshotDiagnostic::SAMPLE_STRIDE),
            '1.0',
            [['changer' => 1, 'rate' => '1.0'], ['changer' => 2, 'rate' => '1.1'], ['changer' => 1522, 'rate' => '1.2']],
            4,
            2,
            $metrics,
            0,
            $this->evidenceDir,
        );
        $this->assertSame([], $metrics);
    }

    public function test_diagnostic_fail_mode_log_and_continue(): void
    {
        putenv(BestChangeL1SameSnapshotDiagnostic::FLAG.'=true');
        $_ENV[BestChangeL1SameSnapshotDiagnostic::FLAG] = 'true';

        $boom = $this->createMock(BestChangeExternalTopNController::class);
        $boom->method('adjust')->willThrowException(new RuntimeException('diag boom'));

        $metrics = [];
        BestChangeL1SameSnapshotDiagnostic::runFailSoft(
            $boom,
            $this->fakeDirection(BestChangeL1SameSnapshotDiagnostic::SAMPLE_STRIDE),
            '1.0',
            [['changer' => 1, 'rate' => '1.0']],
            4,
            2,
            $metrics,
            0,
            $this->evidenceDir,
        );
        $this->assertSame(1, $metrics['bc_same_snapshot_diagnostic_errors'] ?? 0);
        $this->assertArrayNotHasKey('bc_same_snapshot_compare_count', $metrics);
    }

    public function test_enabled_path_records_match_and_zero_side_effects(): void
    {
        putenv(BestChangeL1SameSnapshotDiagnostic::FLAG.'=true');
        $_ENV[BestChangeL1SameSnapshotDiagnostic::FLAG] = 'true';
        $metrics = [];
        BestChangeL1SameSnapshotDiagnostic::runFailSoft(
            $this->controller(),
            $this->fakeDirection(BestChangeL1SameSnapshotDiagnostic::SAMPLE_STRIDE),
            '1.0005',
            [
                ['changer' => 1, 'rate' => '0.998'],
                ['changer' => 2, 'rate' => '0.999'],
                ['changer' => 3, 'rate' => '1.000'],
                ['changer' => 1522, 'rate' => '1.001'],
            ],
            4,
            2,
            $metrics,
            0,
            $this->evidenceDir,
        );
        $this->assertSame(1, $metrics['bc_same_snapshot_compare_count'] ?? 0);
        $this->assertSame(1, $metrics['bc_same_snapshot_match'] ?? 0);
        $this->assertSame(0, $metrics['bc_same_snapshot_extra_network_fetches'] ?? -1);
        $this->assertSame(0, $metrics['bc_same_snapshot_production_writes'] ?? -1);
    }

    public function test_fixture_retention_bounds(): void
    {
        $base = time() - 1000;
        for ($i = 0; $i < BestChangeL1SameSnapshotDiagnostic::MAX_FIXTURES + 5; $i++) {
            BestChangeL1SameSnapshotDiagnostic::captureMismatchFixture(
                [
                    'direction_id' => $i,
                    'book_hash' => hash('sha256', (string) $i),
                    'authoritative_selected_row_id' => 1,
                    'l1_selected_row_id' => 2,
                    'authoritative_rate' => '1',
                    'l1_rate' => '2',
                    'authoritative_position_result' => 1,
                    'l1_position_result' => 2,
                    'authoritative_course' => '1',
                    'l1_course' => '2',
                    'authoritative_status' => 'A',
                    'l1_status' => 'B',
                    'authoritative_reason' => 'x',
                    'l1_reason' => 'y',
                ],
                [['changer' => 1, 'rate' => '1.0']],
                2,
                '1.0',
                BestChangeL1SameSnapshotDiagnostic::CLASS_ALGORITHM_MISMATCH,
                $this->evidenceDir,
            );
            $files = glob($this->evidenceDir.'/mismatch_*.json') ?: [];
            if ($files !== []) {
                $newest = $files[array_key_last($files)];
                @touch($newest, $base + $i);
            }
            BestChangeL1SameSnapshotDiagnostic::enforceRetention($this->evidenceDir);
        }
        $files = glob($this->evidenceDir.'/mismatch_*.json') ?: [];
        $this->assertLessThanOrEqual(
            BestChangeL1SameSnapshotDiagnostic::MAX_FIXTURES,
            count($files)
        );
    }

    public function test_schema_after_normalize_is_changer_rate_only(): void
    {
        $norm = BestChangeTopNBookHelper::normalizeForTopN([
            ['changer' => 1, 'rate' => '1.0', 'reserve' => '9', 'noise' => true],
        ]);
        $this->assertSame(['changer', 'rate'], array_keys($norm[0]));
    }

    private function controller(): BestChangeExternalTopNController
    {
        return new BestChangeExternalTopNController($this->mockConfig());
    }

    private function mockConfig(): BestChangeConfig
    {
        $cfg = $this->createMock(BestChangeConfig::class);
        $cfg->method('externalTopNEnabled')->willReturn(true);
        $cfg->method('coinZelleExternalPositionEnabled')->willReturn(true);
        $cfg->method('externalTopNFamilyEnabled')->willReturn(true);
        $cfg->method('externalTopNDirectionIds')->willReturn([]);
        $cfg->method('coinZelleControllerDirectionIds')->willReturn([]);
        $cfg->method('ownExchangerId')->willReturn(1522);
        $cfg->method('externalTopNMinBookDepth')->willReturn(3);
        $cfg->method('coinZelleMinBookDepth')->willReturn(3);
        $cfg->method('coinZelleExternalPositionTarget')->willReturn(3);
        $cfg->method('externalTopNMinSafeMarginVsBoundaryBps')->willReturn(0.0);
        $cfg->method('coinZelleMinSafeMarginVsTop1Bps')->willReturn(0.0);
        $cfg->method('externalTopNOutlierGuardEnabled')->willReturn(true);
        $cfg->method('coinZelleOutlierGuardEnabled')->willReturn(true);
        $cfg->method('externalTopNOutlierSepBps')->willReturn(200.0);
        $cfg->method('coinZelleOutlierSepBps')->willReturn(200.0);

        return $cfg;
    }

    private function fakeDirection(int $id): DirectionExchange
    {
        $from = new Currency();
        $from->designation_xml = 'BTC';
        $to = new Currency();
        $to->designation_xml = 'ZELLEUSD';
        $to->number_format = 4;
        $d = new DirectionExchange();
        $d->id = $id;
        $d->parser_source_name = 'BestChange';
        $d->setRelation('currency1', $from);
        $d->setRelation('currency2', $to);

        return $d;
    }
}
