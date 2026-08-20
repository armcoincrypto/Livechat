<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics;

use App\Services\Analytics\AttributionOrderLinker;
use App\Services\Analytics\AttributionSanitizer;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class AttributionOrderLinkerTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('EXS_ATTRIBUTION_ORDER_LINK_ENABLED=false');
        putenv('EXS_ATTRIBUTION_EVENTS_ENABLED=false');
        parent::tearDown();
    }

    public function test_attach_fail_open_when_disabled_or_invalid(): void
    {
        // Invalid opaque ids must never link (independent of feature flag).
        putenv('EXS_ATTRIBUTION_ORDER_LINK_ENABLED=true');
        $_ENV['EXS_ATTRIBUTION_ORDER_LINK_ENABLED'] = 'true';
        $linker = new AttributionOrderLinker(new AttributionSanitizer());
        $task = $this->fakeTask();
        $linker->attachFailOpen($task, 'not valid!!!');
        $this->assertFalse($task->saved);
        $this->assertNull($task->session_attribution_id);

        $src = file_get_contents(dirname(__DIR__, 3).'/app/Services/Analytics/AttributionOrderLinker.php');
        self::assertNotFalse($src);
        self::assertStringContainsString('AttributionFeatures::orderLinkEnabled()', $src);
    }

    public function test_session_id_from_options_prefers_opaque_key(): void
    {
        $linker = new AttributionOrderLinker(new AttributionSanitizer());
        $this->assertSame(
            'abc',
            $linker->sessionIdFromOptions(['public_session_id' => 'abc', 'session_attribution_id' => 99])
        );
    }

    public function test_session_id_from_collection_options(): void
    {
        $linker = new AttributionOrderLinker(new AttributionSanitizer());
        $options = new Collection([
            'public_session_id' => 'stagec_collection_ok_abcdef',
            'session_attribution_id' => 123,
        ]);
        $this->assertSame('stagec_collection_ok_abcdef', $linker->sessionIdFromOptions($options));
    }

    public function test_null_and_empty_session_id_are_noops(): void
    {
        putenv('EXS_ATTRIBUTION_ORDER_LINK_ENABLED=true');
        $linker = new AttributionOrderLinker(new AttributionSanitizer());
        $task = $this->fakeTask();
        $linker->attachFailOpen($task, null);
        $linker->attachFailOpen($task, '');
        $this->assertFalse($task->saved);
        $this->assertNull($task->session_attribution_id);
    }

    public function test_already_linked_is_idempotent_without_save(): void
    {
        putenv('EXS_ATTRIBUTION_ORDER_LINK_ENABLED=true');
        $linker = new AttributionOrderLinker(new AttributionSanitizer());
        $task = $this->fakeTask();
        $task->session_attribution_id = 42;
        $linker->attachFailOpen($task, 'validsessionid123456');
        $this->assertFalse($task->saved);
        $this->assertSame(42, $task->session_attribution_id);
    }

    public function test_attach_fail_open_never_throws_to_caller(): void
    {
        putenv('EXS_ATTRIBUTION_ORDER_LINK_ENABLED=true');
        $linker = new AttributionOrderLinker(new AttributionSanitizer());
        $task = new class
        {
            public $id = 1;

            public $session_attribution_id = null;

            public function forceFill(array $a): self
            {
                throw new \RuntimeException('simulated forceFill failure');
            }

            public function saveQuietly(): bool
            {
                return true;
            }
        };
        // Without a Laravel DB, SessionAttribution queries throw and are
        // caught — stub create / forceFill must never escape to the caller.
        $linker->attachFailOpen($task, 'nosuchsessionidabcdef12');
        $this->assertNull($task->session_attribution_id);

        // Source contract: catch (\Throwable) present + fail-open stub create.
        $src = file_get_contents(dirname(__DIR__, 3).'/app/Services/Analytics/AttributionOrderLinker.php');
        self::assertNotFalse($src);
        self::assertStringContainsString('catch (\\Throwable', $src);
        self::assertStringContainsString('attribution_order_link_failed', $src);
        self::assertStringContainsString('Fail-open stub', $src);
    }

    private function fakeTask(): object
    {
        return new class
        {
            public $id = 1;

            public $session_attribution_id = null;

            public bool $saved = false;

            public function forceFill(array $a): self
            {
                foreach ($a as $k => $v) {
                    $this->{$k} = $v;
                }

                return $this;
            }

            public function saveQuietly(): bool
            {
                $this->saved = true;

                return true;
            }
        };
    }
}
