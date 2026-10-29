<?php

declare(strict_types=1);

namespace Tests\Unit\Ops;

use PHPUnit\Framework\TestCase;

/**
 * Contract: after compiler:bestchange financial success, ops must release
 * bestchange-compiler.lock before nested POST_BC scheme:files.
 */
final class PostBcFinancialFlockDecoupleContractTest extends TestCase
{
    private function wrapperSource(): string
    {
        $candidates = [
            '/opt/exswaping-owned-ops/current/scripts/ops/run_backend_compiler.sh',
            dirname(__DIR__, 3).'/../../../../opt/exswaping-owned-ops/current/scripts/ops/run_backend_compiler.sh',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                $src = file_get_contents($path);
                if (is_string($src) && $src !== '') {
                    return $src;
                }
            }
        }
        $this->fail('run_backend_compiler.sh not found');
    }

    public function test_releases_financial_flock_before_post_bc(): void
    {
        $src = $this->wrapperSource();
        $this->assertStringContainsString('BC_FINANCIAL_FLOCK_RELEASE begin reason=post-bc-decouple', $src);
        $this->assertStringContainsString('BC_FINANCIAL_FLOCK_RELEASE done', $src);
        $this->assertStringContainsString('POST_BC_XML_REFRESH begin', $src);

        $relPos = strpos($src, 'BC_FINANCIAL_FLOCK_RELEASE begin reason=post-bc-decouple');
        $postPos = strpos($src, 'POST_BC_XML_REFRESH begin');
        $this->assertNotFalse($relPos);
        $this->assertNotFalse($postPos);
        $this->assertLessThan($postPos, $relPos, 'financial flock release must precede POST_BC begin');
    }

    public function test_post_bc_still_uses_scheme_files_lock_and_debounce(): void
    {
        $src = $this->wrapperSource();
        $this->assertStringContainsString('EXSWAPING_SCHEME_FILES_INVOCATION=post-bc', $src);
        $this->assertStringContainsString('exswaping_scheme_files_clear_post_bc_success', $src);
        $this->assertStringContainsString('scheme:files', $src);
        // Nested post-bc failure must not fail the BC financial exit path.
        $this->assertStringContainsString('WARN POST_BC_XML_REFRESH failed', $src);
    }

    public function test_schedule_documents_decouple_contract(): void
    {
        $sched = file_get_contents(dirname(__DIR__, 3).'/app/Console/Schedule/ExchangeSchedule.php');
        $this->assertNotFalse($sched);
        $this->assertStringContainsString('releases bestchange-compiler.lock BEFORE', $sched);
        $this->assertStringContainsString('scheme-files.lock', $sched);
    }

    public function test_stale_fallback_fail_closed_still_present(): void
    {
        $ddr = file_get_contents(dirname(__DIR__, 3).'/packages/ExchangerClient/Http/Resources/Operations/DirectionDetailResource.php');
        $this->assertNotFalse($ddr);
        $this->assertStringContainsString('fail_closed', $ddr);
        $qs = file_get_contents(dirname(__DIR__, 3).'/app/Services/Rates/FastMarket/FastMarketQuoteService.php');
        $this->assertNotFalse($qs);
        $this->assertStringContainsString('unknown age must not authorize', $qs);
    }
}
