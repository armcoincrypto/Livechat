<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Task;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Collision-safe public_id allocator.
 *
 * ionCube generateUniqueNumericId() is millisecond-based and can collide under
 * concurrent creates. This generator retries and treats the DB unique index as
 * the final authority when present.
 */
final class TaskPublicIdGenerator
{
    public const MAX_ATTEMPTS = 32;

    public static function allocate(): int
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $candidate = self::candidate($attempt);
            if ($candidate <= 0) {
                continue;
            }
            if (self::isTaken($candidate)) {
                usleep(1000 * $attempt);

                continue;
            }

            return $candidate;
        }

        throw new RuntimeException('Unable to allocate unique tasks.public_id after '.self::MAX_ATTEMPTS.' attempts');
    }

    /**
     * Create a task row, regenerating public_id on unique-constraint races.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createTaskWithRetry(array $attributes): Task
    {
        if (!isset($attributes['public_id']) || (int) $attributes['public_id'] <= 0) {
            $attributes['public_id'] = self::allocate();
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return Task::query()->create($attributes);
            } catch (QueryException $e) {
                if (!self::isUniquePublicIdViolation($e) || $attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }
                $attributes['public_id'] = self::allocate();
                usleep(1000 * $attempt);
            }
        }

        throw new RuntimeException('Task create failed after public_id collision retries');
    }

    private static function candidate(int $attempt): int
    {
        // Do not trust ionCube generateUniqueNumericId() alone: it is ms-based,
        // can return duplicates in the same millisecond, and has been observed
        // returning 0. Append entropy so concurrent allocates diverge.
        $ms = (int) round(microtime(true) * 1000);
        if ($ms <= 0) {
            $ms = time() * 1000;
        }

        return (int) sprintf('%d%03d', $ms, random_int(0, 999));
    }

    private static function isTaken(int $publicId): bool
    {
        return DB::table('tasks')->where('public_id', $publicId)->exists();
    }

    private static function isUniquePublicIdViolation(QueryException $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'tasks_public_id_unique')
            || (str_contains($msg, 'public_id') && (str_contains($msg, '1062') || str_contains($msg, 'Duplicate')));
    }
}
