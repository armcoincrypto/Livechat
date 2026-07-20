<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Report XML authority roles and optional legacy sync from canonical export.
 */
final class RatesXmlAuthorityCommand extends Command
{
    protected $signature = 'rates:xml-authority
        {--sync-legacy : Copy canonical export over public/currencies.xml}
        {--format=json : json}';

    protected $description = 'Document/verify XML export authority; optionally sync legacy public/currencies.xml.';

    public function handle(): int
    {
        $public = public_path('currencies.xml');
        $canonical = public_path('static/exports/currencies.xml');
        $changed = public_path('static/exports/changed-currencies.xml');

        $hash = static function (string $path): ?string {
            return is_file($path) ? hash_file('sha256', $path) : null;
        };
        $items = static function (string $path): ?int {
            if (!is_file($path)) {
                return null;
            }

            return substr_count((string) file_get_contents($path), '<item>');
        };

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'surfaces' => [
                'public/static/exports/currencies.xml' => [
                    'role' => 'AUTHORITATIVE_PUBLIC_EXPORT',
                    'path' => $canonical,
                    'sha256' => $hash($canonical),
                    'items' => $items($canonical),
                    'nginx_served' => true,
                ],
                'public/static/exports/changed-currencies.xml' => [
                    'role' => 'GENERATED_CERTIFICATION_COPY',
                    'path' => $changed,
                    'sha256' => $hash($changed),
                    'items' => $items($changed),
                    'nginx_served' => false,
                    'note' => 'URL-enriched twin from xml-changer; rate mutation disabled by default',
                ],
                'public/currencies.xml' => [
                    'role' => 'LEGACY_NON_AUTHORITATIVE',
                    'path' => $public,
                    'sha256' => $hash($public),
                    'items' => $items($public),
                    'nginx_served' => false,
                ],
            ],
            'canonical_vs_changed_item_parity' => $items($canonical) === $items($changed),
            'legacy_matches_canonical' => $hash($public) !== null && $hash($public) === $hash($canonical),
            'synced_legacy' => false,
            'legacy_backup' => null,
        ];

        if ((bool) $this->option('sync-legacy')) {
            if (!is_file($canonical)) {
                $this->error('canonical_missing');

                return self::FAILURE;
            }
            $backup = null;
            if (is_file($public)) {
                $backup = $public . '.stale.' . gmdate('Ymd\THis\Z');
                rename($public, $backup);
            }
            if (!copy($canonical, $public)) {
                $this->error('legacy_copy_failed');

                return self::FAILURE;
            }
            $payload['synced_legacy'] = true;
            $payload['legacy_backup'] = $backup;
            $payload['surfaces']['public/currencies.xml']['sha256'] = $hash($public);
            $payload['surfaces']['public/currencies.xml']['items'] = $items($public);
            $payload['surfaces']['public/currencies.xml']['role'] = 'LEGACY_SYNCED_COPY_OF_CANONICAL_NON_AUTHORITATIVE_PATH';
            $payload['legacy_matches_canonical'] = true;
        }

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
