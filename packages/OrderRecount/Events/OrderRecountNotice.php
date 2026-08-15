<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount\Events;

use App\Models\Task;

final class OrderRecountNotice
{
    public function __construct(
        public readonly string $type,      // 'rate_changed' | 'failed'
        public readonly Task $task,
        public readonly array $payload = [],
    ) {}
}
