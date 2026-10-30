<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Services\Analytics\AttributionFeatures;
use App\Services\Rates\BestChangeL1Authority;
use App\Services\Rates\BestChangeL1Shadow;
use App\Services\Rates\FastMarket\FastMarketFlags;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guards CC1: feature readers must not call env() directly (config:cache safe).
 */
final class ConfigCacheEnvCompatTest extends TestCase
{
    public function test_attribution_features_source_uses_config_not_env(): void
    {
        $src = file_get_contents((new ReflectionMethod(AttributionFeatures::class, 'ingestEnabled'))->getFileName());
        $this->assertIsString($src);
        $this->assertStringContainsString("config('attribution.", $src);
        $this->assertStringNotContainsString("env('EXS_ATTRIBUTION_", $src);
    }

    public function test_bestchange_l1_authority_prefers_config_bake(): void
    {
        $src = file_get_contents((new ReflectionMethod(BestChangeL1Authority::class, 'enabled'))->getFileName());
        $this->assertIsString($src);
        $this->assertStringContainsString('bestchange_l1.authority_enabled', $src);
        $this->assertStringNotContainsString("env('BESTCHANGE_L1_AUTHORITY_ENABLED'", $src);
    }

    public function test_fast_market_flags_remain_config_backed(): void
    {
        $src = file_get_contents((new ReflectionMethod(FastMarketFlags::class, 'planeEnabled'))->getFileName());
        $this->assertIsString($src);
        $this->assertStringContainsString("config('fast_market.", $src);
        $this->assertStringNotContainsString('env(', $src);
    }

    public function test_shadow_source_uses_config_keys(): void
    {
        $src = file_get_contents((new ReflectionMethod(BestChangeL1Shadow::class, 'shadowEnabled'))->getFileName());
        $this->assertIsString($src);
        $this->assertStringContainsString('bestchange_l1.shadow_enabled', $src);
    }
}
