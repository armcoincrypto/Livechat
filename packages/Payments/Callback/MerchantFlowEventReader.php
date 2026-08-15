<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Callback;

use App\Models\MerchantFlowEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class MerchantFlowEventReader
{
    public function paginate(Request $request, int $perPage = 50): LengthAwarePaginator
    {
        $query = MerchantFlowEvent::query()->orderByDesc('id');
        $this->applyFilters($query, $request);

        return $query->paginate($perPage);
    }

    public function paginateForTask(int $taskId, Request $request, int $perPage = 50): LengthAwarePaginator
    {
        $query = MerchantFlowEvent::query()
            ->where('task_id', $taskId)
            ->orderByDesc('id');

        $this->applyFilters($query, $request);

        return $query->paginate($perPage);
    }

    public function paginateForMerchant(int $merchantId, Request $request, int $perPage = 50): LengthAwarePaginator
    {
        $query = MerchantFlowEvent::query()
            ->where('merchant_id', $merchantId)
            ->orderByDesc('id');

        $this->applyFilters($query, $request);

        return $query->paginate($perPage);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        foreach (['flow', 'level', 'gateway_alias', 'stage', 'event'] as $key) {
            $val = trim((string) $request->query($key, ''));
            if ($val !== '') {
                $query->where($key, $val);
            }
        }

        foreach (['checkout_id', 'external_id'] as $key) {
            $val = trim((string) $request->query($key, ''));
            if ($val !== '') {
                $query->where($key, $val);
            }
        }

        $dateFrom = trim((string) $request->query('date_from', '')); // YYYY-MM-DD
        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        $dateTo = trim((string) $request->query('date_to', ''));
        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }
    }
}
