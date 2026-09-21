<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\AtomicPublicXmlPublisher;
use App\Services\Rates\RatesXmlMonitorGate;
use App\Services\Rates\RatesXmlPublicFeedHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use iEXPackages\Courses\Export\ExportCourses;
use Tests\TestCase;

/**
 * Monitor reliability contract:
 * A online publish never empties live file
 * B/C/D invalid/empty candidate keeps last-good
 * E offline empty allowed
 * F show idempotent
 * G/H lock + refuse online clear
 * I health invariant
 */
final class RatesXmlMonitorReliabilityTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/xml_rel_'.getmypid().'_'.bin2hex(random_bytes(3));
        @mkdir($this->dir, 0777, true);
        RatesXmlMonitorGate::setFlagPathOverrideForTests($this->dir.'/rates-xml-hidden.flag');
    }

    protected function tearDown(): void
    {
        RatesXmlMonitorGate::setFlagPathOverrideForTests(null);
        parent::tearDown();
    }

    public function test_a_atomic_publish_replaces_full_with_full_never_empty_window(): void
    {
        $live = $this->dir.'/currencies.xml';
        $old = '<?xml version="1.0"?><rates>'
            .str_repeat('<item><from>A</from><to>B</to><in>1</in><out>1</out></item>', 50)
            .'</rates>';
        file_put_contents($live, $old);
        $beforeSha = hash_file('sha256', $live);

        $new = '<?xml version="1.0"?><rates>'
            .str_repeat('<item><from>C</from><to>D</to><in>1</in><out>2</out></item>', 60)
            .'</rates>';
        $pub = new AtomicPublicXmlPublisher();
        $r = $pub->publish($live, $new, ['min_items' => 1, 'backup' => true]);
        $this->assertTrue($r['published']);
        $this->assertSame(60, substr_count((string) file_get_contents($live), '<item>'));
        $this->assertNotSame($beforeSha, hash_file('sha256', $live));
        $this->assertFileExists($live.'.last-good');
        $this->assertGreaterThan(200, filesize($live));
    }

    public function test_b_c_d_invalid_or_empty_candidate_keeps_last_good(): void
    {
        $live = $this->dir.'/keep.xml';
        $full = '<?xml version="1.0"?><rates>'
            .str_repeat('<item><from>A</from><to>B</to></item>', 40)
            .'</rates>';
        file_put_contents($live, $full);
        $before = file_get_contents($live);

        $pub = new AtomicPublicXmlPublisher();
        $this->assertFalse($pub->publish($live, '', ['min_items' => 1])['published']);
        $this->assertSame($before, file_get_contents($live));

        $this->assertFalse($pub->publish($live, '<rates><item>', ['min_items' => 1])['published']);
        $this->assertSame($before, file_get_contents($live));

        $empty = RatesXmlMonitorGate::EMPTY_RATES_XML;
        $this->assertFalse($pub->publish($live, $empty, ['min_items' => 1])['published']);
        $this->assertSame($before, file_get_contents($live));
    }

    public function test_e_offline_empty_allowed_via_force_policy(): void
    {
        $live = $this->dir.'/offline.xml';
        file_put_contents(
            $live,
            '<?xml version="1.0"?><rates>'
            .str_repeat('<item><from>A</from><to>B</to></item>', 20)
            .'</rates>'
        );

        // Force policy simulates deliberate offline/fail-closed (unit tests cannot
        // flip production WorkStatus safely).
        $this->assertTrue(RatesXmlMonitorGate::mayPublishEmptyFeed(true));
        $pub = new AtomicPublicXmlPublisher();
        $r = $pub->publish($live, RatesXmlMonitorGate::EMPTY_RATES_XML, [
            'min_items' => 0,
            'backup' => true,
        ]);
        $this->assertTrue($r['published']);
        $this->assertSame(0, substr_count((string) file_get_contents($live), '<item>'));
        $this->assertFileExists($live.'.last-good');
    }

    public function test_f_show_is_idempotent_and_clears_flag(): void
    {
        @file_put_contents(RatesXmlMonitorGate::flagPath(), "{\"hidden\":true,\"reason\":\"test\"}\n");
        $this->assertTrue(RatesXmlMonitorGate::isHidden());
        RatesXmlMonitorGate::show('test_show_1');
        $this->assertFalse(RatesXmlMonitorGate::isHidden());
        RatesXmlMonitorGate::show('test_show_2');
        $this->assertFalse(RatesXmlMonitorGate::isHidden());
    }

    public function test_g_online_clear_refuses_to_empty_when_must_keep_full(): void
    {
        // When production site is online (typical CI/dev), clear must refuse.
        // If the environment is offline, skip this assertion.
        if (! RatesXmlMonitorGate::mustKeepFullPublicFeed()) {
            $this->markTestSkipped('WorkStatus offline / tech mode — online refuse path not active');
        }

        $live = $this->dir.'/online_clear.xml';
        $full = '<?xml version="1.0"?><rates>'
            .str_repeat('<item><from>A</from><to>B</to></item>', 25)
            .'</rates>';
        file_put_contents($live, $full);

        $cmd = $this->createMock(Command::class);
        $export = new ExportCourses($cmd);
        $export->clear($live);

        $this->assertSame($full, file_get_contents($live));
        $this->assertFalse(RatesXmlMonitorGate::isHidden());
    }

    public function test_h_hide_refused_online_without_force_leaves_flag_absent(): void
    {
        if (! RatesXmlMonitorGate::mustKeepFullPublicFeed()) {
            $this->markTestSkipped('WorkStatus offline / tech mode — hide refuse path not active');
        }

        RatesXmlMonitorGate::hide('unit_test_should_refuse');
        $this->assertFalse(RatesXmlMonitorGate::isHidden());
    }

    public function test_i_health_snapshot_shape_and_empty_detect(): void
    {
        $snap = RatesXmlPublicFeedHealth::snapshot($this->dir.'/missing.xml');
        $this->assertArrayHasKey('bytes', $snap);
        $this->assertArrayHasKey('rows', $snap);
        $this->assertArrayHasKey('gate_hidden', $snap);
        $this->assertArrayHasKey('must_keep_full', $snap);
        $this->assertArrayHasKey('invariant_ok', $snap);
        $this->assertFalse($snap['exists']);
        $this->assertSame(0, $snap['rows']);

        $emptyPath = $this->dir.'/empty177.xml';
        file_put_contents($emptyPath, RatesXmlMonitorGate::EMPTY_RATES_XML);
        $snap2 = RatesXmlPublicFeedHealth::snapshot($emptyPath);
        $this->assertTrue($snap2['empty_rates_match']);
        $this->assertSame(0, $snap2['rows']);
        $this->assertSame(strlen(RatesXmlMonitorGate::EMPTY_RATES_XML), $snap2['bytes']);
    }

    public function test_export_put_rejects_empty_candidate_when_online(): void
    {
        if (! RatesXmlMonitorGate::mustKeepFullPublicFeed()) {
            $this->markTestSkipped('offline/tech — online put refuse not active');
        }

        $live = $this->dir.'/put_keep.xml';
        $full = '<?xml version="1.0"?><rates>'
            .str_repeat('<item><from>A</from><to>B</to></item>', 15)
            .'</rates>';
        file_put_contents($live, $full);

        $cmd = $this->createMock(Command::class);
        $export = new ExportCourses($cmd);
        $ref = new \ReflectionClass($export);
        $m = $ref->getMethod('put');
        $m->setAccessible(true);
        $m->invoke($export, $live, RatesXmlMonitorGate::EMPTY_RATES_XML);

        $this->assertSame($full, file_get_contents($live));
    }

    public function test_contract_constants_and_source_guards(): void
    {
        $this->assertSame(0, substr_count(RatesXmlMonitorGate::EMPTY_RATES_XML, '<item>'));
        $gate = file_get_contents(base_path('app/Services/Rates/RatesXmlMonitorGate.php'));
        $this->assertStringContainsString('mustKeepFullPublicFeed', $gate);
        $this->assertStringContainsString('forceEmptyPolicy', $gate);
        $export = file_get_contents(base_path('packages/Courses/Export/ExportCourses.php'));
        $this->assertStringContainsString('rates_xml_clear_blocked_site_online', $export);
        $this->assertStringContainsString('rates_xml_PUBLISH_REJECTED', $export);
        $scheme = file_get_contents(base_path('app/Console/Commands/StatusRatesFilesCommand.php'));
        $this->assertStringContainsString('acquireXmlPublishLock', $scheme);
        $this->assertStringContainsString('scheme:files_online_finally', $scheme);
        $start = file_get_contents(base_path('app/Console/Commands/StartModeOperation.php'));
        $this->assertStringContainsString('forceEmptyPolicy: true', $start);
    }
}
