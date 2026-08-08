<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\AtomicPublicXmlPublisher;
use PHPUnit\Framework\TestCase;

final class AtomicPublicXmlPublisherTest extends TestCase
{
    public function testAtomicPublishNeverLeavesEmptyLiveFile(): void
    {
        $dir = sys_get_temp_dir() . '/xmlpub_' . getmypid();
        @mkdir($dir, 0777, true);
        $live = $dir . '/currencies.xml';
        file_put_contents($live, '<?xml version="1.0"?><rates><item><from>BTC</from><to>SBERRUB</to></item></rates>');

        $pub = new AtomicPublicXmlPublisher();
        $xml = '<?xml version="1.0"?><rates>'
            . str_repeat('<item><from>BTC</from><to>SBERRUB</to><in>1</in><out>1</out></item>', 10)
            . '</rates>';
        $r = $pub->publish($live, $xml, ['min_items' => 1, 'backup' => true]);
        $this->assertTrue($r['published']);
        $this->assertGreaterThan(0, filesize($live));
        $this->assertFileExists($live . '.last-good');
        $this->assertSame(10, substr_count((string) file_get_contents($live), '<item>'));
    }

    public function testRejectsEmptyAndUnparseableXml(): void
    {
        $pub = new AtomicPublicXmlPublisher();
        $this->assertFalse($pub->validateXml('', 1)['ok']);
        $this->assertFalse($pub->validateXml('<rates><item>', 1)['ok']);
    }

    public function testCollapseGuard(): void
    {
        $dir = sys_get_temp_dir() . '/xmlpub2_' . getmypid();
        @mkdir($dir, 0777, true);
        $live = $dir . '/currencies.xml';
        $good = str_repeat('<item><from>A</from><to>B</to></item>', 100);
        file_put_contents($live . '.last-good', '<?xml version="1.0"?><rates>' . $good . '</rates>');
        $pub = new AtomicPublicXmlPublisher();
        $this->assertTrue($pub->collapsesAgainstLastGood($live, 10));
        $this->assertFalse($pub->collapsesAgainstLastGood($live, 80));
    }
}
