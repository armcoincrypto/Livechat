<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;

/**
 * Ensures the host-CPU index migration for parser_exchange hot path exists
 * and targets the proven scan query shape.
 */
final class ParserExchangeCodePairIndexContractTest extends TestCase
{
    public function test_migration_defines_code_in_out_updated_index(): void
    {
        $files = glob(dirname(__DIR__, 3).'/database/migrations/*parser_exchange_code_in_out_updated_index.php');
        $this->assertNotFalse($files);
        $this->assertNotEmpty($files, 'migration file missing');
        $src = file_get_contents($files[0]);
        $this->assertNotFalse($src);
        $this->assertStringContainsString('idx_parser_exchange_code_in_out_updated', $src);
        $this->assertStringContainsString('code_in', $src);
        $this->assertStringContainsString('code_out', $src);
        $this->assertStringContainsString('updated_at', $src);
        $this->assertStringContainsString("index(['code_in', 'code_out', 'updated_at']", $src);
    }
}
