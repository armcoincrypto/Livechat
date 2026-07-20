<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Commands\UpdateSitemapCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * P14.16 — Sitemap EN/RU owned-locale policy tests (no live write).
 */
final class UpdateSitemapCommandTest extends TestCase
{
    private const SITEMAP_PATH = __DIR__ . '/../../public/static/seo/sitemap.xml';

    private const INDEX_TIER_PATH = __DIR__ . '/../../storage/app/seo/exchange_index_tier_ids.txt';

    /** @return list<string> */
    private function invokePrivate(string $method): array
    {
        $ref = new ReflectionClass(UpdateSitemapCommand::class);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);

        /** @var list<string> $result */
        $result = $m->invoke($ref->newInstanceWithoutConstructor());

        return $result;
    }

    private function invokePrivateScalar(string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionClass(UpdateSitemapCommand::class);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);

        return $m->invoke($ref->newInstanceWithoutConstructor(), ...$args);
    }

    /** @return list<string> */
    private function sitemapLocs(): array
    {
        self::assertFileExists(self::SITEMAP_PATH);
        $xml = file_get_contents(self::SITEMAP_PATH);
        self::assertNotFalse($xml);
        preg_match_all('#<loc>(https?://[^<]+)</loc>#', $xml, $matches);

        return $matches[1] ?? [];
    }

    public function test_guide_directory_routes_include_en_and_ru_hubs_not_news(): void
    {
        $routes = $this->invokePrivate('guideDirectoryRoutes');
        self::assertContains('ru/blog', $routes);
        self::assertContains('en/blog', $routes);
        self::assertContains('ru/guides', $routes);
        self::assertContains('en/guides', $routes);
        self::assertNotContains('ru/news', $routes);
        self::assertNotContains('uk/guides', $routes);
        self::assertNotContains('ka/guides', $routes);
    }

    public function test_indexable_guide_articles_include_ru_set_and_en_owned_slugs(): void
    {
        $routes = $this->invokePrivate('indexableGuideArticleRoutes');
        self::assertContains('ru/guides/obmen-usdt-na-rubli', $routes);
        self::assertContains('ru/guides/obmen-usdt-na-kartu', $routes);
        self::assertContains('en/guides/usdt-exchange', $routes);
        self::assertContains('en/guides/usdt-to-bank-card', $routes);
        self::assertContains('en/guides/usdt-trc20-exchange', $routes);
        self::assertCount(
            count($this->invokePrivate('indexableRuGuideArticleRoutes'))
            + count($this->invokePrivate('indexableEnGuideArticleRoutes')),
            $routes
        );
        foreach ($routes as $route) {
            self::assertDoesNotMatchRegularExpression('#^(uk|ka|zh)/#', $route);
        }
    }

    public function test_join_localized_url_emits_en_and_ru_homepages(): void
    {
        $base = 'https://exswaping.com';
        self::assertSame(
            'https://exswaping.com/en/',
            $this->invokePrivateScalar('joinLocalizedUrl', $base, 'en', '')
        );
        self::assertSame(
            'https://exswaping.com/ru/',
            $this->invokePrivateScalar('joinLocalizedUrl', $base, 'ru', '')
        );
        self::assertSame(
            'https://exswaping.com/en/exchange/BTC/USDTTRC20',
            $this->invokePrivateScalar('joinLocalizedUrl', $base, 'en', 'exchange/BTC/USDTTRC20')
        );
        self::assertSame(
            'https://exswaping.com/ru/faq',
            $this->invokePrivateScalar('joinLocalizedUrl', $base, 'ru', 'faq')
        );
    }

    public function test_join_localized_url_strips_accidental_locale_prefix(): void
    {
        $base = 'https://exswaping.com';
        self::assertSame(
            'https://exswaping.com/en/pages/about',
            $this->invokePrivateScalar('joinLocalizedUrl', $base, 'en', 'ru/pages/about')
        );
    }

    public function test_cms_amlkyc_slug_normalized_to_lowercase(): void
    {
        self::assertSame('amlkyc', $this->invokePrivateScalar('normalizeCmsSitemapSlug', 'AMLKYC'));
        self::assertSame('amlkyc', $this->invokePrivateScalar('normalizeCmsSitemapSlug', 'amlkyc'));
    }

    public function test_excluded_private_and_dead_slugs(): void
    {
        self::assertTrue($this->invokePrivateScalar('isExcludedLegalDeadSlug', 'terms'));
        self::assertTrue($this->invokePrivateScalar('isExcludedLegalDeadSlug', 'privacy-policy'));
        self::assertFalse($this->invokePrivateScalar('isExcludedLegalDeadSlug', 'about'));
    }

    public function test_owned_locales_constant_is_en_ru_only(): void
    {
        $ref = new ReflectionClass(UpdateSitemapCommand::class);
        $const = $ref->getConstant('OWNED_SITEMAP_LOCALES');
        self::assertSame(['ru', 'en'], $const);
    }

    public function test_live_sitemap_locs_are_unique_and_canonical_host(): void
    {
        $locs = $this->sitemapLocs();
        self::assertNotEmpty($locs);
        self::assertSame(count($locs), count(array_unique($locs)), 'Duplicate <loc> entries found');
        foreach ($locs as $url) {
            self::assertStringStartsWith('https://exswaping.com/', $url);
            self::assertDoesNotMatchRegularExpression('#/(uk|ka|zh)/#', $url);
            self::assertStringNotContainsString('/account', $url);
            self::assertStringNotContainsString('/auth/', $url);
            self::assertStringNotContainsString('/order/', $url);
            self::assertStringNotContainsString('/admin', $url);
            self::assertStringNotContainsString('/apis/', $url);
            self::assertStringNotContainsString('?', $url);
        }
    }

    public function test_exchange_tier_file_exists_and_has_ids(): void
    {
        self::assertFileExists(self::INDEX_TIER_PATH);
        $tierIds = $this->loadTierIdsFromFile();
        self::assertGreaterThan(0, count($tierIds));
    }

    public function test_direction_exchange_query_requires_enabled_status(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Console/Commands/UpdateSitemapCommand.php');
        self::assertNotFalse($src);
        self::assertMatchesRegularExpression(
            "/->where\\('status',\\s*'=',\\s*1\\)/",
            $src,
            'Exchange sitemap emission must require status=1'
        );
        self::assertStringContainsString('strtoupper(trim((string) ($item->currency1?->designation_xml', $src);
        self::assertStringContainsString('strtoupper(trim((string) ($item->currency2?->designation_xml', $src);
    }

    public function test_disabled_zelleusd_cardkzt_and_usdttrc20_sbprub_ids_removed_from_tier_file(): void
    {
        $tierIds = $this->loadTierIdsFromFile();
        // Production-disabled INDEX leftovers that returned HTTP 404 while still listed.
        self::assertNotContains(1562, $tierIds, 'ZELLEUSD→CARDKZT (id 1562) must not remain in INDEX tier');
        self::assertNotContains(268, $tierIds, 'USDTTRC20→SBPRUB (id 268) must not remain in INDEX tier');
    }

    public function test_tier_file_has_no_duplicate_ids(): void
    {
        $tierIds = $this->loadTierIdsFromFile();
        self::assertSame(count($tierIds), count(array_unique($tierIds)));
    }

    public function test_join_localized_exchange_path_has_no_trailing_slash(): void
    {
        $base = 'https://exswaping.com';
        $url = $this->invokePrivateScalar(
            'joinLocalizedUrl',
            $base,
            'en',
            'exchange/USDTTRC20/SBERRUB'
        );
        self::assertSame('https://exswaping.com/en/exchange/USDTTRC20/SBERRUB', $url);
        self::assertStringEndsNotWith('/', $url);
    }

    public function test_owned_locales_emit_en_and_ru_consistently_for_paths(): void
    {
        $base = 'https://exswaping.com';
        self::assertSame(
            'https://exswaping.com/ru/exchange/BTC/SBERRUB',
            $this->invokePrivateScalar('joinLocalizedUrl', $base, 'ru', 'exchange/BTC/SBERRUB')
        );
        self::assertSame(
            'https://exswaping.com/en/exchange/BTC/SBERRUB',
            $this->invokePrivateScalar('joinLocalizedUrl', $base, 'en', 'exchange/BTC/SBERRUB')
        );
    }

    public function test_static_and_guide_surfaces_remain_in_generator(): void
    {
        $static = $this->invokePrivate('topLevelTrustStaticRoutes');
        self::assertContains('faq', $static);
        self::assertContains('contacts', $static);
        self::assertContains('partners', $static);
        self::assertContains('contests', $static);

        $guides = $this->invokePrivate('indexableGuideArticleRoutes');
        self::assertNotEmpty($guides);
        self::assertContains('en/guides/usdt-exchange', $guides);
        self::assertContains('ru/guides/obmen-usdt-na-rubli', $guides);
    }

    public function test_live_sitemap_exchange_urls_are_uppercase_without_slash_or_query(): void
    {
        $locs = $this->sitemapLocs();
        foreach ($locs as $url) {
            if (!str_contains($url, '/exchange/')) {
                continue;
            }
            self::assertDoesNotMatchRegularExpression('#/exchange/[a-z]#', $url);
            self::assertStringNotContainsString('?', $url);
            self::assertDoesNotMatchRegularExpression('#/exchange/.+/$#', $url);
            self::assertStringNotContainsString('ZELLEUSD/CARDKZT', $url);
            self::assertStringNotContainsString('USDTTRC20/SBPRUB', $url);
        }
    }

    /** @return list<int> */
    private function loadTierIdsFromFile(): array
    {
        $tierIds = [];
        foreach (file(self::INDEX_TIER_PATH) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $tierIds[] = (int) (preg_split('/\s+/', $line)[0] ?? 0);
        }

        return array_values(array_filter(array_unique($tierIds), static fn (int $id): bool => $id > 0));
    }
}
