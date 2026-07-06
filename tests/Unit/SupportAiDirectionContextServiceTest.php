<?php

declare(strict_types=1);

namespace Tests\Unit;

use iEXPackages\SupportChat\Services\SupportAiDirectionContextService;
use PHPUnit\Framework\TestCase;

final class SupportAiDirectionContextServiceTest extends TestCase
{
    private SupportAiDirectionContextService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SupportAiDirectionContextService;
    }

    public function test_extract_pair_from_russian_slang_message(): void
    {
        $pair = $this->service->extractPairFromMessage(
            'Добрый день, Хочу поменять ерц на монеро. сможете посмотреть кошелек перед обменом?'
        );

        $this->assertIsArray($pair);
        $this->assertSame('ерц', mb_strtolower($pair['from'], 'UTF-8'));
        $this->assertSame('монеро', mb_strtolower($pair['to'], 'UTF-8'));
    }

    public function test_resolve_slang_designations(): void
    {
        $this->assertSame('USDTERC20', $this->service->resolveDesignation('ерц'));
        $this->assertSame('USDTTRC20', $this->service->resolveDesignation('трц'));
        $this->assertSame('XMR', $this->service->resolveDesignation('монеро'));
        $this->assertSame('BTC', $this->service->resolveDesignation('биток'));
        $this->assertSame('SBERRUB', $this->service->resolveDesignation('сбер'));
        $this->assertSame('TCSBRUB', $this->service->resolveDesignation('тинькофф'));
    }

    public function test_resolve_usdt_trc20_to_sber_tokens(): void
    {
        $this->assertSame('USDTTRC20', $this->service->resolveDesignation('USDT TRC20'));
        $this->assertSame('SBERRUB', $this->service->resolveDesignation('SBER'));
    }

    public function test_unknown_asset_returns_null_designation(): void
    {
        $this->assertNull($this->service->resolveDesignation('notarealassetxyz'));
    }

    public function test_message_without_direction_does_not_detect_pair(): void
    {
        $pair = $this->service->extractPairFromMessage('Здравствуйте, где моя заявка №1783284375535?');

        $this->assertNull($pair);
    }

    public function test_build_prompt_block_includes_availability_rules(): void
    {
        $block = $this->service->buildPromptBlock([
            'direction_lookup_attempted' => true,
            'direction_lookup_found' => true,
            'detected' => true,
            'direction_normalized' => 'USDTERC20 → XMR',
            'availability_status' => 'unsupported',
            'safe_summary' => 'Направление недоступно.',
        ]);

        $this->assertStringContainsString('availability_status: unsupported', $block);
        $this->assertStringContainsString('Do NOT open with "Уточните номер кошелька"', $block);
    }
}
