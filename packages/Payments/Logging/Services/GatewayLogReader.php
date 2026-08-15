<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Logging\Services;

use App\Models\PaymentGatewayLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GatewayLogReader
{
    public function forTask(int $taskId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->search(['taskId' => $taskId], $perPage);
    }

    public function forMerchant(int $merchantId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->search(['merchantId' => $merchantId], $perPage);
    }

    public function forPayment(int $paymentId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->search(['paymentId' => $paymentId], $perPage);
    }

    public function merchantAll(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        return $this->search([
            ...$filters,
            'direction' => 'incoming',
        ], $perPage);
    }

    public function payoutAll(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        return $this->search([
            ...$filters,
            'direction' => 'outgoing',
        ], $perPage);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function search(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $perPage = $this->normalizePerPage($perPage);

        return PaymentGatewayLog::query()
            ->with([
                'merchant:id,alias,name',
                'payment:id,alias,name',
                'task:id,status',
            ])
            ->filter($filters)
            ->paginate($perPage);
    }

    private function normalizePerPage(int $perPage): int
    {
        $perPage = max(5, $perPage);
        return min(100, $perPage);
    }
}
