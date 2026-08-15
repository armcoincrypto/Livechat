<?php

declare(strict_types=1);

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;
use Illuminate\Database\Eloquent\Builder;

final class PaymentGatewayLogFilter extends ModelFilter
{
    /**
     * Разрешённые поля сортировки.
     *
     * @var array<int, string>
     */
    private array $allowedSort = ['id', 'created_at', 'duration_ms', 'response_status', 'attempt'];

    public function taskId(int|string $value): self
    {
        $id = (int) $value;
        if ($id > 0) {
            $this->where('task_id', $id);
        }

        return $this;
    }

    public function merchantId(int|string $value): self
    {
        $id = (int) $value;
        if ($id > 0) {
            $this->where('merchant_id', $id);
        }

        return $this;
    }

    public function paymentId(int|string $value): self
    {
        $id = (int) $value;
        if ($id > 0) {
            $this->where('payment_id', $id);
        }

        return $this;
    }

    public function gatewayAlias(string|array $value): self
    {
        if (is_array($value)) {
            $items = array_values(array_filter(array_map('strval', $value), static fn ($v) => trim($v) !== ''));
            if ($items !== []) {
                $this->whereIn('gateway_alias', $items);
            }
            return $this;
        }

        $value = trim((string) $value);
        if ($value !== '') {
            $this->where('gateway_alias', $value);
        }

        return $this;
    }

    public function direction(string|array $value): self
    {
        if (is_array($value)) {
            $items = array_values(array_filter(array_map('strval', $value), static fn ($v) => trim($v) !== ''));
            if ($items !== []) {
                $this->whereIn('direction', $items);
            }
            return $this;
        }

        $value = trim((string) $value);
        if ($value !== '') {
            $this->where('direction', $value);
        }

        return $this;
    }

    public function operation(string|array $value): self
    {
        if (is_array($value)) {
            $items = array_values(array_filter(array_map('strval', $value), static fn ($v) => trim($v) !== ''));
            if ($items !== []) {
                $this->whereIn('operation', $items);
            }
            return $this;
        }

        $value = trim((string) $value);
        if ($value !== '') {
            $this->where('operation', $value);
        }

        return $this;
    }

    public function status(string|array $value): self
    {
        if (is_array($value)) {
            $items = array_values(array_filter(array_map('strval', $value), static fn ($v) => trim($v) !== ''));
            if ($items !== []) {
                $this->whereIn('status', $items);
            }
            return $this;
        }

        $value = trim((string) $value);
        if ($value !== '') {
            $this->where('status', $value);
        }

        return $this;
    }

    public function transactionId(string $value): self
    {
        $value = trim($value);
        if ($value !== '') {
            $this->where('transaction_id', $value);
        }

        return $this;
    }

    public function externalId(string $value): self
    {
        $value = trim($value);
        if ($value !== '') {
            $this->where('external_id', $value);
        }

        return $this;
    }

    public function idempotencyKey(string $value): self
    {
        $value = trim($value);
        if ($value !== '') {
            $this->where('idempotency_key', $value);
        }

        return $this;
    }

    public function replayKey(string $value): self
    {
        $value = trim($value);
        if ($value !== '') {
            $this->where('replay_key', $value);
        }

        return $this;
    }

    public function responseStatus(int|string|array $value): self
    {
        if (is_array($value)) {
            $codes = [];
            foreach ($value as $v) {
                if ($v === '' || $v === null) continue;
                $n = (int) $v;
                if ($n > 0) $codes[] = $n;
            }
            $codes = array_values(array_unique($codes));
            if ($codes !== []) {
                $this->whereIn('response_status', $codes);
            }
            return $this;
        }

        if ($value === '' || $value === null) {
            return $this;
        }

        $code = (int) $value;
        if ($code > 0) {
            $this->where('response_status', $code);
        }

        return $this;
    }

    public function isSandbox(bool|int|string|array $value): self
    {
        if (is_array($value)) {
            $vals = [];
            foreach ($value as $v) {
                $bool = filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($bool === null) {
                    if ($v === '0' || $v === 0) $vals[] = 0;
                    if ($v === '1' || $v === 1) $vals[] = 1;
                    continue;
                }

                $vals[] = $bool ? 1 : 0;
            }

            $vals = array_values(array_unique($vals));
            if ($vals !== []) {
                $this->whereIn('is_sandbox', $vals);
            }

            return $this;
        }

        $bool = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($bool === null) {
            if ($value === '0' || $value === 0) $this->where('is_sandbox', 0);
            if ($value === '1' || $value === 1) $this->where('is_sandbox', 1);
            return $this;
        }

        $this->where('is_sandbox', $bool ? 1 : 0);

        return $this;
    }

    public function dateFrom(string $value): self
    {
        $value = trim($value);
        if ($value !== '') {
            $this->where('created_at', '>=', $value);
        }

        return $this;
    }

    public function dateTo(string $value): self
    {
        $value = trim($value);
        if ($value !== '') {
            $this->where('created_at', '<=', $value);
        }

        return $this;
    }

    public function search(string $value): self
    {
        $value = trim($value);
        if ($value === '') {
            return $this;
        }

        $like = '%' . $this->escapeLike($value) . '%';

        $this->where(function (Builder $q) use ($value, $like): void {
            if (ctype_digit($value)) {
                $q->orWhere('task_id', (int) $value);
            }

            $q->orWhere('gateway_alias', 'like', $like)
                ->orWhere('operation', 'like', $like)
                ->orWhere('direction', 'like', $like)
                ->orWhere('transaction_id', 'like', $like)
                ->orWhere('external_id', 'like', $like)
                ->orWhere('idempotency_key', 'like', $like)
                ->orWhere('replay_key', 'like', $like);

            $q->orWhereHas('merchant', function (Builder $m) use ($like): void {
                $m->where('name', 'like', $like)->orWhere('alias', 'like', $like);
            });

            $q->orWhereHas('payment', function (Builder $p) use ($like): void {
                $p->where('name', 'like', $like)->orWhere('alias', 'like', $like);
            });
        });

        return $this;
    }

    public function sort(string $value): self
    {
        $sort = trim($value);
        if ($sort === '' || !in_array($sort, $this->allowedSort, true)) {
            $sort = 'id';
        }

        $order = strtolower((string) ($this->input('order') ?? 'desc'));
        $order = $order === 'asc' ? 'asc' : 'desc';

        $this->orderBy($sort, $order);

        return $this;
    }

    public function setup(): void
    {
        if (!is_string($this->input('sort')) || trim((string) $this->input('sort')) === '') {
            $this->orderBy('id', 'desc');
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
