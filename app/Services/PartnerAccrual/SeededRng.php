<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

/**
 * Deterministic PRNG for schedule variation. Seed persisted on plan.
 * Not for cryptography — only for human-looking, replay-stable amounts.
 */
final class SeededRng
{
    private int $state;

    public function __construct(string $seed)
    {
        // Stable 32-bit state from arbitrary seed string.
        $hash = hash('sha256', $seed, true);
        $this->state = unpack('N', substr($hash, 0, 4))[1] & 0x7FFFFFFF;
        if ($this->state === 0) {
            $this->state = 1;
        }
    }

    /** Uniform float in [0, 1). */
    public function nextFloat(): float
    {
        // LCG (Numerical Recipes)
        $this->state = (int) ((1103515245 * $this->state + 12345) & 0x7FFFFFFF);

        return $this->state / 2147483648.0;
    }

    public function nextInt(int $minInclusive, int $maxInclusive): int
    {
        if ($maxInclusive <= $minInclusive) {
            return $minInclusive;
        }
        $span = $maxInclusive - $minInclusive + 1;

        return $minInclusive + (int) floor($this->nextFloat() * $span);
    }
}
