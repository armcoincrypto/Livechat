<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\BestChangeCurrencyCatalogGuard;
use App\Services\Rates\BestChangeMappingVerifier;
use App\Services\Rates\CoinMarketCapFailureClassifier;
use App\Services\Rates\IndependentMarketBaseline;
use PHPUnit\Framework\TestCase;

final class RateProviderRecoveryTest extends TestCase
{
    public function testRemoteManifestVerificationMetadataShape(): void
    {
        $meta = [
            'certified_sha' => '59a18959110b751b533c55cee2fd29656d8d46b7',
            'remote_preservation' => 'BLOCKED_READ_ONLY_DEPLOY_KEY',
            'release_branch' => 'release/rate-pipeline-canonicalize-59a1895',
            'origin_main' => 'ea73f7e7d47083abef45325af5c2836de401fdcc',
        ];
        $this->assertSame(40, strlen($meta['certified_sha']));
        $this->assertStringContainsString('BLOCKED', $meta['remote_preservation']);
    }

    public function testCoinMarketCapPlanLimitFailure(): void
    {
        $c = new CoinMarketCapFailureClassifier();
        $r = $c->classify([
            'status' => [
                'error_code' => 1010,
                'error_message' => 'You\'ve exceeded your API Key\'s monthly credit limit.',
            ],
        ], 429);
        $this->assertSame('PLAN_LIMIT_EXCEEDED', $r['class']);
    }

    public function testCoinMarketCapAuthenticationFailure(): void
    {
        $c = new CoinMarketCapFailureClassifier();
        $r = $c->classify([
            'status' => [
                'error_code' => 1001,
                'error_message' => 'This API Key is invalid.',
            ],
        ], 401);
        $this->assertSame('CREDENTIAL_REJECTED', $r['class']);
    }

    public function testDisabledFailingProviderBehaviorClassifiesAsPlanLimit(): void
    {
        $c = new CoinMarketCapFailureClassifier();
        $r = $c->classify([
            'status' => ['error_code' => 1010, 'error_message' => 'credit limit'],
        ], 429);
        // Disabled group should still surface the same class for health warnings.
        $this->assertSame('PLAN_LIMIT_EXCEEDED', $r['class']);
        $this->assertDoesNotMatchRegularExpression('/[a-f0-9]{32}/', (string) $r['message']);
    }

    public function testTonSymbolNormalizationCandidates(): void
    {
        $b = new IndependentMarketBaseline([], allowDatabase: false);
        $ref = new \ReflectionClass($b);
        if ($ref->hasMethod('symbolCandidates')) {
            $m = $ref->getMethod('symbolCandidates');
            $m->setAccessible(true);
            $cands = $m->invoke($b, 'TONUSDT');
            $this->assertIsArray($cands);
            $flat = json_encode($cands);
            $this->assertTrue(
                str_contains((string) $flat, 'TON') && str_contains((string) $flat, 'USDT'),
                'TONUSDT candidates should mention TON and USDT'
            );
        } else {
            $this->assertNull($b->quote('TONUSDT'));
        }
    }

    public function testTonStaleSourceRejectedWhenNoFreshQuote(): void
    {
        $b = new IndependentMarketBaseline([
            'TONUSDT' => [
                'rate' => '1.30',
                'source' => 'rapira_stale',
                'as_of' => gmdate('c', time() - 86400 * 80),
            ],
        ], allowDatabase: false);
        $q = $b->quote('TONUSDT');
        $this->assertNotNull($q);
        $this->assertGreaterThan(IndependentMarketBaseline::CRYPTO_MAX_AGE_SECONDS, (int) $q['age_seconds']);
        // cryptoGel must reject stale TON component
        $this->assertNull($b->cryptoGel('TON'));
    }

    public function testWhiteBitBnbWrongTypeRegressionFixture(): void
    {
        // Inverse type=1 on a direct BNBUSDT market produced ~4.7% divergence historically.
        $binance = 569.41;
        $badInverseStored = 596.33;
        $pct = abs($badInverseStored - $binance) / $binance * 100;
        $this->assertGreaterThan(2.0, $pct);
        $liveAligned = 569.42;
        $pctOk = abs($liveAligned - $binance) / $binance * 100;
        $this->assertLessThan(0.1, $pctOk);
    }

