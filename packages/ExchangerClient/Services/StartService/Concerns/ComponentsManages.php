<?php

namespace iEXPackages\ExchangerClient\Services\StartService\Concerns;


use App\Http\Resources\ReviewsResponses;
use App\Services\CacheService;
use iEXPackages\ExchangerClient\Http\Resources\Content\NewsHomepageCardResources;
use iEXPackages\ExchangerClient\Http\Resources\Started\AdvantageResources;
use iEXPackages\ExchangerClient\Http\Resources\Started\BannerResources;
use iEXPackages\ExchangerClient\Http\Resources\Started\ContactResources;
use iEXPackages\ExchangerClient\Http\Resources\Started\FooterResources;
use iEXPackages\ExchangerClient\Http\Resources\Started\LastExchangesResources;
use iEXPackages\ExchangerClient\Http\Resources\Started\MenuResources;
use iEXPackages\ExchangerClient\Http\Resources\Started\NotificationResources;
use iEXPackages\ExchangerClient\Http\Resources\Started\PartnerResources;
use iEXPackages\ExchangerClient\Http\Resources\Started\PopularExchangeResources;
use iEXPackages\ExchangerClient\Http\Resources\Started\StatisticsResources;
use App\Models\Advantage;
use App\Models\Banner;
use App\Models\Contact;
use App\Models\DirectionExchange;
use App\Models\InfoStatistic;
use App\Models\LinksFooterGroup;
use App\Models\Menu;
use App\Models\News;
use App\Models\NoticeExchange;
use App\Models\Partner;
use App\Models\Review;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait ComponentsManages
{

    public function componentBuild(): array
    {
        return [
            'contacts' => $this->contacts(),
            'menu' => $this->menu(),
            'partners' => $this->partners(),
            'footers' => $this->footer(),
        ];
    }

    public function componentBuildExchange(): array
    {
        // Общее время кеширования для компонентов в минутах
        $cacheMinutes = (int) iEXSetting('clear_app_cache_other_timeout', 30);
        $cacheService = app(CacheService::class);
        $isGlobalCacheEnabled = (int) iEXSetting('is_enable_app_cache');

        return [
            'advantage' => $isGlobalCacheEnabled && (int)iEXSetting('enable_cache_advantages') === 1
                ? $cacheService->remember('component:advantage', fn() => $this->advantage(), ['components', 'advantage'], $cacheMinutes)
                : $this->advantage(),

            'last_news' => $isGlobalCacheEnabled && (int)iEXSetting('enable_cache_news') === 1
                ? $cacheService->remember('component:last_news:v2', fn() => $this->lastNews(), ['components', 'last_news'], $cacheMinutes)
                : $this->lastNews(),

            'statistics' => $isGlobalCacheEnabled && (int)iEXSetting('enable_cache_statistics') === 1
                ? $cacheService->remember('component:statistics', fn() => $this->statistics(), ['components', 'statistics'], $cacheMinutes)
                : $this->statistics(),

            'last_reviews' => $isGlobalCacheEnabled && (int)iEXSetting('enable_cache_reviews_exchange') === 1
                ? $cacheService->remember('component:last_reviews', fn() => $this->lastReviews(), ['components', 'last_reviews'], $cacheMinutes)
                : $this->lastReviews(),

            'popular_exchanges' => $isGlobalCacheEnabled && (int)iEXSetting('enable_cache_popular_exchange') === 1
                ? $cacheService->remember('component:popular_exchanges', fn() => $this->popularExchanges(), ['components', 'popular_exchanges'], $cacheMinutes)
                : $this->popularExchanges(),

            'last_exchanges' => $this->lastExchanges(),

            'banners' => $isGlobalCacheEnabled && (int)iEXSetting('enable_cache_banners') === 1
                ? $cacheService->remember('component:banners', fn() => $this->banners(), ['components', 'banners'], $cacheMinutes)
                : $this->banners(),

            'notifications' => $isGlobalCacheEnabled && (int)iEXSetting('enable_cache_notification') === 1
                ? $cacheService->remember('component:notifications', fn() => $this->notifications(), ['components', 'notifications'], $cacheMinutes)
                : $this->notifications(),
        ];
    }

    private function contacts(): ContactResources
    {
        $response = Contact::query()
            ->where('contacts.is_home', 1)
            ->where('contacts.status', 1)
            ->whereHas('contact_group', function ($q) {
                $q->where('status', 1);
            })
            ->orderBy('contacts.sorting')
            ->get();
        return new ContactResources($response);
    }

    private function menu(): MenuResources
    {
        $response = Menu::query()
            ->with([
                'children' => function ($q) {
                    $q->where('status', 1)
                        ->orderBy('sorting');
                },
            ])
            ->where('parent_id', 0)
            ->where('status', 1)
            ->orderBy('sorting')
            ->get();

        return new MenuResources($response);
    }

    private function footer(): FooterResources | null
    {
        if ((int)iEXSetting('is_enable_footer') === 0) {
            return null;
        }

        $response = LinksFooterGroup::with(['links' => function ($q) {
            $q->select('name', 'url', 'is_blank', 'sorting', 'id_group')->orderBy('sorting');
        }])->select('id', 'name', 'sorting')->get();

        return new FooterResources($response);
    }

    private function notifications(): NotificationResources | null
    {
        $response = NoticeExchange::where('status', 1)
            ->orderBy('sorting')
            ->get()
            ->filter(function ($notice) {
                if ($notice->is_enabled_schedule == 0) {
                    return true;
                }

                // Если расписание активно, проверяем интервал, учитывая переход через полночь
                $now = Carbon::now()->format('H:i');
                $from = $notice->from_time;
                $to = $notice->to_time;

                if ($from < $to) {
                    return $now >= $from && $now <= $to;
                } else {
                    return ($now >= $from) || ($now <= $to);
                }
            });

        return new NotificationResources($response);
    }

    private function partners(): PartnerResources | null
    {
        if ((int)iEXSetting('visible_partners') === 0) {
            return null;
        }

        $response = Partner::orderBy('sorting')->get();
        return new PartnerResources($response);
    }

    private function advantage(): AdvantageResources | null
    {
        if ((int)iEXSetting('visible_advantage') === 0) {
            return null;
        }

        $response = Advantage::where('status', '=', 1)->orderBy('sorting')->get();
        return new AdvantageResources($response);
    }

    private function lastNews(): NewsHomepageCardResources | null
    {
        if ((int)iEXSetting('visible_news') === 0) {
            return null;
        }

        $news =  News::orderByDesc('id')
            ->limit((int) iEXSetting('count_news_exchange', 10))
            ->get();

        return new NewsHomepageCardResources($news);
    }

    private function statistics(): StatisticsResources | null
    {
        if ((int)iEXSetting('visible_statistics') === 0) {
            return null;
        }

        $response = InfoStatistic::where('status', '=', 1)->orderBy('sorting')->get();
        return new StatisticsResources($response);
    }

    private function lastReviews(): ReviewsResponses | null
    {
        if ((int)iEXSetting('visible_reviews') === 0) {
            return null;
        }

        $response = Review::with(['tasks' => function ($q) {
            $q->select('id', 'public_id', 'id_direction_exchange', 'give_price', 'receiving_price');
        }, 'tasks.direction_exchange' => function ($q) {
            $q->select('id', 'id_currency1', 'id_currency2');
        }, 'tasks.direction_exchange.currency1' => function ($q) {
            $q->select('id', 'id_payment', 'id_code_currency', 'number_format');
        }, 'tasks.direction_exchange.currency2' => function ($q) {
            $q->select('id', 'id_payment', 'id_code_currency', 'number_format');
        }, 'tasks.direction_exchange.currency1.payment' => function ($q) {
            $q->select('id', 'name', 'logo');
        }, 'tasks.direction_exchange.currency2.payment' => function ($q) {
            $q->select('id', 'name', 'logo');
        }, 'tasks.direction_exchange.currency1.code_currency' => function ($q) {
            $q->select('id', 'name');
        }, 'tasks.direction_exchange.currency2.code_currency' => function ($q) {
            $q->select('id', 'name');
        }])->where('version', '=', 1)->where('status', '=', 1)->orderByDesc('id')->limit(
            (int) iEXSetting('count_reviews_exchange', 20)
        )->get();

        return new ReviewsResponses($response);
    }

    private function popularExchanges(): PopularExchangeResources | null
    {
        if ((int)iEXSetting('visible_popular_exchange') === 0) {
            return null;
        }

        $now = now();
        $currentPeriodStart = $now->copy()->startOfMonth();
        $prevPeriodStart = $now->copy()->subMonth()->startOfMonth();
        $prevPeriodEnd = $now->copy()->subMonth()->endOfMonth();

        // Текущий период
        $currentOrders = Task::where('status', 4)
            ->whereBetween('created_at', [$currentPeriodStart, $now])
            ->get();
        $totalCurrentOrders = $currentOrders->count();

        $currentStats = $currentOrders->groupBy('id_direction_exchange')->map(function($orders) use ($totalCurrentOrders) {
            $count = $orders->count();
            return [
                'orders_count' => $count,
                'orders_percent' => $totalCurrentOrders > 0 ? round($count / $totalCurrentOrders * 100, 2) : 0,
            ];
        });

        // Предыдущий период
        $prevOrders = Task::where('status', 4)
            ->whereBetween('created_at', [$prevPeriodStart, $prevPeriodEnd])
            ->get();
        $totalPrevOrders = $prevOrders->count();

        $prevStats = $prevOrders->groupBy('id_direction_exchange')->map(function($orders) use ($totalPrevOrders) {
            $count = $orders->count();
            return [
                'orders_count' => $count,
                'orders_percent' => $totalPrevOrders > 0 ? round($count / $totalPrevOrders * 100, 2) : 0,
            ];
        });

        // Собираем топ-10 направлений по текущему периоду
        $topDirectionIds = $currentStats->sortByDesc('orders_count')->keys()->take(iEXSetting('count_popular_exchange', 10))->toArray();

        $directions = DirectionExchange::with([
            'currency1.payment', 'currency1.code_currency',
            'currency2.payment', 'currency2.code_currency'
        ])
            ->whereIn('id', $topDirectionIds)
            ->get()
            ->map(function($direction) use ($currentStats, $prevStats) {
                $cur = $currentStats[$direction->id] ?? ['orders_count' => 0, 'orders_percent' => 0];
                $prev = $prevStats[$direction->id] ?? ['orders_count' => 0, 'orders_percent' => 0];
                $direction->orders_count = $cur['orders_count'];
                $direction->orders_percent = $cur['orders_percent'];
                $direction->orders_percent_delta = round($cur['orders_percent'] - $prev['orders_percent'], 2);
                return $direction;
            });

        return new PopularExchangeResources($directions);
    }

    private function lastExchanges(): LastExchangesResources | null
    {
        if ((int)iEXSetting('visible_last_exchange') === 0) {
            return null;
        }

        //last items
        $lastItems = Task::with([
            'task_info' => function ($q) {
                $q->select('id', 'id_task', 'city_name', 'direction_city_id')
                    ->with('directionCity.city');
            },
            'direction_exchange' => function ($q) {
                $q->select('id', 'id_currency1', 'id_currency2');
            }, 'direction_exchange.currency1' => function ($q) {
                $q->select('id', 'number_format', 'id_payment', 'id_code_currency');
            }, 'direction_exchange.currency1.payment' => function ($q) {
                $q->select('id', 'name', 'enable_svg', 'logo');
            }, 'direction_exchange.currency1.code_currency' => function ($q) {
                $q->select('id', 'name');
            }, 'direction_exchange.currency2' => function ($q) {
                $q->select('id', 'number_format', 'id_payment', 'id_code_currency');
            }, 'direction_exchange.currency2.payment' => function ($q) {
                $q->select('id', 'name', 'enable_svg', 'logo');
            }, 'direction_exchange.currency2.code_currency' => function ($q) {
                $q->select('id', 'name');
            }])->select('id', 'id_direction_exchange', 'status', 'started_at', 'give_price', 'receiving_price', 'updated_at', 'created_at')->where('status', '=', 4)->orderByDesc('id')
            ->limit(iEXSetting('count_last_exchange'))->get();

        return new LastExchangesResources($lastItems);
    }


    private function banners(): BannerResources | null
    {
        if ((int)iEXSetting('visible_banner') === 0) {
            return null;
        }

        $response = Banner::with('buttons')
            ->where('status', '=', 1)->orderBy('sorting')
            ->get();

        return new BannerResources($response);
    }
}
