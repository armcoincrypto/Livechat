<?php

namespace App\Jobs;

use App\Exports\OrdersExport;
use App\Models\OrderExport;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class OrdersExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $exportId;

    public function __construct(int $exportId)
    {
        $this->exportId = $exportId;
    }

    public function handle(): void
    {
        /** @var OrderExport|null $exportModel */
        $exportModel = OrderExport::find($this->exportId);

        if (!$exportModel) {
            return;
        }

        $exportModel->update([
            'status'     => 'processing',
            'started_at' => now(),
        ]);

        $filters = $exportModel->filters ?? [];

        $createdFrom = $filters['created_from'] ?? null;
        $createdTo   = $filters['created_to'] ?? null;
        $updatedFrom = $filters['updated_from'] ?? null;
        $updatedTo   = $filters['updated_to'] ?? null;
        $statuses    = $filters['statuses'] ?? [];
        $fields      = $filters['fields'] ?? [];

        $timestamp = Carbon::now()->format('Y_m_d_H_i_s');
        $fileName  = sprintf(
            'orders_export_%s_%s.%s',
            $timestamp,
            uniqid(),
            $exportModel->format
        );

        try {
            $export = new OrdersExport(
                $createdFrom,
                $createdTo,
                $updatedFrom,
                $updatedTo,
                $statuses,
                $fields
            );

            // Формируем файл в фоне
            Excel::store(
                $export,
                $fileName,
                'exports',
                ucfirst($exportModel->format)
            );

            // Можно посчитать количество строк (без выборки в память)
            $rowsCount = $export->query()->count();

            $exportModel->update([
                'status'      => 'done',
                'file_name'   => $fileName,
                'rows_count'  => $rowsCount,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            $exportModel->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at'   => now(),
            ]);
        }
    }
}
