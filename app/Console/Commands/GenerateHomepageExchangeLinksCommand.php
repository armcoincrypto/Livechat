<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DirectionExchange;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * P-FOOTER-SEO-3: Generate localized homepage footer exchange link blocks.
 *
 * Reads INDEX-tier direction IDs, emits public HTML mirrors and nginx sub_filter snippets.
 */
final class GenerateHomepageExchangeLinksCommand extends Command
{
    private const INDEX_TIER_IDS_PATH = 'app/seo/exchange_index_tier_ids.txt';

    private const HOMEPAGE_LINK_LIMIT = 12;

    private const PUBLIC_HTML_DIR = 'static/seo';

    private const GENERATED_NGINX_DIR = 'app/seo/generated';

    /** @var list<string> */
    private const ACTIVE_LOCALES = ['ru', 'en', 'uk', 'ka'];

    protected $signature = 'seo:generate-homepage-exchange-links
        {--limit=12 : Number of INDEX-tier directions for the homepage block}
        {--frontend-url= : Override app.frontend_url for href generation}
        {--force-zh-links : Include /zh/exchange links even when probe returns 404 (not recommended)}
    ';

    protected $description = 'Generate localized homepage SEO exchange link HTML + nginx sub_filter snippets';

    public function handle(): int
    {
        $frontendUrl = $this->resolveFrontendUrl();
        if ($frontendUrl === null) {
            $this->error('app.frontend_url is not configured.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $directionIds = array_slice($this->loadIndexTierDirectionIds(), 0, $limit);
        $pairs = $this->loadDirectionPairs($directionIds);

        if ($pairs === []) {
            $this->error('No resolvable INDEX-tier direction pairs.');

            return self::FAILURE;
        }

        $zhLinksEnabled = $this->shouldIncludeZhLinks();

        File::ensureDirectoryExists(public_path(self::PUBLIC_HTML_DIR));
        File::ensureDirectoryExists(storage_path(self::GENERATED_NGINX_DIR));

        $locales = self::ACTIVE_LOCALES;
        if ($zhLinksEnabled) {
            $locales[] = 'zh';
        }

        foreach ($locales as $locale) {
            $sectionHtml = $this->buildSectionHtml($locale, $frontendUrl, $pairs);
            $htmlPath = public_path(self::PUBLIC_HTML_DIR . '/homepage-exchange-links.' . $locale . '.html');
            File::put($htmlPath, $sectionHtml . "\n");

            $nginxPath = storage_path(self::GENERATED_NGINX_DIR . '/exswaping-homepage-seo-subfilter-' . $locale . '.conf');
            File::put($nginxPath, $this->buildNginxSnippet($locale, $sectionHtml));

            $this->line(sprintf('Generated %s + %s (%d links)', basename($htmlPath), basename($nginxPath), count($pairs)));
        }

        $this->writeZhOmittedArtifacts($zhLinksEnabled);

        $this->info(sprintf(
            'Done: %d locales with links%s. Nginx snippets: storage/%s/',
            count($locales),
            $zhLinksEnabled ? ' + zh' : '; zh omitted (404)',
            self::GENERATED_NGINX_DIR
        ));

        return self::SUCCESS;
    }

    private function resolveFrontendUrl(): ?string
    {
        $override = trim((string) $this->option('frontend-url'));
        $raw = $override !== '' ? $override : trim((string) config('app.frontend_url'));
        if ($raw === '') {
            return null;
        }

        return rtrim($raw, '/');
    }

    /**
     * @return list<int>
     */
    private function loadIndexTierDirectionIds(): array
    {
        $path = storage_path(self::INDEX_TIER_IDS_PATH);
        if (!File::exists($path)) {
            throw new \RuntimeException('Missing INDEX tier list: ' . $path);
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

        return array_values(array_unique($ids));
    }

    /**
     * @param list<int> $directionIds
     * @return list<array{from: string, to: string}>
     */
    private function loadDirectionPairs(array $directionIds): array
    {
        $byId = DirectionExchange::query()
            ->whereIn('id', $directionIds)
            ->where('status', '=', 1)
            ->with([
                'currency1:id,designation_xml,tech_name',
                'currency2:id,designation_xml,tech_name',
            ])
            ->get(['id', 'id_currency1', 'id_currency2', 'status'])
            ->keyBy('id');

        $pairs = [];
        foreach ($directionIds as $id) {
            $direction = $byId->get($id);
            if ($direction === null) {
                $this->warn("Skipping missing/inactive direction id={$id}");
                continue;
            }

            $from = trim((string) ($direction->currency1?->designation_xml ?? ''));
            $to = trim((string) ($direction->currency2?->designation_xml ?? ''));
            if ($from === '' || $to === '') {
                $this->warn("Skipping direction id={$id} — empty designation_xml");
                continue;
            }

            $pairs[] = ['from' => $from, 'to' => $to];
        }

        return $pairs;
    }

    private function shouldIncludeZhLinks(): bool
    {
        if ((bool) $this->option('force-zh-links')) {
            $this->warn('--force-zh-links: including zh links despite probe result.');

            return true;
        }

        $url = rtrim((string) config('app.frontend_url'), '/') . '/zh/exchange/USDTTRC20/SBERRUB';
        $status = $this->probeHttpStatus($url);

        if ($status === 200) {
            $this->info("ZH probe OK (HTTP 200): {$url}");

            return true;
        }

        $this->warn("ZH exchange routes unavailable (HTTP {$status}) — zh footer links will be omitted.");

        return false;
    }

    private function probeHttpStatus(string $url): int
    {
        $cmd = 'curl -s -o /dev/null -w %{http_code} ' . escapeshellarg($url);
        $output = trim((string) shell_exec($cmd));

        return is_numeric($output) ? (int) $output : 0;
    }

    /**
     * @param list<array{from: string, to: string}> $pairs
     */
    private function buildSectionHtml(string $locale, string $frontendUrl, array $pairs): string
    {
        $meta = self::localeMeta()[$locale];
        $links = '';
        foreach ($pairs as $pair) {
            $href = $frontendUrl . '/' . $locale . '/exchange/' . $pair['from'] . '/' . $pair['to'];
            $label = $this->formatLinkLabel($locale, $pair['from'], $pair['to']);
            $links .= '<a class="seo-footer-links__link" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        }

        return '<section id="seo-popular-exchange-directions" class="seo-footer-links" aria-labelledby="seo-footer-links-title">'
            . self::styleBlock()
            . '<div class="seo-footer-links__inner">'
            . '<h2 id="seo-footer-links-title" class="seo-footer-links__title">'
            . htmlspecialchars($meta['heading'], ENT_QUOTES, 'UTF-8')
            . '</h2>'
            . '<nav class="seo-footer-links__nav" aria-label="'
            . htmlspecialchars($meta['aria'], ENT_QUOTES, 'UTF-8')
            . '">' . $links . '</nav></div></section>';
    }

    private function buildNginxSnippet(string $locale, string $sectionHtml): string
    {
        $escaped = str_replace("'", "\\'", $sectionHtml);

        return implode("\n", [
            '# SEO Phase E — homepage INDEX-tier exchange links (' . $locale . ')',
            '# Generated by: php artisan seo:generate-homepage-exchange-links',
            'sub_filter_types text/html;',
            "sub_filter '</body>' '" . $escaped . "</body>';",
            '',
        ]);
    }

    private function writeZhOmittedArtifacts(bool $zhLinksEnabled): void
    {
        if ($zhLinksEnabled) {
            return;
        }

        $comment = '<!-- zh exchange links omitted because /zh/exchange/* routes return 404 (locale build not deployed) -->';
        $htmlPath = public_path(self::PUBLIC_HTML_DIR . '/homepage-exchange-links.zh.html');
        File::put($htmlPath, $comment . "\n");

        $nginxPath = storage_path(self::GENERATED_NGINX_DIR . '/exswaping-homepage-seo-subfilter-zh.conf');
        File::put($nginxPath, implode("\n", [
            '# SEO Phase E — homepage INDEX-tier exchange links (zh)',
            '# Generated by: php artisan seo:generate-homepage-exchange-links',
            '# zh exchange links omitted: /zh/exchange/* returns 404 — no sub_filter injection',
            'sub_filter_types text/html;',
            '',
        ]));

        $this->line('Generated zh omitted artifacts (comment-only HTML, no sub_filter).');
    }

    private static function styleBlock(): string
    {
        return '<style>'
            . '#seo-popular-exchange-directions{color-scheme:dark;padding:36px 24px 112px;background:#15151d;}'
            . '#seo-popular-exchange-directions .seo-footer-links__inner{max-width:1180px;margin:0 auto;}'
            . '#seo-popular-exchange-directions .seo-footer-links__title{margin:0 0 18px;font-size:clamp(15px,1.2vw,17px);line-height:1.25;font-weight:700;color:rgba(255,255,255,.9);}'
            . '#seo-popular-exchange-directions .seo-footer-links__nav{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px 26px;}'
            . '#seo-popular-exchange-directions .seo-footer-links__link{position:relative;min-width:0;color:rgba(255,255,255,.68);font-size:13px;line-height:1.38;text-decoration:none;overflow-wrap:anywhere;'
            . 'transition:color .18s ease,opacity .18s ease,transform .18s ease;}'
            . '#seo-popular-exchange-directions .seo-footer-links__link:hover{color:#fff;opacity:1;transform:translateX(2px);}'
            . '#seo-popular-exchange-directions .seo-footer-links__link:focus-visible{outline:2px solid rgba(124,92,255,.75);outline-offset:3px;border-radius:6px;}'
            . '@media (max-width:900px){#seo-popular-exchange-directions .seo-footer-links__nav{grid-template-columns:repeat(2,minmax(0,1fr));}}'
            . '@media (max-width:560px){#seo-popular-exchange-directions{padding:22px 18px 124px;}'
            . '#seo-popular-exchange-directions .seo-footer-links__nav{grid-template-columns:1fr;gap:8px;}'
            . '#seo-popular-exchange-directions .seo-footer-links__link{font-size:12.5px;}}'
            . '</style>';
    }

    private function formatLinkLabel(string $locale, string $fromCode, string $toCode): string
    {
        $from = $this->resolveFromLabel($locale, $fromCode);
        $to = $this->resolveToLabel($locale, $toCode);
        $template = self::localeMeta()[$locale]['template'];

        return str_replace(['{from}', '{to}'], [$from, $to], $template);
    }

    private function resolveFromLabel(string $locale, string $code): string
    {
        $map = self::fromLabels();

        return $map[$code][$locale] ?? $map[$code]['en'] ?? $code;
    }

    private function resolveToLabel(string $locale, string $code): string
    {
        $map = self::toLabels();

        return $map[$code][$locale] ?? $map[$code]['en'] ?? $code;
    }

    /**
     * @return array<string, array{heading: string, aria: string, template: string}>
     */
    private static function localeMeta(): array
    {
        return [
            'en' => [
                'heading' => 'Popular exchange directions',
                'aria' => 'Popular exchange directions',
                'template' => 'Exchange {from} to {to}',
            ],
            'ru' => [
                'heading' => 'Популярные направления обмена',
                'aria' => 'Популярные направления обмена',
                'template' => 'Обмен {from} на {to}',
            ],
            'uk' => [
                'heading' => 'Популярні напрямки обміну',
                'aria' => 'Популярні напрямки обміну',
                'template' => 'Обмін {from} на {to}',
            ],
            'ka' => [
                'heading' => 'პოპულარული გაცვლის მიმართულებები',
                'aria' => 'პოპულარული გაცვლის მიმართულებები',
                'template' => '{from}-ის გაცვლა {to}-ზე',
            ],
            'zh' => [
                'heading' => '热门兑换方向',
                'aria' => '热门兑换方向',
                'template' => '{from} 兑换 {to}',
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function fromLabels(): array
    {
        return [
            'USDTTRC20' => [
                'en' => 'USDT TRC20', 'ru' => 'USDT TRC20', 'uk' => 'USDT TRC20', 'ka' => 'USDT TRC20', 'zh' => 'USDT TRC20',
            ],
            'USDTBEP20' => [
                'en' => 'USDT BEP20', 'ru' => 'USDT BEP20', 'uk' => 'USDT BEP20', 'ka' => 'USDT BEP20', 'zh' => 'USDT BEP20',
            ],
            'BTC' => [
                'en' => 'Bitcoin BTC', 'ru' => 'Bitcoin BTC', 'uk' => 'Bitcoin BTC', 'ka' => 'Bitcoin BTC', 'zh' => 'Bitcoin BTC',
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function toLabels(): array
    {
        return [
            'SBERRUB' => [
                'en' => 'Sberbank RUB', 'ru' => 'Сбербанк RUB', 'uk' => 'Сбербанк RUB', 'ka' => 'Sberbank RUB', 'zh' => 'Sberbank RUB',
            ],
            'SBPRUB' => [
                'en' => 'SBP RUB', 'ru' => 'СБП RUB', 'uk' => 'СБП RUB', 'ka' => 'SBP RUB', 'zh' => 'SBP RUB',
            ],
            'TCSBRUB' => [
                'en' => 'Tinkoff RUB', 'ru' => 'Тинькофф RUB', 'uk' => 'Тинькофф RUB', 'ka' => 'Tinkoff RUB', 'zh' => 'Tinkoff RUB',
            ],
            'P24UAH' => [
                'en' => 'Privat24 UAH', 'ru' => 'Приват24 UAH', 'uk' => 'Приват24 UAH', 'ka' => 'Privat24 UAH', 'zh' => 'Privat24 UAH',
            ],
            'ACRUB' => [
                'en' => 'Alfa-Bank RUB', 'ru' => 'Альфа-Банк RUB', 'uk' => 'Альфа-Банк RUB', 'ka' => 'Alfa-Bank RUB', 'zh' => 'Alfa-Bank RUB',
            ],
            'MONOBUAH' => [
                'en' => 'Monobank UAH', 'ru' => 'Монобанк UAH', 'uk' => 'Монобанк UAH', 'ka' => 'Monobank UAH', 'zh' => 'Monobank UAH',
            ],
            'ALPCNY' => [
                'en' => 'Alipay CNY', 'ru' => 'Alipay CNY', 'uk' => 'Alipay CNY', 'ka' => 'Alipay CNY', 'zh' => '支付宝 CNY',
            ],
            'CARDUAH' => [
                'en' => 'Visa Card UAH', 'ru' => 'Visa Card UAH', 'uk' => 'Visa Card UAH', 'ka' => 'Visa Card UAH', 'zh' => 'Visa Card UAH',
            ],
            'KSPBKZT' => [
                'en' => 'Kaspi KZT', 'ru' => 'Kaspi KZT', 'uk' => 'Kaspi KZT', 'ka' => 'Kaspi KZT', 'zh' => 'Kaspi KZT',
            ],
        ];
    }
}
