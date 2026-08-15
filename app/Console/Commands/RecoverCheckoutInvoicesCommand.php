<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payments\CheckoutInvoiceRecoveryService;
use Illuminate\Console\Command;

final class RecoverCheckoutInvoicesCommand extends Command
{
    protected $signature = 'merchant:recover-checkout-invoices
        {--limit=50 : Максимум записей merchants_transaction_data за запуск}';

    protected $description = 'Фоновое восстановление invoice/create для checkout после сбоя (до 5 попыток, тот же transactionId).';

    public function handle(CheckoutInvoiceRecoveryService $recovery): int
    {
        $limit = max(1, (int) $this->option('limit'));

        [$ok, $fail] = $recovery->recoverBatchForCron($limit);

        $this->info("recover-checkout-invoices: ok={$ok}, no_change_or_fail={$fail}");

        return self::SUCCESS;
    }
}
