<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Console;

use App\Settings\BestChangeConfig;
use iEXPackages\BestChange\Services\BestChangeCatalogRepository;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;

/**
 * BestchangeCacheCommand
 *
 * Прогревает кэш справочников BestChange (автокэш репозитория).
 *
 * Что кэшируется:
 * - currencies
 * - cities
 * - countries
 * - exchangers
 * - groups
 *
 * Важно:
 * - Команда не пишет никаких JSON-файлов (это делает bestchange:files)
 * - Команда использует единый слой BestChangeCatalogRepository, чтобы логика кэша была централизована
 */
final class BestchangeCacheCommand extends Command
{
    protected $signature = 'bestchange:cache-update {--force : Принудительно обновить кэш справочников}';
    protected $description = 'Прогревает кэш справочников BestChange (валюты/города/страны/обменники/группы).';

    public function handle(): int
    {
        /** @var BestChangeConfig $config */
        $config = app(BestChangeConfig::class);

        if (!$config->isEnabled()) {
            $this->info('BestChange выключен — пропуск.');
            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');

        try {
            app(BestChangeCatalogRepository::class)->warmupAll($force);

            $this->info($force
                ? 'BestChange: справочники принудительно обновлены и закэшированы.'
                : 'BestChange: справочники закэшированы (если кэш отсутствовал).'
            );

            return self::SUCCESS;

        } catch (ConnectionException $e) {
            $this->error('BestChange: ошибка сети: ' . $e->getMessage());
            return self::FAILURE;

        } catch (\Throwable $e) {
            $this->error('BestChange: ошибка: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
