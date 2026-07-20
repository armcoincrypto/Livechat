<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DirectionExchange;
use App\Models\News;
use App\Models\Page;
use App\Models\PageGroup;
use App\Models\PagesStatic;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * SEO sitemap generator for owned EN/RU public canonical routes.
 *
 * P14.16: emit EN and RU locale-prefixed URLs for owned Next.js cutover surfaces.
 * INDEX-tier exchanges only (see storage/app/seo/exchange_index_tier_ids.txt).
 * UK/KA/ZH are intentionally excluded (vendor-owned / not in owned LOCALES).
 */
final class UpdateSitemapCommand extends Command
{
    /** @var list<string> Owned sitemap locales (nginx + Next cutover). Order: RU then EN. */
    private const OWNED_SITEMAP_LOCALES = ['ru', 'en'];

    private const INDEX_TIER_IDS_PATH = 'app/seo/exchange_index_tier_ids.txt';

    /** Locales that may already appear in a path (detect / strip). */
    private const LOCALE_PREFIXES = ['ru', 'en', 'uk', 'ka', 'zh'];

    /**
     * @var string
     */
    protected $signature = 'update:sitemap
        {--ping= : URL для ping (например, поисковика). Если указан — будет выполнен GET запрос после генерации.}
        {--silent : Не выводить подробности в консоль (кроме ошибок).}
        {--output= : Путь к sitemap.xml (по умолчанию public/static/seo/sitemap.xml). Не пишет seo_sitemap_modification если путь не default.}
    ';

    /**
     * @var string
     */
    protected $description = 'Обновление карты сайта (EN/RU owned public routes)';