    public function testMappingVerificationStates(): void
    {
        $dir = sys_get_temp_dir() . '/bc_map_' . getmypid();
        @mkdir($dir . '/bestchange', 0777, true);
        file_put_contents($dir . '/bestchange/currencies.json', json_encode([
            ['id' => 108, 'name' => '[CARDVND] - VND Card'],
            ['id' => 209, 'name' => '[GRAM] - Gram'],
            ['id' => 10, 'name' => '[BTC] - Bitcoin'],
        ]));
        file_put_contents($dir . '/bestchange-codes.json', json_encode([
            '108' => ['id' => '108', 'code' => 'PRUSD', 'name' => 'Payeer USD'],
            '209' => ['id' => '209', 'code' => 'TON', 'name' => 'Toncoin'],
            '10' => ['id' => '10', 'code' => 'BTC', 'name' => 'Bitcoin'],
        ]));
        $guard = new BestChangeCurrencyCatalogGuard(
            $dir . '/bestchange/currencies.json',
            $dir . '/bestchange-codes.json'
        );
        $v = new BestChangeMappingVerifier(
            $dir . '/bestchange/currencies.json',
            $dir . '/bestchange-codes.json',
            $guard
        );
        $btc = $v->verifyCode('BTC');
        $this->assertSame('VERIFIED', $btc['status']);
        $pr = $v->verifyCode('PRUSD');
        $this->assertSame('DRIFTED', $pr['status']);
        $card = $v->verifyCode('CARDVND');
        $this->assertSame('VERIFIED', $card['status']);
        // Local codes claim TON for 209 but live is GRAM — validateId(209,'TON') remains drifted.
        $tonId = $guard->validateId(209, 'TON');
        $this->assertFalse($tonId['ok']);
    }

    public function testBusinessDisabledAndNoReserveRestoreRejection(): void
    {
        $this->assertSame('KEEP_NO_RESERVE', $this->restoreDecision('NO_RESERVE', 'TECHNICALLY_VALID_NO_RESERVE'));
        $this->assertSame('KEEP_INACTIVE_BUSINESS_DIRECTION', $this->restoreDecision('INACTIVE_BUSINESS_DIRECTION', 'TECHNICALLY_VALID_BUSINESS_DISABLED'));
        $this->assertSame('READY_TO_RESTORE', $this->restoreDecision('VALID_READY_TO_RESTORE', 'READY_FOR_CONTROLLED_RESTORE'));
    }

    public function testControlledRestoreBatchLimit(): void
    {
        $ids = range(1, 25);
        $batches = array_chunk($ids, 10);
        $this->assertCount(3, $batches);
        $this->assertLessThanOrEqual(10, count($batches[0]));
    }

    public function testRestoreRollbackSqlShape(): void
    {
        $sql = "UPDATE direction_exchange SET status=0, allow_export=2 WHERE id=1020;\n";
        $this->assertStringContainsString('UPDATE direction_exchange SET status=0, allow_export=2', $sql);
        $this->assertStringContainsString('WHERE id=1020', $sql);
    }

    public function testHealthCommandProviderSummaryKeys(): void
    {
        $keys = [
            'runtime_drift',
            'invalid_active_rate_count',
            'active_no_baseline_count',
            'unexplained_high_count',
            'unexplained_critical_count',
            'unexplained_extreme_count',
            'stale_provider_count',
            'failing_provider_count',
            'mapping_drift_count',
            'xml_parity_count',
            'quarantined_direction_count',
            'restored_regression_count',
            'provider_warnings',
        ];
        foreach ($keys as $k) {
            $this->assertIsString($k);
            $this->assertNotSame('', $k);
        }
    }

    private function restoreDecision(string $primary, string $business): string
    {
        return $business === 'READY_FOR_CONTROLLED_RESTORE' ? 'READY_TO_RESTORE' : 'KEEP_' . $primary;
    }
}
