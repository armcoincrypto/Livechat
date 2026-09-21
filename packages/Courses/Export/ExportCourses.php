<?php

declare(strict_types=1);

namespace iEXPackages\Courses\Export;

use App\Services\Rates\AtomicPublicXmlPublisher;
use App\Services\Rates\RatesXmlMonitorGate;
use App\Services\Rates\RatesXmlPublicFeedHealth;
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
 *
 * Online contract: never clear/empty the public feed while SITE_ONLINE+TECH_OFF.
 * Failed/invalid candidates leave the previous public XML untouched.
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
        $stageCandidate = RatesXmlMonitorGate::isHidden() && ! $this->isClear;
        if ($stageCandidate) {
            $filename = $filename.'.candidate';
        }

        // Online full-feed contract: never replace a live full file with empty/near-empty.
        if (! $stageCandidate && RatesXmlMonitorGate::mustKeepFullPublicFeed() && $items < 1) {
            Log::critical('rates_xml_PUBLISH_REJECTED', [
                'reason' => 'online_empty_candidate_refused',
                'path' => $livePath,
                'candidate_bytes' => strlen($xml),
                'candidate_rows' => $items,
            ]);
            @file_put_contents($livePath.'.failed.'.gmdate('Ymd\THis\Z'), $xml);

            return;
        }

        // Collapse guard always vs the live path's last-good (not the candidate).
        if ($publisher->collapsesAgainstLastGood($livePath, $items)) {
            Log::error('rates_xml_PUBLISH_REJECTED', [
                'reason' => 'item_collapse_vs_last_good',
                'path' => $filename,
                'live' => $livePath,
                'candidate_rows' => $items,
                'candidate_bytes' => strlen($xml),
            ]);
            @file_put_contents($filename.'.failed.'.gmdate('Ymd\THis\Z'), $xml);

            return;
        }

        $isCanonicalCurrencies = str_contains($livePath, '/static/exports/')
            && str_ends_with($livePath, 'currencies.xml');

        $result = $publisher->publish($filename, $xml, [
            'min_items' => 1,
            'backup' => ! $stageCandidate,
            'sync_legacy' => $isCanonicalCurrencies && ! $stageCandidate,
        ]);

        if (! $result['published']) {
            Log::error('rates_xml_PUBLISH_REJECTED', [
                'reason' => $result['reason'] ?? 'publish_refused',
                'path' => $filename,
                'candidate_rows' => $items,
                'candidate_bytes' => strlen($xml),
            ] + $result);
            @file_put_contents($filename.'.failed.'.gmdate('Ymd\THis\Z'), $xml);

            return;
        }

        if ($isCanonicalCurrencies && ! $stageCandidate) {
            Log::info('rates_xml_publish_success', [
                'path' => $livePath,
                'rows' => $result['items'],
                'bytes' => strlen($xml),
            ]);
            RatesXmlPublicFeedHealth::assertOnlineInvariant('export_put_success');
        }
    }

    protected function exists(string $filename): bool
    {
        return File::exists($filename);
    }

    public function clear(string $filename): void
    {
        // Online + tech-off: never expose EMPTY_RATES_XML. Keep last-known-good.
        if (! RatesXmlMonitorGate::mayPublishEmptyFeed()) {
            Log::critical('rates_xml_clear_blocked_site_online', [
                'path' => $filename,
                'reason' => 'SITE_ONLINE+TECH_OFF forbids empty monitor feed',
                'kept_bytes' => is_file($filename) ? filesize($filename) : 0,
                'kept_rows' => is_file($filename)
                    ? substr_count((string) file_get_contents($filename), '<item>')
                    : 0,
            ]);

            return;
        }

        // Intentional pause: gate hide publishes empty to all public paths + flag.
        RatesXmlMonitorGate::hide('scheme:files_clear');

        Log::warning('rates_xml_cleared_for_offline', [
            'path' => $filename,
            'items' => 0,
            'reason' => 'work_is_offline_or_tech_mode',
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
