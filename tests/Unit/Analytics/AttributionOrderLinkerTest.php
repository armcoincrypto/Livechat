<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics;

use App\Services\Analytics\AttributionOrderLinker;
use App\Services\Analytics\AttributionSanitizer;
use PHPUnit\Framework\TestCase;

final class AttributionOrderLinkerTest extends TestCase
{
    public function test_attach_fail_open_when_disabled_or_invalid(): void
    {
        putenv('EXS_ATTRIBUTION_ORDER_LINK_ENABLED=false');
        $linker = new AttributionOrderLinker(new AttributionSanitizer());
        $task = new class {
            public $id = 1;
            public $session_attribution_id = null;
            public bool $saved = false;
            public function forceFill(array $a): self { return $this; }
            public function saveQuietly(): bool { $this->saved = true; return true; }
        };
        $linker->attachFailOpen($task, 'validsessionid123456');
        $this->assertFalse($task->saved);

        putenv('EXS_ATTRIBUTION_ORDER_LINK_ENABLED=true');
        $linker->attachFailOpen($task, 'not valid!!!');
        $this->assertFalse($task->saved);
        putenv('EXS_ATTRIBUTION_ORDER_LINK_ENABLED=false');
    }

    public function test_session_id_from_options_prefers_opaque_key(): void
    {
        $linker = new AttributionOrderLinker(new AttributionSanitizer());
        $this->assertSame(
            'abc',
            $linker->sessionIdFromOptions(['public_session_id' => 'abc', 'session_attribution_id' => 99])
        );
    }
}
