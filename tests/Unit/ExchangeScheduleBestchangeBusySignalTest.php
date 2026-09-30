<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Schedule\BestchangeCompilerBusySignal;
use PHPUnit\Framework\TestCase;

/**
 * H1 busy-signal narrowing: BESTCHANGE_BUSY only when compiler:bestchange is live.
 */
final class ExchangeScheduleBestchangeBusySignalTest extends TestCase
{
    private string $ownersDir;

    /** @var list<resource|\ProcOpenResource> */
    private array $childProcs = [];

    /** @var list<int> */
    private array $childPids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ownersDir = sys_get_temp_dir().'/exs-h1-busy-'.bin2hex(random_bytes(4));
        mkdir($this->ownersDir, 0777, true);
        putenv('EXS_LOCK_BESTCHANGE_OWNERS='.$this->ownersDir);
        putenv('EXS_H1_BUSY_PROC_ROOT='.$this->ownersDir.'/empty-proc');
        mkdir($this->ownersDir.'/empty-proc', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->childPids as $pid) {
            if (@file_exists('/proc/'.$pid)) {
                @posix_kill($pid, 9);
            }
        }
        foreach ($this->childProcs as $proc) {
            if (is_resource($proc)) {
                @proc_terminate($proc, 9);
                @proc_close($proc);
            }
        }
        $this->childPids = [];
        $this->childProcs = [];
        putenv('EXS_LOCK_BESTCHANGE_OWNERS');
        putenv('EXS_H1_BUSY_PROC_ROOT');
        foreach (glob($this->ownersDir.'/*.owner') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->ownersDir.'/empty-proc');
        @rmdir($this->ownersDir);
        parent::tearDown();
    }

    public function test_cmdline_identity_matrix(): void
    {
        $this->assertTrue(BestchangeCompilerBusySignal::cmdlineIdentifiesBestchangeCompiler(
            'bash /opt/exswaping-owned-ops/current/scripts/ops/run_backend_compiler.sh compiler:bestchange '
        ));
        $this->assertTrue(BestchangeCompilerBusySignal::cmdlineIdentifiesBestchangeCompiler(
            '/usr/bin/php8.4 artisan compiler:bestchange'
        ));
        $this->assertFalse(BestchangeCompilerBusySignal::cmdlineIdentifiesBestchangeCompiler(
            'bash /opt/exswaping-owned-ops/current/scripts/ops/run_backend_compiler.sh compiler:courses '
        ));
        $this->assertFalse(BestchangeCompilerBusySignal::cmdlineIdentifiesBestchangeCompiler(
            '/usr/bin/php8.4 artisan merchant:webhook'
        ));
        $this->assertFalse(BestchangeCompilerBusySignal::cmdlineIdentifiesBestchangeCompiler(
            'php artisan not-compiler:bestchange-extra'
        ));
    }

    public function test_nothing_running_is_idle(): void
    {
        $this->assertFalse(BestchangeCompilerBusySignal::isBusy());
    }

    public function test_bestchange_owner_alive_is_busy(): void
    {
        $pid = $this->spawnSleepWithArg('compiler:bestchange');
        $this->writeOwner($pid);
        $this->assertTrue(BestchangeCompilerBusySignal::isBusy());
    }

    public function test_courses_owner_alive_is_not_busy(): void
    {
        $pid = $this->spawnSleepWithArg('compiler:courses');
        $this->writeOwner($pid);
        $this->assertFalse(
            BestchangeCompilerBusySignal::isBusy(),
            'courses-only shared flock owner must not report BESTCHANGE_BUSY'
        );
    }

    public function test_stale_dead_pid_owner_is_idle(): void
    {
        $path = $this->ownersDir.'/999999999.stale.owner';
        file_put_contents($path, "pid=999999999\nstarttime_ticks=1\ncommand=run_backend_compiler.sh\n");
        $this->assertFalse(BestchangeCompilerBusySignal::ownerFileIsLiveBestchangeCompiler($path));
        $this->assertFalse(BestchangeCompilerBusySignal::isBusy());
    }

    public function test_pid_reuse_starttime_mismatch_is_idle(): void
    {
        $pid = $this->spawnSleepWithArg('compiler:bestchange');
        $path = $this->ownersDir.'/'.$pid.'.reuse.owner';
        file_put_contents($path, "pid={$pid}\nstarttime_ticks=1\ncommand=run_backend_compiler.sh\n");
        $this->assertFalse(BestchangeCompilerBusySignal::ownerFileIsLiveBestchangeCompiler($path));
        $this->assertFalse(BestchangeCompilerBusySignal::isBusy());
    }

    public function test_unrelated_artisan_owner_is_idle(): void
    {
        $pid = $this->spawnSleepWithArg('merchant:webhook');
        $this->writeOwner($pid);
        $this->assertFalse(BestchangeCompilerBusySignal::isBusy());
    }

    public function test_bestchange_crash_clears_busy_after_exit(): void
    {
        $pid = $this->spawnSleepWithArg('compiler:bestchange');
        $this->writeOwner($pid);
        $this->assertTrue(BestchangeCompilerBusySignal::isBusy());

        $proc = array_pop($this->childProcs);
        $this->assertNotNull($proc);
        proc_terminate($proc, 9);
        proc_close($proc);

        // Wrapper trap removes owner on exit; simulate that recovery path.
        foreach (glob($this->ownersDir.'/*.owner') ?: [] as $f) {
            @unlink($f);
        }
        $this->assertFalse(BestchangeCompilerBusySignal::isBusy());
    }

    public function test_process_table_fallback_detects_bestchange_without_owner(): void
    {
        $fake = $this->ownersDir.'/fake-proc';
        @mkdir($fake.'/424242', 0777, true);
        file_put_contents($fake.'/424242/cmdline', "php\0artisan\0compiler:bestchange\0");
        putenv('EXS_H1_BUSY_PROC_ROOT='.$fake);
        $this->assertTrue(
            BestchangeCompilerBusySignal::isBusy(),
            'process-table fallback must see compiler:bestchange without owner file'
        );

        file_put_contents($fake.'/424242/cmdline', "php\0artisan\0compiler:courses\0");
        $this->assertFalse(BestchangeCompilerBusySignal::isBusy());
        @unlink($fake.'/424242/cmdline');
        @rmdir($fake.'/424242');
        @rmdir($fake);
    }

    public function test_h1_tier3_gates_still_bound_to_when_bc_idle(): void
    {
        $path = dirname(__DIR__, 2).'/app/Console/Schedule/ExchangeSchedule.php';
        $src = (string) file_get_contents($path);
        $this->assertStringContainsString('BestchangeCompilerBusySignal::isBusy()', $src);

        foreach ([
            "command('rates:zelleusd-usdt-preview",
            "command('directions:derived-baseline-refresh",
            'scheme:files")',
            "command('rates:health",
            "command('rates:ownership-drift-health",
            "command('rates:authority-drift-monitor",
        ] as $needle) {
            $pos = strpos($src, $needle);
            $this->assertNotFalse($pos, "missing {$needle}");
            $window = substr($src, $pos, 700);
            $this->assertStringContainsString(
                'when($whenBcIdle)',
                $window,
                "{$needle} must remain H1-gated via whenBcIdle"
            );
        }

        foreach ([
            "command('merchant:webhook",
            "command('pay:pending-withdrawal",
        ] as $crit) {
            $pos = strpos($src, $crit);
            $this->assertNotFalse($pos, "missing critical {$crit}");
            $window = substr($src, $pos, 500);
            $this->assertStringNotContainsString('when($whenBcIdle)', $window);
        }
    }

    public function test_monitor_cadence_simulation_courses_only_allows_idle_gate(): void
    {
        $pid = $this->spawnSleepWithArg('compiler:courses');
        $this->writeOwner($pid);
        $expected = 3;
        $actual = 0;
        for ($tick = 0; $tick < $expected; $tick++) {
            if (! BestchangeCompilerBusySignal::isBusy()) {
                $actual++;
            }
        }
        $this->assertSame($expected, $actual, 'courses-only must not suppress monitor ticks');
    }

    private function spawnSleepWithArg(string $token): int
    {
        $cmd = [PHP_BINARY, '-r', 'usleep(8000000);', 'x', $token];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes);
        $this->assertNotFalse($proc);
        $status = proc_get_status($proc);
        $pid = (int) ($status['pid'] ?? 0);
        $this->assertGreaterThan(1, $pid);
        $this->childPids[] = $pid;
        $this->childProcs[] = $proc;
        foreach ($pipes as $p) {
            if (is_resource($p)) {
                fclose($p);
            }
        }
        usleep(150000);

        return $pid;
    }

    private function writeOwner(int $pid): void
    {
        $ticks = $this->readStarttimeTicks($pid);
        $this->assertNotNull($ticks);
        $path = $this->ownersDir.'/'.$pid.'.'.$pid.'-test.owner';
        file_put_contents($path, implode("\n", [
            'token='.$pid.'-test',
            'pid='.$pid,
            'starttime_ticks='.$ticks,
            'namespace=bestchange',
            'class=backend-compiler',
            'command=run_backend_compiler.sh',
            '',
        ]));
    }

    private function readStarttimeTicks(int $pid): ?int
    {
        $stat = @file_get_contents('/proc/'.$pid.'/stat');
        if (! is_string($stat)) {
            return null;
        }
        $close = strrpos($stat, ')');
        if ($close === false) {
            return null;
        }
        $parts = explode(' ', substr($stat, $close + 2));

        return isset($parts[19]) && ctype_digit($parts[19]) ? (int) $parts[19] : null;
    }
}
