<?php

declare(strict_types=1);

/**
 * P-CONTENT-FIX-1 — Fix About heading hierarchy and AML bullet structure.
 *
 * Usage: php8.4 scripts/fix-content-pages-p1.php [--dry-run]
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Page;

$dryRun = in_array('--dry-run', $argv, true);
$backupDir = __DIR__ . '/../storage/app/content-fix-1-backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

function normalizeText(string $text): string
{
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

function bulletParagraphsToList(string $html): string
{
    return preg_replace_callback(
        '/(?:<p[^>]*>\s*(?:&nbsp;|\s)*•\s*(.*?)<\/p>\s*)+/isu',
        static function (array $m): string {
            preg_match_all('/<p[^>]*>\s*(?:&nbsp;|\s)*•\s*(.*?)<\/p>/isu', $m[0], $items);
            if (empty($items[1])) {
                return $m[0];
            }
            $lis = array_map(
                static fn (string $item): string => '<li>' . trim($item) . '</li>',
                $items[1]
            );

            return '<ul>' . implode('', $lis) . '</ul>';
        },
        $html
    ) ?? $html;
}

function fixAmlContent(string $html, string $locale): string
{
    // Normalize heading levels: section titles should be H2, not H1.
    $html = preg_replace('/<h1\b/i', '<h2', $html) ?? $html;
    $html = preg_replace('/<\/h1>/i', '</h2>', $html) ?? $html;

    // RU intro: keep policy title as first H2, demote duplicate AML Policy label if present.
    if ($locale === 'ru') {
        $html = preg_replace(
            '/<h2>\s*AML Policy\s*<\/h2>\s*<h2>\s*(Политика противодействия отмыванию средств \(AML\) сервиса Exswaping)\s*<\/h2>/iu',
            '<h2>$1</h2>',
            $html,
            1
        ) ?? $html;
    }

    if ($locale === 'en') {
        // Merge duplicate AML headings at top.
        $html = preg_replace(
            '/<h2>\s*AML Policy\s*<\/h2>\s*<h3>\s*(Anti-Money Laundering Policy of Exswaping)\s*<\/h3>/iu',
            '<h2>$1</h2>',
            $html,
            1
        ) ?? $html;
        $html = preg_replace('/<h3\b/i', '<h2', $html) ?? $html;
        $html = preg_replace('/<\/h3>/i', '</h2>', $html) ?? $html;
    }

    // Convert inline bullet paragraphs to semantic lists.
    $html = bulletParagraphsToList($html);

    // Fix orphaned h4 bullet at end (RU).
    $html = preg_replace(
        '/<h4>\s*•\s*(.*?)\s*<\/h4>/iu',
        '<ul><li>$1</li></ul>',
        $html
    ) ?? $html;

    // Risk level lines → compact list when consecutive plain paragraphs follow intro.
    $html = preg_replace_callback(
        '/(<p>Risk levels are categorized as follows:<\/p>|<p>Уровни риска классифицируются следующим образом:<\/p>)\s*((?:<p>[^<]*Risk[^<]*<\/p>\s*){3})/iu',
        static function (array $m): string {
            preg_match_all('/<p>(.*?)<\/p>/u', $m[2], $rows);
            $lis = array_map(static fn ($r) => '<li>' . trim($r) . '</li>', $rows[1]);

            return $m[1] . '<ul>' . implode('', $lis) . '</ul>';
        },
        $html
    ) ?? $html;

    $html = preg_replace_callback(
        '/(<p>Уровни риска классифицируются следующим образом:<\/p>)\s*((?:<p>\s*(?:Low|Medium|High)[^<]*<\/p>\s*){3})/iu',
        static function (array $m): string {
            preg_match_all('/<p>(.*?)<\/p>/u', $m[2], $rows);
            $lis = array_map(static fn ($r) => '<li>' . trim($r) . '</li>', $rows[1]);

            return $m[1] . '<ul>' . implode('', $lis) . '</ul>';
        },
        $html
    ) ?? $html;

    return trim($html);
}

function fixAboutContent(string $html, string $locale): string
{
    $sectionMarkers = $locale === 'ru'
        ? ['ЧТО МЫ ПРЕДЛАГАЕМ', 'НАШИ ЦЕННОСТИ', 'ПОЧЕМУ ВЫБИРАЮТ EXSWAPING?']
        : ['What We Offer:', 'Our Values:', 'Why Choose ExSwaping:'];

    $blocks = preg_split('/(?=<h[1-4][^>]*>)/iu', $html, -1, PREG_SPLIT_NO_EMPTY) ?: [$html];
    $out = [];
    $currentList = [];
    $sectionIndex = 0;
    $introDone = false;

    $flushList = static function () use (&$currentList, &$out): void {
        if ($currentList !== []) {
            $out[] = '<ul>' . implode('', array_map(static fn ($li) => '<li>' . $li . '</li>', $currentList)) . '</ul>';
            $currentList = [];
        }
    };

    foreach ($blocks as $block) {
        if (!preg_match('/^<(h[1-4])([^>]*)>(.*)<\/\1>$/isu', trim($block), $m)) {
            $trim = trim($block);
            if ($trim !== '') {
                $flushList();
                $out[] = $trim;
            }
            continue;
        }

        $level = strtolower($m[1]);
        $inner = $m[3];
        $text = normalizeText($inner);

        if ($level === 'h1') {
            $flushList();
            if (stripos($inner, '<img') !== false) {
                $out[] = '<p class="ql-align-center">' . $inner . '</p>';
            } else {
                $out[] = '<p>' . $inner . '</p>';
            }
            continue;
        }

        $upper = mb_strtoupper($text);
        $isSection = false;
        foreach ($sectionMarkers as $marker) {
            if ($upper === mb_strtoupper($marker) || str_starts_with($upper, mb_strtoupper(rtrim($marker, ':')))) {
                $flushList();
                $out[] = '<h2>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h2>';
                $sectionIndex++;
                $introDone = true;
                $isSection = true;
                break;
            }
        }
        if ($isSection) {
            continue;
        }

        if (!$introDone) {
            $flushList();
            $out[] = '<p>' . $inner . '</p>';
            continue;
        }

        if (str_contains($text, ':') && $sectionIndex <= 2) {
            [$title, $body] = array_map('trim', explode(':', $text, 2));
            $item = '<strong>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>';
            if ($body !== '') {
                $item .= ': ' . htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            $currentList[] = $item;
            continue;
        }

        if ($sectionIndex === 3) {
            $currentList[] = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            continue;
        }

        if (mb_strlen($text) > 120) {
            $flushList();
            $out[] = '<p>' . $inner . '</p>';
            continue;
        }

        $flushList();
        $out[] = '<h2>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h2>';
    }

    $flushList();

    return trim(implode('', $out));
}

function countTags(string $html, string $tag): int
{
    return preg_match_all('/<' . $tag . '\b/i', $html) ?: 0;
}

function backupPage(Page $page): void
{
    global $backupDir;
    $payload = [
        'page_id' => $page->page_id,
        'page_slug' => $page->page_slug,
        'page_title' => $page->getTranslations('page_title'),
        'page_headline' => $page->getTranslations('page_headline'),
        'page_content' => $page->getTranslations('page_content'),
        'backed_up_at' => date('c'),
    ];
    file_put_contents(
        $backupDir . '/' . $page->page_slug . '.json',
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

$updates = [
    'about' => ['ru', 'en'],
    'AMLKYC' => ['ru', 'en'],
];

foreach ($updates as $slug => $locales) {
    $page = Page::query()->where('page_slug', $slug)->first();
    if (!$page) {
        fwrite(STDERR, "Missing page: {$slug}\n");
        exit(1);
    }

    backupPage($page);
    echo "Backed up {$slug}\n";

    foreach ($locales as $locale) {
        $before = (string) $page->getTranslation('page_content', $locale, false);
        $after = $slug === 'about'
            ? fixAboutContent($before, $locale)
            : fixAmlContent($before, $locale);

        $beforeH1 = countTags($before, 'h1');
        $afterH1 = countTags($after, 'h1');
        $beforeH2 = countTags($before, 'h2');
        $afterH2 = countTags($after, 'h2');
        $beforeUl = preg_match_all('/<ul\b/i', $before) ?: 0;
        $afterUl = preg_match_all('/<ul\b/i', $after) ?: 0;

        echo "{$slug}/{$locale}: h1 {$beforeH1} -> {$afterH1}, h2 {$beforeH2} -> {$afterH2}, ul {$beforeUl} -> {$afterUl}\n";

        if (!$dryRun) {
            $page->setTranslation('page_content', $locale, $after);
        }
    }

    if (!$dryRun) {
        $page->save();
        echo "Saved {$slug}\n";
    }
}

echo $dryRun ? "DRY RUN complete\n" : "P-CONTENT-FIX-1 content updates applied\n";
