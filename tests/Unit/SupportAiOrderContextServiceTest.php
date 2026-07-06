<?php

declare(strict_types=1);

namespace Tests\Unit;

use iEXPackages\SupportChat\Services\SupportAiOrderContextService;
use PHPUnit\Framework\TestCase;

final class SupportAiOrderContextServiceTest extends TestCase
{
    private SupportAiOrderContextService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SupportAiOrderContextService;
    }

    public function test_normalize_plain_order_number(): void
    {
        $this->assertSame('1783284375535', $this->service->normalizeOrderIdentifier('1783284375535'));
    }

    public function test_normalize_order_url(): void
    {
        $this->assertSame(
            '1783284375535',
            $this->service->normalizeOrderIdentifier('https://exswaping.com/order/1783284375535')
        );
    }

    public function test_normalize_rejects_malformed_values(): void
    {
        $this->assertNull($this->service->normalizeOrderIdentifier('abc123'));
        $this->assertNull($this->service->normalizeOrderIdentifier('12345'));
        $this->assertNull($this->service->normalizeOrderIdentifier(''));
    }

    public function test_empty_context_when_no_order_number(): void
    {
        $context = $this->service->lookupForDraft(null, 'ru');

        $this->assertFalse($context['order_lookup_attempted']);
        $this->assertFalse($context['verified_order_status']);
    }

    public function test_build_prompt_block_includes_verified_rule(): void
    {
        $block = $this->service->buildPromptBlock([
            'order_lookup_attempted' => true,
            'order_lookup_found' => true,
            'verified_order_status' => true,
            'order_public_id' => '1783284375535',
            'order_status_human' => 'Ожидает обработки',
            'order_safe_summary' => 'Заявка в обработке.',
        ]);

        $this->assertStringContainsString('verified_order_status: true', $block);
        $this->assertStringContainsString('LEAD with verified status', $block);
        $this->assertStringContainsString('Проверим заявку', $block);
    }
}
