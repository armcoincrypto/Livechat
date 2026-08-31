<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\BestChangeRateGenerationMarker;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\TestCase;

final class BestChangeRateGenerationMarkerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(\Illuminate\Foundation\Application::class)) {
            $this->markTestSkipped('Laravel not bootstrapped in this unit harness');
        }
    }

    public function test_marker_contract_exists_and_documents_generation_semantics(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/Rates/BestChangeRateGenerationMarker.php'
        );
        $this->assertStringContainsString('iex:bc:rate_generation', $src);
        $this->assertStringContainsString('markCommitted', $src);

        $console = (string) file_get_contents(
            dirname(__DIR__, 3) . '/packages/BestChange/Console/BestchangeUpdateRatesConsole.php'
        );
        $this->assertStringContainsString('BestChangeRateGenerationMarker::markCommitted', $console);
        $this->assertStringContainsString('withUpdateDirections()', $console);
        // Marker must be AFTER withUpdateDirections, not between BC and DE.
        $posUpdate = strpos($console, 'updateRates()');
        $posRecalc = strpos($console, 'withUpdateDirections()');
        $posMark = strpos($console, 'BestChangeRateGenerationMarker::markCommitted');
        $this->assertNotFalse($posUpdate);
        $this->assertNotFalse($posRecalc);
        $this->assertNotFalse($posMark);
        $this->assertGreaterThan($posRecalc, $posMark);
        $this->assertGreaterThan($posUpdate, $posRecalc);
    }

    public function test_wrapper_scheme_files_uses_independent_lock(): void
    {
        $wrapper = (string) file_get_contents(
            '/opt/exswaping-owned-frontend/scripts/ops/run_backend_compiler.sh'
        );
        $this->assertStringContainsString('EXS_LOCK_SCHEME_FILES', $wrapper);
        $this->assertStringContainsString('XML_REQUIRES_COMPILER_LOCK=NO', $wrapper);
        $this->assertStringContainsString('POST_BC_XML_REFRESH', $wrapper);
        $this->assertStringContainsString('scheme-files.lock', (string) file_get_contents(
            '/opt/exswaping-owned-frontend/scripts/ops/lib/scoped_locks.sh'
        ));
    }
}
