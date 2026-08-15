<?php

declare(strict_types=1);

namespace Tests\Unit\Courses\Export;

use Illuminate\Console\Command;
use iEXPackages\Courses\Export\ExportCourses;
use Tests\TestCase;

final class ExportCoursesOfflineClearTest extends TestCase
{
    public function test_clear_publishes_empty_rates_not_zero_byte_file(): void
    {
        $dir = sys_get_temp_dir() . '/exs_xml_clear_' . getmypid();
        @mkdir($dir, 0777, true);
        $live = $dir . '/currencies.xml';
        file_put_contents(
            $live,
            '<?xml version="1.0"?><rates>'
            . str_repeat('<item><from>BTC</from><to>USDT</to><in>1</in><out>1</out></item>', 20)
            . '</rates>'
        );

        $cmd = $this->createMock(Command::class);
        $export = new ExportCourses($cmd);
        $export->clear($live);

        $xml = (string) file_get_contents($live);
        $this->assertNotSame('', trim($xml));
        $this->assertStringContainsString('<rates', $xml);
        $this->assertStringContainsString('</rates>', $xml);
        $this->assertSame(0, substr_count($xml, '<item>'));
        $this->assertFileExists($live . '.last-good');
    }
}