    public function handle(): int
    {
        $silent = (bool) $this->option('silent');

        $frontendUrl = $this->getFrontendBaseUrl();
        if ($frontendUrl === null) {
            $this->error('Не задан app.frontend_url. Sitemap не может быть создан.');

            return self::FAILURE;
        }

        $defaultDirectory = $this->ensureSitemapDirectory();
        $defaultPath = $defaultDirectory . '/sitemap.xml';
        $outputOption = trim((string) ($this->option('output') ?? ''));
        $sitemapPath = $outputOption !== '' ? $outputOption : $defaultPath;
        $writingDefault = $this->pathsEqual($sitemapPath, $defaultPath);

        // Always read blog preservation from the live default sitemap.
        $preservedBlogUrls = $this->extractBlogLocUrls($defaultPath);

        $sitemap = Sitemap::create();

        foreach (self::OWNED_SITEMAP_LOCALES as $locale) {
            $sitemap->add(
                Url::create($this->joinLocalizedUrl($frontendUrl, $locale, ''))
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                    ->setPriority(1.0)
            );
        }

        $this->addPagesAndGroupsUrls($sitemap, $frontendUrl, $silent);
        $this->addStaticUrls($sitemap, $frontendUrl, $silent);
        $this->addGuideDirectoryUrls($sitemap, $frontendUrl, $silent);
        $this->addIndexableGuideArticleUrls($sitemap, $frontendUrl, $silent);
        $this->addDirectionExchangeUrls($sitemap, $frontendUrl, $silent);
        $this->addBilingualBlogUrls($sitemap, $preservedBlogUrls, $silent);

        try {
            $outputDir = dirname($sitemapPath);
            if (!File::exists($outputDir)) {
                File::makeDirectory($outputDir, 0755, true);
            }

            $tmpPath = $sitemapPath . '.tmp.' . getmypid();
            File::put($tmpPath, $sitemap->render());
            File::move($tmpPath, $sitemapPath);

            if ($writingDefault) {
                iEXSetting(['seo_sitemap_modification' => Carbon::now()->format('c')]);
            }

            if (!$silent) {
                $this->info('Карта сайта успешно обновлена: ' . $sitemapPath);
                $this->line('Сохранено blog URL из предыдущего sitemap: ' . count($preservedBlogUrls));
                if (!$writingDefault) {
                    $this->line('Output override: seo_sitemap_modification not updated.');
                }
            }
        } catch (\Throwable $e) {
            $this->error('Ошибка записи файла sitemap.xml: ' . $e->getMessage());

            return self::FAILURE;
        }

        $pingUrl = trim((string) ($this->option('ping') ?? ''));
        if ($pingUrl !== '' && $writingDefault) {
            try {
                @file_get_contents($pingUrl);
                if (!$silent) {
                    $this->info('Ping выполнен: ' . $pingUrl);
                }
            } catch (\Throwable $e) {
                $this->warn('Ping не выполнен: ' . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    private function pathsEqual(string $a, string $b): bool
    {
        $na = rtrim(str_replace('\\', '/', $a), '/');
        $nb = rtrim(str_replace('\\', '/', $b), '/');

        return $na === $nb;
    }

    private function getFrontendBaseUrl(): ?string
    {
        $raw = trim((string) config('app.frontend_url'));
        if ($raw === '') {
            return null;
        }

        return rtrim($raw, '/');
    }

    private function ensureSitemapDirectory(): string
    {
        $directory = public_path('static/seo');

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        return $directory;
    }

    /**
     * Preserve locale blog article URLs from the previous sitemap.
     * Posts: RU only (EN blog articles remain thin/same-slug mirrors — hub covers EN).
     *
     * @return list<string>
     */
    private function extractBlogLocUrls(string $sitemapPath): array
    {
        if (!File::exists($sitemapPath)) {
            return [];
        }

        $xml = File::get($sitemapPath);
        if (!preg_match_all('#<loc>(https?://[^<]+)</loc>#', $xml, $matches)) {
            return [];
        }

        $urls = [];
        foreach ($matches[1] as $url) {
            // Article URLs only (hub is added via guideDirectoryRoutes).
            if (preg_match('#/ru/blog/.+#', $url) === 1) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Emit blog article URLs for every owned locale that has authoritative content.
     *
     * The curated RU set is preserved from the previous sitemap (publication /
     * indexability already decided there). Each article's EN counterpart shares
     * the same locale-invariant public slug ({parent_url}-{id}); the EN URL is
     * emitted only when the News record has a non-empty authoritative English
     * `text` translation (Spatie HasTranslations, no locale fallback) — never
     * manufactured from a matching slug alone. Proven bilingual for the current
     * 19-article corpus (P14.25 EN blog content-ownership availability matrix).
     *
     * @param list<string> $ruBlogUrls
     */
    private function addBilingualBlogUrls(Sitemap $sitemap, array $ruBlogUrls, bool $silent): void
    {
        $ruCount = 0;
        $enCount = 0;

        foreach ($ruBlogUrls as $ruUrl) {
            $sitemap->add(
                Url::create($ruUrl)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                    ->setPriority(0.6)
            );
            $ruCount++;

            if (preg_match('#/ru/blog/(.+)$#', $ruUrl, $m) !== 1) {
                continue;
            }

            if (!$this->newsSlugHasEnglishBody($m[1])) {
                continue;
            }

            $sitemap->add(
                Url::create(str_replace('/ru/blog/', '/en/blog/', $ruUrl))
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                    ->setPriority(0.6)
            );
            $enCount++;
        }

        if (!$silent && $ruBlogUrls !== []) {
            $this->line('Blog: RU ' . $ruCount . ' + EN ' . $enCount . ' URL (authoritative EN only).');
        }
    }

    /**
     * Whether the News article behind a public blog slug ({parent_url}-{id}) has
     * a non-empty authoritative English body translation (no locale fallback).
     */
    private function newsSlugHasEnglishBody(string $publicSlug): bool
    {
        if (preg_match('/-(\d+)$/', $publicSlug, $m) !== 1) {
            return false;
        }

        $news = News::find((int) $m[1]);
        if ($news === null) {
            return false;
        }

        $en = (string) $news->getTranslation('text', 'en', false);

        return trim(strip_tags($en)) !== '';
    }

    private function addPagesAndGroupsUrls(Sitemap $sitemap, string $frontendUrl, bool $silent): void
    {
        $added = 0;

        PageGroup::query()
            ->where('is_active', '=', 1)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->select(['id', 'slug', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, function ($groups) use ($sitemap, $frontendUrl, &$added) {
                foreach ($groups as $group) {
                    $slug = trim((string) $group->slug);
                    if ($slug === '') {
                        continue;
                    }

                    $path = 'pages/' . $slug;
                    if ($this->cmsPathDuplicatesTopLevelTrustRoute($path)) {
                        continue;
                    }

                    $added += $this->addOwnedLocaleUrls(
                        $sitemap,
                        $frontendUrl,
                        $path,
                        0.6,
                        $this->safeLastMod($group->updated_at)
                    );
                }
            }, 'id');

        Page::query()
            ->where('is_active', '=', 1)
            ->whereNotNull('page_slug')
            ->where('page_slug', '!=', '')
            ->with(['group:id,slug'])
            ->select(['page_id', 'page_slug', 'group_id', 'updated_at'])
            ->orderBy('page_id')
            ->chunkById(500, function ($pages) use ($sitemap, $frontendUrl, &$added) {
                foreach ($pages as $page) {
                    $slug = trim((string) $page->page_slug);
                    if ($slug === '') {
                        continue;
                    }

                    $path = 'pages/' . $this->normalizeCmsSitemapSlug($slug);

                    if ($page->group !== null && trim((string) $page->group->slug) !== '') {
                        $path = 'pages/' . trim((string) $page->group->slug) . '/' . $this->normalizeCmsSitemapSlug($slug);
                    }

                    if ($this->cmsPathDuplicatesTopLevelTrustRoute($path)) {
                        continue;
                    }

                    if ($this->isDeferredGuideCmsSlug($slug)) {
                        continue;
                    }

                    if ($this->isExcludedLegalDeadSlug($slug)) {
                        continue;
                    }

                    $added += $this->addOwnedLocaleUrls(
                        $sitemap,
                        $frontendUrl,
                        $path,
                        0.7,
                        $this->safeLastMod($page->updated_at)
                    );
                }
            }, 'page_id');

        if (!$silent) {
            $this->line('Страницы: добавлено ' . $added . ' URL (EN+RU).');
        }
    }

    private function addStaticUrls(Sitemap $sitemap, string $frontendUrl, bool $silent): void
    {
        $static = PagesStatic::query()->select(['id', 'updated_at'])->first();
        $lastMod = $static?->updated_at;
        $added = 0;

        foreach ($this->topLevelTrustStaticRoutes() as $route) {
            $added += $this->addOwnedLocaleUrls(
                $sitemap,
                $frontendUrl,
                $route,
                0.5,
                $this->safeLastMod($lastMod)
            );
        }

        if (!$silent) {
            $this->line('Статические страницы: добавлено ' . $added . ' URL (EN+RU).');
        }
    }

    private function addGuideDirectoryUrls(Sitemap $sitemap, string $frontendUrl, bool $silent): void
    {
        $added = 0;
        foreach ($this->guideDirectoryRoutes() as $route) {
            // Routes already include locale prefix.
            $sitemap->add(
                Url::create($this->joinAbsoluteLocalizedPath($frontendUrl, $route))
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                    ->setPriority(0.6)
            );
            $added++;
        }

        if (!$silent) {
            $this->line('Guide/blog directories: добавлено ' . $added . ' URL.');
        }
    }

    /**
     * Owned public hubs for guides and blog (no /ru/news — blog is canonical news hub).
     *
     * @return list<string>
     */
    private function guideDirectoryRoutes(): array
    {
        return array_merge(
            [
                'ru/guides',
                'en/guides',
                'ru/blog',
                'en/blog',
            ],
            // SEO P1.1 — payment-method + USDT topical hubs (owned Next.js routes).
            $this->seoTopicHubRoutes()
        );
    }

    /**
     * Owned SEO topic hubs (payment methods + USDT). Paths are locale-prefixed.
     *
     * @return list<string>
     */
    private function seoTopicHubRoutes(): array
    {
        $paths = [
            'usdt',
            'methods/sber',
            'methods/sbp',
            'methods/tbank',
        ];
        $routes = [];
        foreach (['en', 'ru'] as $locale) {
            foreach ($paths as $path) {
                $routes[] = $locale . '/' . $path;
            }
        }

        return $routes;
    }

    /**
     * Indexable guide articles: RU (full set) + EN owned registry slugs.
     *
     * @return list<string>
     */
    private function indexableGuideArticleRoutes(): array
    {
        return array_merge(
            $this->indexableRuGuideArticleRoutes(),
            $this->indexableEnGuideArticleRoutes()
        );
    }

    /**
     * @return list<string>
     */
    private function indexableRuGuideArticleRoutes(): array
    {
        return [
            'ru/guides/obmen-usdt-na-rubli',
            'ru/guides/monitoring-kriptovalyutnyh-obmennikov',
            'ru/guides/seti-usdt',
            'ru/guides/bezopasnyj-kriptoobmen',
            'ru/guides/obmen-usdt-trc20',
            'ru/guides/obmen-usdt-na-kartu',
            'ru/guides/usdt-trc20-i-erc20',
            'ru/guides/usdt-trc20-na-sberbank',
            'ru/guides/usdt-trc20-na-t-bank',
            'ru/guides/bitcoin-na-rubli',
            'ru/guides/kak-rabotayut-rezervy-obmennikov',
            'ru/guides/chto-takoe-sbp',
        ];
    }

    /**
     * EN guide articles from owned Next.js GUIDE_SLUGS.en (P9.1).
     *
     * @return list<string>
     */
    private function indexableEnGuideArticleRoutes(): array
    {
        return [
            'en/guides/usdt-exchange',
            'en/guides/usdt-to-bank-card',
            'en/guides/usdt-trc20-exchange',
            // P6 EN USDT network cluster (owned frontend GUIDE_SLUGS.en)
            'en/guides/usdt-networks',
            'en/guides/usdt-trc20-vs-erc20',
            // EN/RU content parity closeout — EN editions of previously RU-only guides
            'en/guides/usdt-trc20-to-sberbank',
            'en/guides/usdt-trc20-to-tbank',
            'en/guides/bitcoin-to-rubles',
            'en/guides/what-is-sbp',
            'en/guides/safe-crypto-exchange',
            'en/guides/how-exchanger-reserves-work',
            'en/guides/crypto-exchange-monitors',
        ];
    }

    private function addIndexableGuideArticleUrls(Sitemap $sitemap, string $frontendUrl, bool $silent): void
    {
        $routes = $this->indexableGuideArticleRoutes();

        foreach ($routes as $route) {
            $url = Url::create($this->joinAbsoluteLocalizedPath($frontendUrl, $route))
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                ->setPriority(0.75);
            // No request-time lastmod — guide bodies are filesystem/CMS without a shared timestamp here.
            $sitemap->add($url);
        }

        if (!$silent) {
            $this->line('Guide articles: добавлено ' . count($routes) . ' URL.');
        }
    }

    private function addDirectionExchangeUrls(Sitemap $sitemap, string $frontendUrl, bool $silent): void
    {
        $indexTierIds = $this->loadIndexTierDirectionIds();
        $added = 0;
        $skippedMissingAssets = 0;

        DirectionExchange::query()
            // Fail-closed: INDEX-tier membership alone is not enough — direction
            // must be enabled/public (status=1). Disabled rows remaining in the
            // tier file must never be emitted.
            ->where('status', '=', 1)
            ->whereIn('id', $indexTierIds)
            ->with([
                'currency1:id,designation_xml',
                'currency2:id,designation_xml',
            ])
            ->select(['id', 'id_currency1', 'id_currency2', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, function ($items) use ($sitemap, $frontendUrl, &$added, &$skippedMissingAssets) {
                foreach ($items as $item) {
                    $from = strtoupper(trim((string) ($item->currency1?->designation_xml ?? '')));
                    $to = strtoupper(trim((string) ($item->currency2?->designation_xml ?? '')));

                    if ($from === '' || $to === '' || $from === $to) {
                        $skippedMissingAssets++;
                        continue;
                    }

                    $path = 'exchange/' . $from . '/' . $to;
                    $added += $this->addOwnedLocaleUrls(
                        $sitemap,
                        $frontendUrl,
                        $path,
                        0.8,
                        $this->safeLastMod($item->updated_at)
                    );
                }
            }, 'id');

        if (!$silent) {
            $this->line(
                'Направления: добавлено ' . $added . ' INDEX-tier URL EN+RU (из '
                . count($indexTierIds) . ' ID).'
            );
            if ($skippedMissingAssets > 0) {
                $this->warn('Направления: пропущено без designation_xml: ' . $skippedMissingAssets);
            }
        }
    }

    /**
     * Add the same relative path for every owned locale. Returns number of URLs added.
     */
    private function addOwnedLocaleUrls(
        Sitemap $sitemap,
        string $frontendUrl,
        string $pathWithoutLocale,
        float $priority,
        ?Carbon $lastMod
    ): int {
        $count = 0;
        foreach (self::OWNED_SITEMAP_LOCALES as $locale) {
            $tag = Url::create($this->joinLocalizedUrl($frontendUrl, $locale, $pathWithoutLocale))
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                ->setPriority($priority);
            if ($lastMod !== null) {
                $tag->setLastModificationDate($lastMod);
            }
            $sitemap->add($tag);
            $count++;
        }

        return $count;
    }

    /**
     * @return list<int>
     */
    private function loadIndexTierDirectionIds(): array
    {
        $path = storage_path(self::INDEX_TIER_IDS_PATH);
        if (!File::exists($path)) {
            throw new \RuntimeException('SEO INDEX tier ID list missing: ' . $path);
        }

        $ids = [];
        foreach (File::lines($path) as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $first = preg_split('/\s+/', $line)[0] ?? '';
            $id = (int) $first;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            throw new \RuntimeException('SEO INDEX tier ID list is empty: ' . $path);
        }

        return $ids;
    }

    /**
     * Join base + owned locale + relative path (path must NOT include locale).
     */
    private function joinLocalizedUrl(string $base, string $locale, string $path): string
    {
        $base = rtrim($base, '/');
        $path = trim($path);
        $path = preg_replace('#^https?://[^/]+#i', '', $path) ?? $path;
        $path = trim($path, '/');

        // Strip accidental locale prefix if caller passed one.
        $lower = strtolower($path);
        foreach (self::LOCALE_PREFIXES as $prefix) {
            if ($lower === $prefix) {
                $path = '';
                break;
            }
            if (str_starts_with($lower, $prefix . '/')) {
                $path = substr($path, strlen($prefix) + 1);
                break;
            }
        }

        if ($path === '') {
            return $base . '/' . $locale . '/';
        }

        $path = preg_replace('~/{2,}~', '/', $path) ?? $path;
        $path = trim($path, '/');

        return $base . '/' . $locale . '/' . $path;
    }

    /**
     * Join base + already-localized path (e.g. "ru/guides/foo").
     */
    private function joinAbsoluteLocalizedPath(string $base, string $route): string
    {
        $base = rtrim($base, '/');
        $route = trim($route, '/');

        return $base . '/' . $route;
    }

    /**
     * @return list<string>
     */
    private function topLevelTrustStaticRoutes(): array
    {
        return ['contacts', 'faq', 'partners', 'contests'];
    }

    private function cmsPathDuplicatesTopLevelTrustRoute(string $path): bool
    {
        $path = trim($path, '/');
        foreach ($this->topLevelTrustStaticRoutes() as $route) {
            if (strcasecmp($path, 'pages/' . $route) === 0) {
                return true;
            }
        }

        return false;
    }

    private function normalizeCmsSitemapSlug(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return $slug;
        }

        if (strcasecmp($slug, 'amlkyc') === 0) {
            return 'amlkyc';
        }

        return $slug;
    }

    /**
     * @return list<string>
     */
    private function deferredGuideCmsSlugs(): array
    {
        return [
            'obmen-usdt-na-rubli',
            'obmen-usdt-trc20',
            'obmen-usdt-na-kartu',
            'usdt-trc20-i-erc20',
            'bezopasnyj-kriptoobmen',
            'monitoring-kriptovalyutnyh-obmennikov',
            'seti-usdt',
            'usdt-exchange',
            'usdt-trc20-exchange',
            'usdt-to-bank-card',
            'usdt-trc20-na-sberbank',
            'usdt-trc20-na-t-bank',
            'bitcoin-na-rubli',
            'kak-rabotayut-rezervy-obmennikov',
            'chto-takoe-sbp',
        ];
    }

    private function isDeferredGuideCmsSlug(string $slug): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        foreach ($this->deferredGuideCmsSlugs() as $deferred) {
            if (strcasecmp($slug, $deferred) === 0) {
                return true;
            }
        }

        return false;
    }

    private function isExcludedLegalDeadSlug(string $slug): bool
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return false;
        }

        foreach ($this->excludedLegalDeadSlugs() as $dead) {
            if ($slug === strtolower($dead)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function excludedLegalDeadSlugs(): array
    {
        return [
            'terms',
            'aml-kyc',
            'aml-kyc-policy',
            'return',
            'payment-details',
            'privacy-policy',
        ];
    }

    private function safeLastMod(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
