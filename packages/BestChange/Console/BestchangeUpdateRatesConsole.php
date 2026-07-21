<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Console;

use App\Models\HistoryUpdatedData;
use App\Services\Rates\IndependentMarketBaseline;
use App\Settings\BestChangeConfig;
use iEXPackages\BestChange\Facades\BestChangeFacade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * BestchangeUpdateRatesConsole
 *
 * Полное обновление курсов BestChange:
 * 1) BestChangeFacade::rates()->updateRates() — обновляет bestchange_directions
 * 2) ->withUpdateDirections() — пересчитывает direction_exchange через калькулятор
 * 3) пишет статистику выполнения в HistoryUpdatedData
 */
final class BestchangeUpdateRatesConsole extends Command
{
    /**
     * @var string
     */
    protected $signature = 'compiler:bestchange';

    /**
     * @var string
     */
    protected $description = 'Полное обновление курсов из BestChange';

    public function handle(): int
    {
        /** @var BestChangeConfig $config */
        $config = app(BestChangeConfig::class);

        if (! $config->isEnabled()) {
            $this->info('BestChange выключен — пропуск.');
            return self::SUCCESS;
        }

        $this->line('Обновление курсов из источника: BestChange');

        $start = microtime(true);
        $ownsSnapshot = IndependentMarketBaseline::currentSnapshot() === null;
        if ($ownsSnapshot) {
            IndependentMarketBaseline::beginSnapshot(purpose: 'rate_update');
        }

        try {
            $ratesConnection = BestChangeFacade::rates()->useCommand($this);

            // 1) обновляем курсы bestchange_directions
            $ratesConnection->updateRates();

            // 2) пересчитываем direction_exchange
            $ratesConnection->withUpdateDirections();

            $elapsed = round(microtime(true) - $start, 4);

            $prev = (float) (HistoryUpdatedData::where('type_update', 'bestchange')->latest()->value('time') ?? 0);

            HistoryUpdatedData::create([
                'time' => $elapsed,
                'type_update' => 'bestchange',
                'count_num' => $ratesConnection->getCountUpdateData(),
                'total_num' => $ratesConnection->getCountTotalUpdate(),
                'old_time' => $prev,
            ]);

            $this->info("Обновление завершено за {$elapsed} сек.");
            return self::SUCCESS;

        } catch (\Throwable $e) {
            $elapsed = round(microtime(true) - $start, 4);

            $this->error("Ошибка при обновлении курсов: {$e->getMessage()}");

            Log::error('BestchangeUpdateRatesConsole: ошибка при обновлении курсов', [
                'error' => $e->getMessage(),
                'elapsed' => $elapsed,
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        } finally {
            if ($ownsSnapshot) {
                IndependentMarketBaseline::endSnapshot();
            }
        }
    }
}
