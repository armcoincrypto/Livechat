<?php

namespace iEXPackages\Analytics\Console;

use App\Models\ReserveTotalSnapshot;
use App\Services\Reserves\TotalReserveCalculator;
use Brick\Math\BigDecimal;
use Illuminate\Console\Command;

class TakeReserveTotalSnapshot extends Command
{
    protected $signature = 'reserves:total-snapshot';
    protected $description = 'Сохраняет снапшот общего резерва в USD';

    public function __construct(
        private readonly TotalReserveCalculator $calculator
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $now = now()->seconds(0); // выровняли секунды, чтобы не плясало

            /** @var BigDecimal $total */
            $total = $this->calculator->calculateTotalUsd();

            ReserveTotalSnapshot::create([
                'snapshot_at' => $now,
                'total_usd'   => (string) $total, // сохраняем как строку в decimal
            ]);

            $this->info('Reserve total snapshot saved: ' . $total);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error while taking reserve total snapshot: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
