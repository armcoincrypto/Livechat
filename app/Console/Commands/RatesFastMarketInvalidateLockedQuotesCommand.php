<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\FastMarket\LockedQuoteStore;
use Illuminate\Console\Command;

/**
 * Emergency invalidation of all outstanding fast locked quotes.
 */
final class RatesFastMarketInvalidateLockedQuotesCommand extends Command
{
    protected $signature = 'rates:fast-market-invalidate-locked-quotes {--reason=}';

    protected $description = 'Bump locked-quote generation so outstanding quote tokens fail closed';

    public function handle(LockedQuoteStore $store): int
    {
        $gen = $store->bumpGeneration();
        $this->info('locked_quote_generation='.$gen);
        if ($this->option('reason')) {
            $this->line('reason='.(string) $this->option('reason'));
        }

        return self::SUCCESS;
    }
}
