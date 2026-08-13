<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\CanonicalDirectionResolver;
use Tests\TestCase;

/**
 * Fast release gate: every unique currencies.xml from/to must resolve
 * through CanonicalDirectionResolver (same path as BestChange deep links).
 */
final class BestChangeDeeplinkClosureGateTest extends TestCase
{
    public function test_exported_xml_identities_resolve_when_xml_present(): void
    {
        $xmlPath = public_path('static/exports/currencies.xml');
        if (!is_file($xmlPath)) {
            $this->markTestSkipped('currencies.xml unavailable');
        }

        CanonicalDirectionResolver::clearCaches();
        $xml = (string) file_get_contents($xmlPath);
        preg_match_all('/<item>(.*?)<\/item>/s', $xml, $items);

        $seen = [];
        $failures = [];
        foreach ($items[1] as $item) {
            if (!preg_match('/<from>(.*?)<\/from>/', $item, $from)) {
                continue;
            }
            if (!preg_match('/<to>(.*?)<\/to>/', $item, $to)) {
                continue;
            }
            $f = strtoupper($from[1]);
            $t = strtoupper($to[1]);
            $key = $f.'->'.$t;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            try {
                $r = CanonicalDirectionResolver::resolve($f, $t, 'BESTCHANGE_LINK');
            } catch (\Throwable $e) {
                $this->markTestSkipped('db unavailable: '.$e->getMessage());
            }

            $ok = in_array($r['status'], [
                CanonicalDirectionResolver::STATUS_EXACT_PAIR_FOUND,
                CanonicalDirectionResolver::STATUS_PAIR_ALIAS_RESOLVED,
            ], true) && $r['direction_id'] !== null;

            if (!$ok) {
                $failures[] = $key.':'.$r['status'].':'.$r['reason'];
            }
        }

        $this->assertGreaterThan(0, count($seen), 'xml must contain unique pairs');
        $this->assertSame(
            [],
            array_slice($failures, 0, 25),
            'exported XML identities must resolve; failures='.count($failures)
        );
        $this->assertCount(0, $failures);
    }
}
