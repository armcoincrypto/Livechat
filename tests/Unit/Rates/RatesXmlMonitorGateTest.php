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
}
