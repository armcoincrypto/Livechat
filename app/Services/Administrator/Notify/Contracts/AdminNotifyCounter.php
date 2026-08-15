<?php

namespace App\Services\Administrator\Notify\Contracts;

use App\Models\User;

interface AdminNotifyCounter
{
    public function key(): string;

    public function allowed(User $user): bool;

    /**
     * @return int|array<string,mixed>
     */
    public function count(User $user): int|array;
}
