<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Privacy-safe hourly aggregates from Support Chat data only (no PII in output).
 */
final class SupportChatHourlyAnalyticsService
{
    public const SCOPE_SUPPORT_CHAT_ONLY = 'support_chat_only';

    public function __construct(
        private readonly SupportVisitorContextService $visitorContext,
    ) {}

    /**
     * @return array{
     *     scope: string,
     *     period_start: string,
     *     period_end: string,
     *     period_label: string,
     *     timezone: string,
     *     new_conversations: int,
     *     visitor_messages: int,
     *     unique_visitors: int,
     *     countries: list<array{label: string, count: int}>,
     *     languages: list<array{code: string, count: int}>,
     *     top_pages: list<array{path: string, count: int}>
     * }
     */
    public function aggregate(CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $tz = (string) config('app.timezone', 'UTC');
        $localStart = $periodStart->timezone($tz);
        $localEnd = $periodEnd->timezone($tz);

        // support_conversations.created_at is stored as app-local wall time (not UTC).
        $rangeStart = $localStart->format('Y-m-d H:i:s');
        $rangeEnd = $localEnd->format('Y-m-d H:i:s');

        $newConversations = SupportConversation::query()
            ->where('created_at', '>=', $rangeStart)
            ->where('created_at', '<', $rangeEnd)
            ->count();

        $visitorMessages = SupportMessage::query()
            ->where('sender_type', SupportMessage::SENDER_VISITOR)
            ->where('created_at', '>=', $rangeStart)
            ->where('created_at', '<', $rangeEnd)
            ->count();

        $activeConversationIds = $this->activeConversationIds($rangeStart, $rangeEnd);

        /** @var Collection<int, SupportConversation> $conversations */
        $conversations = $activeConversationIds === []
            ? collect()
            : SupportConversation::query()
                ->whereIn('id', $activeConversationIds)
                ->get(['id', 'visitor_ip', 'locale', 'page_url']);

        $uniqueVisitors = $this->countUniqueVisitors($conversations);
        $countries = $this->aggregateCountries($conversations);
        $languages = $this->aggregateLanguages($conversations);
        $topPages = $this->aggregateTopPages($conversations);

        $localStart = $periodStart->timezone($tz);
        $localEnd = $periodEnd->timezone($tz)->subSecond();

        return [
            'scope' => self::SCOPE_SUPPORT_CHAT_ONLY,
            'period_start' => $localStart->toIso8601String(),
            'period_end' => $localEnd->toIso8601String(),
            'period_label' => $localStart->format('H:i').'–'.$localEnd->format('H:i'),
            'timezone' => $tz,
            'new_conversations' => $newConversations,
            'visitor_messages' => $visitorMessages,
            'unique_visitors' => $uniqueVisitors,
            'countries' => $countries,
            'languages' => $languages,
            'top_pages' => $topPages,
        ];
    }

    /**
     * Previous completed local hour in app timezone.
     */
    public function defaultPeriod(): array
    {
        $tz = (string) config('app.timezone', 'UTC');
        $end = CarbonImmutable::now($tz)->startOfHour();
        $start = $end->subHour();

        return [$start, $end];
    }

    /**
     * @return list<int>
     */
    private function activeConversationIds(string $rangeStart, string $rangeEnd): array
    {
        $fromNew = SupportConversation::query()
            ->where('created_at', '>=', $rangeStart)
            ->where('created_at', '<', $rangeEnd)
            ->pluck('id');

        $fromMessages = SupportMessage::query()
            ->where('sender_type', SupportMessage::SENDER_VISITOR)
            ->where('created_at', '>=', $rangeStart)
            ->where('created_at', '<', $rangeEnd)
            ->distinct()
            ->pluck('support_conversation_id');

        return $fromNew->merge($fromMessages)->unique()->values()->all();
    }

    /**
     * @param  Collection<int, SupportConversation>  $conversations
     */
    private function countUniqueVisitors(Collection $conversations): int
    {
        $fingerprints = [];

        foreach ($conversations as $conversation) {
            $fingerprints[$this->visitorFingerprint($conversation)] = true;
        }

        return count($fingerprints);
    }

    /**
     * @param  Collection<int, SupportConversation>  $conversations
     * @return list<array{label: string, count: int}>
     */
    private function aggregateCountries(Collection $conversations): array
    {
        $counts = [];

        foreach ($conversations as $conversation) {
            $ctx = $this->visitorContext->resolve($conversation);
            $label = trim((string) ($ctx['country_display'] ?? ''));
            if ($label === '' || strcasecmp($label, 'Unknown') === 0) {
                continue;
            }
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        arsort($counts);

        $rows = [];
        foreach ($counts as $label => $count) {
            $rows[] = ['label' => $label, 'count' => (int) $count];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, SupportConversation>  $conversations
     * @return list<array{code: string, count: int}>
     */
    private function aggregateLanguages(Collection $conversations): array
    {
        $counts = [];

        foreach ($conversations as $conversation) {
            $code = $this->normalizeLocaleCode($conversation->locale);
            if ($code === null) {
                continue;
            }
            $counts[$code] = ($counts[$code] ?? 0) + 1;
        }

        arsort($counts);

        $rows = [];
        foreach ($counts as $code => $count) {
            $rows[] = ['code' => $code, 'count' => (int) $count];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, SupportConversation>  $conversations
     * @return list<array{path: string, count: int}>
     */
    private function aggregateTopPages(Collection $conversations): array
    {
        $counts = [];

        foreach ($conversations as $conversation) {
            $path = $this->maskPagePath($conversation->page_url);
            if ($path === null || $path === '') {
                continue;
            }
            $counts[$path] = ($counts[$path] ?? 0) + 1;
        }

        arsort($counts);

        $rows = [];
        foreach (array_slice($counts, 0, 5, true) as $path => $count) {
            $rows[] = ['path' => $path, 'count' => (int) $count];
        }

        return $rows;
    }

    private function visitorFingerprint(SupportConversation $conversation): string
    {
        $ip = trim((string) ($conversation->visitor_ip ?? ''));
        if ($ip !== '') {
            return hash('sha256', 'support-chat-visitor-ip:'.$ip);
        }

        return hash('sha256', 'support-chat-conversation:'.$conversation->id);
    }

    private function normalizeLocaleCode(?string $locale): ?string
    {
        if ($locale === null) {
            return null;
        }

        $locale = strtolower(trim($locale));
        if ($locale === '') {
            return null;
        }

        $base = explode('-', $locale)[0];

        return $base !== '' ? $base : null;
    }

    private function maskPagePath(?string $pageUrl): ?string
    {
        if ($pageUrl === null || trim($pageUrl) === '') {
            return null;
        }

        $parts = parse_url(trim($pageUrl));
        if ($parts === false) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');
        if ($path === '') {
            return null;
        }

        $path = preg_replace('#/order/\d+#i', '/order/…', $path) ?? $path;
        $path = preg_replace('#/\d{8,}(?=/|$)#', '/…', $path) ?? $path;

        if (strlen($path) > 120) {
            $path = mb_substr($path, 0, 119, 'UTF-8').'…';
        }

        return $path;
    }
}
