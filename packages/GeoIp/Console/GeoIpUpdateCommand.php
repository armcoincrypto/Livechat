<?php

declare(strict_types=1);

namespace iEXPackages\GeoIp\Console;

use iEXPackages\GeoIp\Support\DatabaseValidator;
use Illuminate\Console\Command;
use Symfony\Component\HttpClient\HttpClient;

final class GeoIpUpdateCommand extends Command
{
    protected $signature = 'geoip:update {type=city : city|country|asn}';
    protected $description = 'Обновление GeoLite2 базы MaxMind (download->extract->validate->atomic rename)';

    public function handle(DatabaseValidator $validator): int
    {
        $cfg = (array) config('geoip', []);
        $update = (array)($cfg['update'] ?? []);
        $db = (array)($cfg['databases'] ?? []);
        $editions = (array)($update['editions'] ?? []);

        if (!(bool)($update['enabled'] ?? false)) {
            $this->error('Обновление выключено (geoip.update.enabled=false).');
            return self::FAILURE;
        }

        $type = (string)$this->argument('type');
        $editionId = (string)($editions[$type] ?? '');
        $targetPath = (string)($db[$type] ?? '');

        if ($editionId === '' || $targetPath === '') {
            $this->error("Не настроены edition/путь для типа: {$type}");
            return self::FAILURE;
        }

        $account = (string)($update['account_id'] ?? '');
        $license = (string)($update['license_key'] ?? '');
        $workDir = (string)($update['work_dir'] ?? sys_get_temp_dir().'/geoip_work');

        if ($account === '' || $license === '') {
            $this->error('Не заданы MAXMIND_ACCOUNT_ID / MAXMIND_LICENSE_KEY.');
            return self::FAILURE;
        }

        @mkdir($workDir, 0775, true);

        $tmpTarGz = $workDir.'/'.$editionId.'.tar.gz';
        $tmpExtract = $workDir.'/'.$editionId.'_extract_'.bin2hex(random_bytes(6));
        @mkdir($tmpExtract, 0775, true);

        try {
            $url = "https://download.maxmind.com/app/geoip_download"
                ."?edition_id=".rawurlencode($editionId)
                ."&suffix=tar.gz";

            $this->info("Скачивание: {$editionId}");
            $client = HttpClient::create();
            $resp = $client->request('GET', $url, [
                'auth_basic' => [$account, $license],
                'timeout' => 120,
            ]);

            if ($resp->getStatusCode() !== 200) {
                $this->error("HTTP ".$resp->getStatusCode());
                return self::FAILURE;
            }

            file_put_contents($tmpTarGz, $resp->getContent());

            $this->info("Распаковка…");
            $tarPath = str_replace('.tar.gz', '.tar', $tmpTarGz);

            $pharGz = new \PharData($tmpTarGz);
            if (!is_file($tarPath)) {
                $pharGz->decompress();
            }

            $pharTar = new \PharData($tarPath);
            $pharTar->extractTo($tmpExtract, null, true);

            $mmdb = $this->findMmdb($tmpExtract);
            if ($mmdb === null) {
                $this->error('В архиве не найден .mmdb');
                return self::FAILURE;
            }

            $this->info("Валидация mmdb…");
            $validator->validate($mmdb);

            $tmpFinal = $targetPath.'.tmp';
            @mkdir(dirname($targetPath), 0775, true);

            if (!copy($mmdb, $tmpFinal)) {
                $this->error("Не удалось скопировать mmdb в {$tmpFinal}");
                return self::FAILURE;
            }

            $validator->validate($tmpFinal);

            $this->info("Атомарная замена…");
            if (!@rename($tmpFinal, $targetPath)) {
                $this->error("Не удалось заменить {$targetPath}");
                return self::FAILURE;
            }

            $this->info("Готово: {$targetPath}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Ошибка: ".$e->getMessage());
            return self::FAILURE;
        } finally {
            @unlink($tmpTarGz);
            @unlink(str_replace('.tar.gz', '.tar', $tmpTarGz));
            $this->rrmdir($tmpExtract);
            @unlink(($targetPath ?? '').'.tmp');
        }
    }

    private function findMmdb(string $dir): ?string
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && str_ends_with($file->getFilename(), '.mmdb')) {
                return $file->getPathname();
            }
        }
        return null;
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}
