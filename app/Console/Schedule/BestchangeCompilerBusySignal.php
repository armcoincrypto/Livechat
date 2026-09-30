<?php

declare(strict_types=1);

namespace App\Console\Schedule;

/**
 * H1 observation signal: true only while an actual compiler:bestchange run is live.
 *
 * Does NOT flock bestchange-compiler.lock. compiler:courses may share that flock and
 * the owners/bestchange namespace; courses-only must return false.
 */
final class BestchangeCompilerBusySignal
{
    public static function isBusy(): bool
    {
        $dir = (string) (getenv('EXS_LOCK_BESTCHANGE_OWNERS') ?: '/var/lock/build/owners/bestchange');
        if (is_dir($dir)) {
            foreach (glob(rtrim($dir, '/').'/*.owner') ?: [] as $ownerFile) {
                if (self::ownerFileIsLiveBestchangeCompiler((string) $ownerFile)) {
                    return true;
                }
            }
        }

        return self::processTableHasBestchangeCompiler();
    }

    public static function cmdlineIdentifiesBestchangeCompiler(string $cmdline): bool
    {
        return (bool) preg_match('/(?:^|[\s\/])compiler:bestchange(?:\s|$)/', $cmdline);
    }

    public static function ownerFileIsLiveBestchangeCompiler(string $ownerFile): bool
    {
        $body = @file_get_contents($ownerFile);
        if (! is_string($body) || $body === '') {
            return false;
        }

        $pid = 0;
        if (preg_match('/^pid=(\d+)/m', $body, $m)) {
            $pid = (int) $m[1];
        }
        if ($pid <= 1) {
            $base = basename($ownerFile);
            $pid = (int) strtok($base, '.');
        }
        if ($pid <= 1 || ! @file_exists('/proc/'.$pid)) {
            return false;
        }

        if (preg_match('/^starttime_ticks=(\d+)/m', $body, $tm)) {
            $liveTicks = self::procStarttimeTicks($pid);
            if ($liveTicks === null || $liveTicks !== (int) $tm[1]) {
                return false;
            }
        }

        $cmdline = self::procCmdline($pid);
        if ($cmdline === null) {
            return false;
        }

        return self::cmdlineIdentifiesBestchangeCompiler($cmdline);
    }

    private static function processTableHasBestchangeCompiler(): bool
    {
        $root = (string) (getenv('EXS_H1_BUSY_PROC_ROOT') ?: '/proc');
        if (! is_dir($root)) {
            return false;
        }
        foreach (scandir($root) ?: [] as $entry) {
            if (! ctype_digit($entry)) {
                continue;
            }
            $pid = (int) $entry;
            if ($pid <= 1) {
                continue;
            }
            $cmdline = self::procCmdline($pid, $root);
            if ($cmdline !== null && self::cmdlineIdentifiesBestchangeCompiler($cmdline)) {
                return true;
            }
        }

        return false;
    }

    private static function procCmdline(int $pid, string $procRoot = '/proc'): ?string
    {
        $raw = @file_get_contents(rtrim($procRoot, '/').'/'.$pid.'/cmdline');
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return str_replace("\0", ' ', $raw);
    }

    private static function procStarttimeTicks(int $pid): ?int
    {
        $stat = @file_get_contents('/proc/'.$pid.'/stat');
        if (! is_string($stat) || $stat === '') {
            return null;
        }
        $close = strrpos($stat, ')');
        if ($close === false) {
            return null;
        }
        $rest = substr($stat, $close + 2);
        $parts = explode(' ', $rest);
        if (! isset($parts[19]) || ! ctype_digit($parts[19])) {
            return null;
        }

        return (int) $parts[19];
    }
}
