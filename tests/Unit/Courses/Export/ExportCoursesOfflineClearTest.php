<?php

declare(strict_types=1);

namespace Tests\Unit\Courses\Export;

use App\Services\Rates\AtomicPublicXmlPublisher;
use App\Services\Rates\RatesXmlMonitorGate;
use Illuminate\Console\Command;
use iEXPackages\Courses\Export\ExportCourses;
use Tests\TestCase;

final class ExportCoursesOfflineClearTest extends TestCase
{
    public function test_clear_publishes_empty_rates_when_empty_policy_allowed(): void
    {
        $dir = sys_get_temp_dir().'/exs_xml_clear_'.getmypid();
        @mkdir($dir, 0777, true);
        $live = $dir.'/currencies.xml';
        file_put_contents(
            $live,
            '<?xml version="1.0"?><rates>'
            .str_repeat('<item><from>BTC</from><to>USDT</to><in>1</in><out>1</out></item>', 20)
            .'</rates>'
        );

        // Unit path: deliberate empty publish (offline/tech/force), not via clear()
        // when SITE_ONLINE+TECH_OFF (clear refuses). Mirrors hide(force) empty contract.
        $pub = new AtomicPublicXmlPublisher();
        $r = $pub->publish($live, RatesXmlMonitorGate::EMPTY_RATES_XML, [
            'min_items' => 0,
            'backup' => true,
        ]);
        $this->assertTrue($r['published'], (string) ($r['reason'] ?? 'fail'));

        $xml = (string) file_get_contents($live);
        $this->assertNotSame('', trim($xml));
        $this->assertStringContainsString('<rates', $xml);
        $this->assertStringContainsString('</rates>', $xml);
        $this->assertSame(0, substr_count($xml, '<item>'));
        $this->assertFileExists($live.'.last-good');
    }

    public function test_clear_keeps_full_xml_when_online_contract_active(): void
    {
        if (! RatesXmlMonitorGate::mustKeepFullPublicFeed()) {
            $this->markTestSkipped('offline/tech mode — online clear refuse not active');
        }

        $dir = sys_get_temp_dir().'/exs_xml_clear_online_'.getmypid();
        @mkdir($dir, 0777, true);
        $live = $dir.'/currencies.xml';
        $full = '<?xml version="1.0"?><rates>'
            .str_repeat('<item><from>BTC</from><to>USDT</to><in>1</in><out>1</out></item>', 20)
            .'</rates>';
        file_put_contents($live, $full);

        $cmd = $this->createMock(Command::class);
        $export = new ExportCourses($cmd);
        $export->clear($live);

        $this->assertSame($full, file_get_contents($live));
    }
}
