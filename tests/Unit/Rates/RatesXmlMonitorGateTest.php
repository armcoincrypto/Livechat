<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\RatesXmlMonitorGate;
use Tests\TestCase;

final class RatesXmlMonitorGateTest extends TestCase
{
    public function test_flag_path_constant(): void
    {
        $this->assertSame('/var/lib/exswaping/rates-xml-hidden.flag', RatesXmlMonitorGate::FLAG_PATH);
        $this->assertStringContainsString('<rates', RatesXmlMonitorGate::EMPTY_RATES_XML);
        $this->assertSame(0, substr_count(RatesXmlMonitorGate::EMPTY_RATES_XML, '<item>'));
    }

    public function test_empty_xml_is_valid_rates_document(): void
    {
        $xml = simplexml_load_string(RatesXmlMonitorGate::EMPTY_RATES_XML);
        $this->assertNotFalse($xml);
        $this->assertSame('rates', $xml->getName());
    }

    public function test_may_publish_empty_feed_force_override(): void
    {
        $this->assertTrue(RatesXmlMonitorGate::mayPublishEmptyFeed(true));
    }

    public function test_flag_path_override_isolated_from_production(): void
    {
        $tmp = sys_get_temp_dir().'/xml_flag_'.getmypid();
        RatesXmlMonitorGate::setFlagPathOverrideForTests($tmp);
        try {
            $this->assertSame($tmp, RatesXmlMonitorGate::flagPath());
            $this->assertFalse(RatesXmlMonitorGate::isHidden());
            @file_put_contents($tmp, "hidden\n");
            $this->assertTrue(RatesXmlMonitorGate::isHidden());
            RatesXmlMonitorGate::show('unit');
            $this->assertFalse(RatesXmlMonitorGate::isHidden());
        } finally {
            RatesXmlMonitorGate::setFlagPathOverrideForTests(null);
            @unlink($tmp);
        }
        $this->assertSame(RatesXmlMonitorGate::FLAG_PATH, RatesXmlMonitorGate::flagPath());
    }
}
