<?php
declare(strict_types=1);

namespace iEXPackages\BestChange\Console;

use iEXPackages\BestChange\Facades\BestChangeFacade;
use iEXPackages\BestChange\Services\BestChangeCatalogRepository;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;

/**
 * BestchangeUpdateFilesConsole
 *
 * Генерирует локальные JSON-файлы со справочниками BestChange:
 * - currencies.json   (id, name)
 * - cities.json       (id, name)
 * - exchangers.json   (id => name)
 * - codes.json        (code => display name)
 *
 * Назначение:
 * - ускорение UI (селекты/поиск), когда не хочется каждый раз тянуть справочники из API
 * - стабильные локальные файлы для фронта/админки
 *
 * Источник данных:
 * - RatesConnection (BestChangeFacade::rates()), который использует автокэш справочников через BestChangeCatalogRepository
 */
final class BestchangeUpdateFilesConsole extends Command
{
    /**
     * @var string
     */
    protected $signature = 'bestchange:files {--force : Принудительно обновить справочники и пересоздать файлы}';

    /**
     * @var string
     */
    protected $description = 'Генерирует JSON-файлы справочников BestChange (валюты/города/обменники/коды).';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        try {
            // Прогрев справочников (автокэш) — один раз
            /** @var BestChangeCatalogRepository $repo */
            $repo = app(BestChangeCatalogRepository::class);
            $repo->warmupAll($force);

            // Генерируем файлы
            $this->writeJsonFiles();

            $this->info('BestChange: JSON-файлы успешно обновлены.');
            return self::SUCCESS;

        } catch (ConnectionException $e) {
            $this->error('BestChange: ошибка сети при получении справочников: ' . $e->getMessage());
            return self::FAILURE;

        } catch (\Throwable $e) {
            $this->error('BestChange: ошибка генерации файлов: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Собирает данные из RatesConnection и сохраняет JSON-файлы в storage/app/bestchange/.
     *
     * @return void
     */
    private function writeJsonFiles(): void
    {
        $baseDir = storage_path('app/bestchange');
        $this->ensureDirectory($baseDir);

        $rates = BestChangeFacade::rates();

        // Берём справочники (они уже из кэша или обновлены)
        $currencies = $rates->currencies();
        $citiesWithCountries = $rates->citiesWithCountries();
        $exchangers = $rates->exchangers();

        // 1) currencies.json
        $currenciesJson = [];
        foreach ($currencies as $item) {
            $id = (int)($item['id'] ?? 0);
            if ($id <= 0) continue;

            $code = (string)($item['code'] ?? '');
            $name = (string)($item['name'] ?? '');

            $currenciesJson[] = [
                'id' => $id,
                'name' => $code !== '' ? "[{$code}] - {$name}" : $name,
            ];
        }

        // 2) cities.json
        $citiesJson = [];
        foreach ($citiesWithCountries as $item) {
            $id = (int)($item['id'] ?? 0);
            if ($id <= 0) continue;

            $citiesJson[] = [
                'id' => $id,
                'name' => (string)($item['full_name'] ?? ''),
                'code' => (string)($item['code'] ?? ''),
            ];
        }

        // 3) exchangers.json (id => name)
        $exchangersJson = [];
        foreach ($exchangers as $id => $item) {
            $id = (int)$id;
            if ($id <= 0) continue;
            $exchangersJson[(string)$id] = (string)($item['name'] ?? '');
        }

        // 4) codes.json (code => "[CODE] - Name")
        $codesJson = [];
        foreach ($currencies as $item) {
            $code = (string)($item['code'] ?? '');
            $name = (string)($item['name'] ?? '');
            if ($code === '') continue;

            $codesJson[$code] = "[{$code}] - {$name}";
        }

        // Атомарная запись файлов
        $this->atomicJsonWrite($baseDir . '/currencies.json', $currenciesJson);
        $this->atomicJsonWrite($baseDir . '/cities.json', $citiesJson);
        $this->atomicJsonWrite($baseDir . '/exchangers.json', $exchangersJson);
        $this->atomicJsonWrite($baseDir . '/codes.json', $codesJson);
    }

    /**
     * Создаёт директорию, если её нет.
     */
    private function ensureDirectory(string $path): void
    {
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }

    /**
     * Атомарно записывает JSON (через временный файл + rename).
     *
     * @param string $fullPath
     * @param mixed $data
     */
    private function atomicJsonWrite(string $fullPath, mixed $data): void
    {
        $tmp = $fullPath . '.tmp';

        File::put(
            $tmp,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        File::move($tmp, $fullPath);
    }
}
