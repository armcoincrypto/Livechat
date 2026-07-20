<?php

declare(strict_types=1);

namespace iEXPackages\Courses\Export;

use App\Services\Rates\AtomicPublicXmlPublisher;
use iEXPackages\Courses\Export\Concerns\DefaultExport;
use iEXPackages\Courses\Export\Concerns\ManagesAttributes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * ExportCourses — generates public rate XML files.
 *
 * Publication is atomic (temp + rename) so nginx cannot serve a truncated file
 * while scheme:files is rewriting currencies.xml.
 */
final class ExportCourses
{
    use DefaultExport;
    use ManagesAttributes;

    private int $countUpdateData = 0;

    private int $countTotalUpdate = 0;

    protected bool $isRead = false;

    protected bool $isClear = false;

    protected Command $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    public function store(bool $isRead = false, bool $isClear = false): void
    {
        $this->setIsClear($isClear)
            ->setIsRead($isRead)
            ->loadingDefault();
    }

    protected function put(string $filename, mixed $contents): void
    {
        $publisher = new AtomicPublicXmlPublisher();
        $xml = (string) $contents;
        $items = substr_count($xml, '<item>');

        if ($publisher->collapsesAgainstLastGood($filename, $items)) {
            Log::error('rates_xml_publish_refused_item_collapse', [
                'path' => $filename,
                'new_items' => $items,
            ]);
            @file_put_contents($filename . '.failed.' . gmdate('Ymd\THis\Z'), $xml);

            return;
        }

        $isCanonicalCurrencies = str_contains($filename, '/static/exports/')
            && str_ends_with($filename, 'currencies.xml');

        $result = $publisher->publish($filename, $xml, [
            'min_items' => 1,
            'backup' => true,
            'sync_legacy' => $isCanonicalCurrencies,
        ]);

        if (!$result['published']) {
            Log::error('rates_xml_publish_refused', $result);
            @file_put_contents($filename . '.failed.' . gmdate('Ymd\THis\Z'), $xml);
        }
    }

    protected function exists(string $filename): bool
    {
        return File::exists($filename);
    }

    public function clear(string $filename): void
    {
        // Never truncate the live public export (causes 0-byte client timeouts).
        Log::warning('rates_xml_clear_skipped_live_path', ['path' => $filename]);
    }

    public function setIsRead(bool $read): self
    {
        $this->isRead = $read;

        return $this;
    }

    public function setIsClear(bool $clear): self
    {
        $this->isClear = $clear;

        return $this;
    }

    public function getCountUpdateData(): int
    {
        return $this->countUpdateData;
    }

    public function getCountTotalUpdate(): int
    {
        return $this->countTotalUpdate;
    }
}
