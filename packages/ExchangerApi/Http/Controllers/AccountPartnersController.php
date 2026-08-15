<?php

namespace iEXPackages\ExchangerApi\Http\Controllers;

use App\Http\Resources\PartnerWithdrawalResponses;
use App\Models\CodeCurrency;
use App\Models\WithdrawalRequest;
use App\Models\Task;
use Carbon\Carbon;
use iEXPackages\ExchangerApi\Http\Resources\AccountPartner\ReferralExchangesResources;
use iEXPackages\ReferralSystem\Models\ReferralLink;
use iEXPackages\ReferralSystem\Models\ReferralLog;
use iEXPackages\ReferralSystem\Models\ReferralRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AccountPartnersController extends AbstractAPIController
{
    /**
     * Информация о партнёрской программе для текущего пользователя.
     *
     * @return JsonResponse
     */
    public function getPartnerInfo(Request $request): JsonResponse
    {
        $user = $request->user();

        // Подтягиваем баланс + валюту и реферальную ссылку с программой
        $user->loadMissing('user_balance.code_currency', 'referralLink.program');

        /** @var ReferralLink|null $referralLink */
        $referralLink = $user->referralLink;

        $userBalance  = $user->user_balance;
        $codeCurrency = CodeCurrency::find((int) iEXSetting('id_referral_code_currency'));

        // Значения по умолчанию для баланса и валюты
        $currentBalance          = (float) ($userBalance->balance ?? 0);
        $totalReferralProfit     = (float) ($userBalance->referral_total_profit ?? 0);
        $totalReferralWithdrawal = (float) ($userBalance->referral_total_withdrawal ?? 0);

        $currencyData = [
            'name'     => $codeCurrency->name ?? null
        ];

        // Если у пользователя ещё нет партнёрской программы — отдаём "пустой" ответ, но без падений,
        // но всё равно возвращаем данные по валюте и балансу.
        if (! $referralLink) {
            return Response::json([
                'type'       => 'referral',
                'attributes' => [
                    'percent_referral' => 0.0,
                    'count_referral'   => 0,
                    'iso_code'         => config('partners-bonus.name') ?? '',
                    'referral_hash'    => null,
                    'balance'          => $currentBalance,
                    'totals'           => [
                        'balance'    => $totalReferralProfit,
                        'withdrawal' => $totalReferralWithdrawal,
                    ],
                    'currency' => $currencyData,
                    'program'  => null,
                    'stats'    => [
                        'referrals' => [
                            'total'                 => 0,
                            'with_completed_orders' => 0,
                            'active_last_30_days'   => 0,
                        ],
                        'orders' => [
                            'completed_total'  => 0,
                            'last_completed_at' => null,
                        ],
                    ],
                ],
            ]);
        }

        // Получаем список id всех рефералов (для общей статистики по количеству)
        $relationshipsQuery = ReferralRelationship::where('referral_link_id', $referralLink->id);
        $referralCount = (int) $relationshipsQuery->count();
        $referralUserIds = $relationshipsQuery->pluck('user_id');

        $completedOrdersTotal    = 0;
        $lastCompletedAt         = null;
        $referralsWithOrders     = 0;
        $activeReferralsLast30   = 0;
        $firstCompletedAt        = null;

        // Статистика только по тем заявкам, где реально был начислен бонус (> 0),
        // и только по текущей реферальной ссылке/программе.
        $logsQuery = ReferralLog::where('id_referral_link', $referralLink->id)
            ->where('bonus_number', '>', 0);

        if ($logsQuery->exists()) {
            // Общее количество записей начислений (фактически — кол-во заявок с бонусом)
            $completedOrdersTotal = (int) (clone $logsQuery)->count();

            // Дата последней заявки с бонусом
            $lastCompletedAt = (clone $logsQuery)->max('created_at');

            // Дата первой заявки с бонусом
            $firstCompletedAt = (clone $logsQuery)->min('created_at');

            // Сколько рефералов вообще получили хотя бы один бонус
            $referralsWithOrders = (int) (clone $logsQuery)
                ->distinct('id_referral')
                ->count('id_referral');

            // Активные за последние 30 дней (есть бонусные заявки за период)
            $threshold = Carbon::now()->subDays(30);

            $activeReferralsLast30 = (int) (clone $logsQuery)
                ->where('created_at', '>=', $threshold)
                ->distinct('id_referral')
                ->count('id_referral');
        }

        // Дополнительная агрегированная статистика для партнёрки
        $avgProfitPerReferral = $referralCount > 0
            ? round($totalReferralProfit / $referralCount, 8)
            : 0.0;

        $program = $referralLink->program;

        $programData = $program ? [
            'name'             => $program->name,
            'title'            => $program->title,
            'description'      => $program->description,
            'percent'          => (float) $program->percent
        ] : null;

        return Response::json([
            'type'       => 'referral',
            'attributes' => [
                // Старые поля (для обратной совместимости)
                'percent_referral' => (float) ($program->percent ?? 0),
                'count_referral'   => $referralCount,
                'iso_code'         => $codeCurrency->name ?? '',
                'referral_hash'    => $referralLink->hash ?? $referralLink->getHashAttribute(),
                'balance'          => $currentBalance,
                'totals'           => [
                    'balance'    => $totalReferralProfit,
                    'withdrawal' => $totalReferralWithdrawal,
                ],

                'program'  => $programData,
                'stats'    => [
                    'referrals' => [
                        'total'                 => $referralCount,
                        'with_completed_orders' => $referralsWithOrders,
                        'active_last_30_days'   => $activeReferralsLast30,
                    ],
                    'orders' => [
                        'completed_total'   => $completedOrdersTotal,
                        'last_completed_at' => $lastCompletedAt,
                    ],
                    'earnings' => [
                        'total_profit'       => $totalReferralProfit,
                        'total_withdrawal'   => $totalReferralWithdrawal,
                        'available_balance'  => $currentBalance,
                        'avg_per_referral'   => $avgProfitPerReferral,
                    ],
                    'timeline' => [
                        'first_completed_order_at' => $firstCompletedAt,
                        'last_completed_order_at'  => $lastCompletedAt,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Партнёрские обмены (история начислений бонусов по рефералам).
     *
     * Поддерживает:
     *  - ?page=1
     *  - ?per_page=20 (по умолчанию 20, максимум 100)
     *  - ?from=YYYY-MM-DD (нижняя граница по дате начисления)
     *  - ?to=YYYY-MM-DD   (верхняя граница по дате начисления)
     *  - ?min_bonus=0.01  (минимальный бонус)
     *  - ?max_bonus=100   (максимальный бонус)
     */
    public function getPartnerExchanges(Request $request): ReferralExchangesResources
    {
        $user = $request->user();

        // Параметры пагинации
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;

        // Базовый запрос: все начисления реферальных бонусов текущему партнёру
        $baseQuery = ReferralLog::where('id_user', $user->id)
            ->where('bonus_number', '>', 0);

        // Фильтры по дате
        $from = $request->input('from');
        $to   = $request->input('to');

        if (!empty($from)) {
            $baseQuery->whereDate('created_at', '>=', $from);
        }

        if (!empty($to)) {
            $baseQuery->whereDate('created_at', '<=', $to);
        }

        // Фильтры по размеру бонуса
        $minBonus = $request->input('min_bonus');
        $maxBonus = $request->input('max_bonus');

        if ($minBonus !== null && $minBonus !== '') {
            $baseQuery->where('bonus_number', '>=', (float) $minBonus);
        }

        if ($maxBonus !== null && $maxBonus !== '') {
            $baseQuery->where('bonus_number', '<=', (float) $maxBonus);
        }

        // Статистика по этому же запросу (до пагинации)
        $statsQuery = clone $baseQuery;

        $totalExchanges   = (int) (clone $statsQuery)->count();
        $totalBonus       = (float) (clone $statsQuery)->sum('bonus_number');
        $firstExchangeAt  = (clone $statsQuery)->min('created_at');
        $lastExchangeAt   = (clone $statsQuery)->max('created_at');
        $uniqueReferrals  = (int) (clone $statsQuery)->distinct('id_referral')->count('id_referral');
        $avgBonus         = $totalExchanges > 0 ? round($totalBonus / $totalExchanges, 8) : 0.0;

        // Запрос для списка с подгрузкой связей
        $query = $baseQuery
            ->with([
                'tasks' => function ($q) {
                    $q->select('id', 'public_id', 'id_direction_exchange');
                },
                'user' => function ($q) {
                    $q->select('id', 'name', 'email');
                },
            ])
            ->orderByDesc('id_task');

        $paginator = $query->simplePaginate($perPage);

        // Возвращаем коллекцию + meta с краткой статистикой и применёнными фильтрами
        return (new ReferralExchangesResources($paginator))
            ->additional([
                'meta' => [
                    'summary' => [
                        'total_exchanges'  => $totalExchanges,
                        'total_bonus'      => $totalBonus,
                        'avg_bonus'        => $avgBonus,
                        'first_exchange_at'=> $firstExchangeAt,
                        'last_exchange_at' => $lastExchangeAt,
                        'unique_referrals' => $uniqueReferrals,
                        'user'             => [
                            'id'    => $user->id,
                            'name'  => $user->name,
                            'email' => $user->email,
                        ],
                    ],
                    'filters' => [
                        'from'      => $from,
                        'to'        => $to,
                        'min_bonus' => $minBonus,
                        'max_bonus' => $maxBonus,
                        'per_page'  => $perPage,
                    ],
                ],
            ]);
    }

    /**
     * Партнёрские выплаты (история запросов на вывод вознаграждений).
     *
     * Поддерживает:
     *  - ?page=1
     *  - ?per_page=20 (по умолчанию 20, максимум 100)
     *  - ?from=YYYY-MM-DD (нижняя граница по дате создания заявки)
     *  - ?to=YYYY-MM-DD   (верхняя граница по дате создания заявки)
     *  - ?status=paid|unpaid|all (фильтр по статусу выплаты)
     *  - ?min_amount=10   (минимальная сумма заявки)
     *  - ?max_amount=1000 (максимальная сумма заявки)
     *
     * @return PartnerWithdrawalResponses
     */
    public function getPartnerWithdrawal(Request $request): PartnerWithdrawalResponses
    {
        $user = $request->user();

        // Пагинация
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;

        // Базовый запрос по заявкам на вывод текущего пользователя
        $baseQuery = WithdrawalRequest::where('id_user', $user->id);

        // Фильтр по дате создания
        $from = $request->input('from');
        $to   = $request->input('to');

        if (! empty($from)) {
            $baseQuery->whereDate('created_at', '>=', $from);
        }

        if (! empty($to)) {
            $baseQuery->whereDate('created_at', '<=', $to);
        }

        // Фильтр по статусу выплаты
        // status: 0 = unpaid, 1 = paid
        $status = $request->input('status', 'all');

        if ($status === 'paid') {
            $baseQuery->where('status', 1);
        } elseif ($status === 'unpaid') {
            $baseQuery->where('status', 0);
        }

        // Фильтр по сумме (используем базовое поле balance_referral)
        $minAmount = $request->input('min_amount');
        $maxAmount = $request->input('max_amount');

        if ($minAmount !== null && $minAmount !== '') {
            $baseQuery->where('balance_referral', '>=', (float) $minAmount);
        }

        if ($maxAmount !== null && $maxAmount !== '') {
            $baseQuery->where('balance_referral', '<=', (float) $maxAmount);
        }

        // Статистика по этому же запросу (до пагинации)
        $statsQuery = clone $baseQuery;

        $totalRequests  = (int) (clone $statsQuery)->count();

        $totalPaid      = (int) (clone $statsQuery)->where('status', 1)->count();
        $totalUnpaid    = (int) (clone $statsQuery)->where('status', 0)->count();

        $sumPaid        = (float) (clone $statsQuery)->where('status', 1)->sum('view_balance_referral');
        $sumUnpaid      = (float) (clone $statsQuery)->where('status', 0)->sum('balance_referral');

        $firstRequestAt = (clone $statsQuery)->min('created_at');
        $lastRequestAt  = (clone $statsQuery)->max('created_at');

        // Основной запрос для списка (с сортировкой)
        $query = $baseQuery
            ->orderByDesc('id');

        $paginator = $query->simplePaginate($perPage);

        // Возвращаем коллекцию + meta с краткой статистикой и применёнными фильтрами
        return (new PartnerWithdrawalResponses($paginator))
            ->additional([
                'meta' => [
                    'summary' => [
                        'total_requests'  => $totalRequests,
                        'total_paid'      => $totalPaid,
                        'total_unpaid'    => $totalUnpaid,
                        'sum_paid'        => $sumPaid,
                        'sum_unpaid'      => $sumUnpaid,
                        'first_request_at'=> $firstRequestAt,
                        'last_request_at' => $lastRequestAt,
                        'user'            => [
                            'id'    => $user->id,
                            'name'  => $user->name,
                            'email' => $user->email,
                        ],
                    ],
                    'filters' => [
                        'from'       => $from,
                        'to'         => $to,
                        'status'     => $status,
                        'min_amount' => $minAmount,
                        'max_amount' => $maxAmount,
                        'per_page'   => $perPage,
                    ],
                ],
            ]);
    }
}
