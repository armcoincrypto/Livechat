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
        $livePath = $filename;

        // Fail-closed start: while pause flag is set, stage to .candidate so the
        // public empty feed stays empty until operation:start promotes + goes online.
        $stageCandidate = \App\Services\Rates\RatesXmlMonitorGate::isHidden() && !$this->isClear;
        if ($stageCandidate) {
            $filename = $filename.'.candidate';
        }

        // Collapse guard always vs the live path's last-good (not the candidate).
        if ($publisher->collapsesAgainstLastGood($livePath, $items)) {
            Log::error('rates_xml_publish_refused_item_collapse', [
                'path' => $filename,
                'live' => $livePath,
                'new_items' => $items,
            ]);
            @file_put_contents($filename.'.failed.'.gmdate('Ymd\THis\Z'), $xml);

            return;
        }

        $isCanonicalCurrencies = str_contains($livePath, '/static/exports/')
            && str_ends_with($livePath, 'currencies.xml');

        $result = $publisher->publish($filename, $xml, [
            'min_items' => 1,
            'backup' => !$stageCandidate,
            'sync_legacy' => $isCanonicalCurrencies && !$stageCandidate,
        ]);

        if (!$result['published']) {
            Log::error('rates_xml_publish_refused', $result);
            @file_put_contents($filename.'.failed.'.gmdate('Ymd\THis\Z'), $xml);
        }
    }

    protected function exists(string $filename): bool
    {
        return File::exists($filename);
    }

    public function clear(string $filename): void
    {
        // Ensure nginx 404 flag is set for the whole pause window.
        \App\Services\Rates\RatesXmlMonitorGate::hide('scheme:files_clear');

        // Intentional pause (work_is_offline / operation:stop): publish a valid
        // empty <rates/> document so BestChange/monitors see 0 pairs — not a
        // truncated 0-byte file (which caused client timeouts historically).
        $empty = \App\Services\Rates\RatesXmlMonitorGate::EMPTY_RATES_XML;

        $publisher = new AtomicPublicXmlPublisher();
        $isCanonicalCurrencies = str_contains($filename, '/static/exports/')
            && str_ends_with($filename, 'currencies.xml');

        $result = $publisher->publish($filename, $empty, [
            'min_items' => 0,
            'backup' => true,
            'sync_legacy' => $isCanonicalCurrencies,
        ]);

        if (!$result['published']) {
            Log::error('rates_xml_clear_publish_failed', $result);
            return;
        }

        Log::warning('rates_xml_cleared_for_offline', [
            'path' => $filename,
            'items' => $result['items'],
            'reason' => 'work_is_offline',
        ]);
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
