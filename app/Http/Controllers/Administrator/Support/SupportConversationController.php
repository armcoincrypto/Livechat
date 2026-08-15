<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use iEXPackages\SupportChat\Services\SupportChatSchemaReadinessService;
use iEXPackages\SupportChat\Services\SupportTelegramDeliveryStatusService;
use iEXPackages\SupportChat\Services\SupportVisitorContextService;
use iEXPackages\SupportChat\Services\SupportConversationLifecycleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class SupportConversationController extends Controller
{
    public function index(Request $request): View
    {
        $query = SupportConversation::query();

        $status = (string) $request->query('status', '');
        if ($status !== '' && $status !== 'all') {
            $allowed = [
                SupportConversation::STATUS_WAITING_OPERATOR,
                SupportConversation::STATUS_WAITING_VISITOR,
                SupportConversation::STATUS_CLOSED,
                SupportConversation::STATUS_OPEN,
                'open',
            ];
            if (in_array($status, $allowed, true)) {
                $query->where('status', $status);
            }
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('visitor_email', 'like', '%'.$search.'%')
                    ->orWhere('visitor_name', 'like', '%'.$search.'%');

                if (preg_match('/^s-\d+$/i', $search) === 1) {
                    $q->orWhereRaw('LOWER(public_support_id) = ?', [mb_strtolower($search)]);
                } else {
                    $q->orWhere('public_support_id', 'like', '%'.$search.'%');
                }

                if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $search) === 1) {
                    $q->orWhere('uuid', $search);
                } else {
                    $q->orWhere('uuid', 'like', '%'.$search.'%');
                }
            });
        }

        $op = SupportConversation::STATUS_WAITING_OPERATOR;
        $opLegacy = SupportConversation::STATUS_OPEN;
        $vis = SupportConversation::STATUS_WAITING_VISITOR;
        $closed = SupportConversation::STATUS_CLOSED;

        $sort = (string) $request->query('sort', 'waiting');
        if (! in_array($sort, ['waiting', 'recent'], true)) {
            $sort = 'waiting';
        }

        if ($sort === 'waiting') {
            $closedStatus = SupportConversation::STATUS_CLOSED;
            $query->orderByRaw(
                'CASE WHEN (`status` != ? AND `last_visitor_message_at` IS NOT NULL '
                    .'AND (`last_operator_message_at` IS NULL OR `last_visitor_message_at` > `last_operator_message_at`)) '
                    .'THEN 0 ELSE 1 END ASC, '
                    .'CASE WHEN (`status` != ? AND `last_visitor_message_at` IS NOT NULL '
                    .'AND (`last_operator_message_at` IS NULL OR `last_visitor_message_at` > `last_operator_message_at`)) '
                    .'THEN `last_visitor_message_at` END ASC, '
                    .'COALESCE(`last_message_at`, `updated_at`, `created_at`) DESC',
                [$closedStatus, $closedStatus]
            );
        } else {
            $query->orderByRaw(
                'CASE '
                    .'WHEN `status` IN (?, ?) THEN 0 '
                    .'WHEN `status` = ? THEN 1 '
                    .'WHEN `status` = ? THEN 2 '
                    .'ELSE 3 END, '
                    .'COALESCE(last_message_at, updated_at, created_at) DESC',
                [$op, $opLegacy, $vis, $closed]
            );
        }

        /** @var LengthAwarePaginator<int, SupportConversation> $conversations */
        $conversations = $query->paginate(25)->withQueryString();

        $statsRow = SupportConversation::query()->selectRaw(
            'SUM(CASE WHEN `status` IN (?, ?) THEN 1 ELSE 0 END) AS waiting_operator, '
            .'SUM(CASE WHEN `status` = ? THEN 1 ELSE 0 END) AS waiting_visitor, '
            .'SUM(CASE WHEN `status` = ? THEN 1 ELSE 0 END) AS closed, '
            .'COUNT(*) AS total',
            [$op, $opLegacy, $vis, $closed]
        )->first();

        $waitStatsRow = SupportConversation::query()
            ->unansweredWaiting()
            ->selectRaw(
                'COUNT(*) AS waiting_now, '
                .'SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, last_visitor_message_at, NOW()) >= 15 THEN 1 ELSE 0 END) AS over_15, '
                .'SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, last_visitor_message_at, NOW()) >= 30 THEN 1 ELSE 0 END) AS over_30, '
                .'SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, last_visitor_message_at, NOW()) >= 60 THEN 1 ELSE 0 END) AS over_60'
            )
            ->first();

        return view('admin.support.conversations.index', [
            'conversations' => $conversations,
            'filters' => [
                'q' => $search,
                'status' => $status === '' ? 'all' : $status,
                'sort' => $sort,
            ],
            'status_counts' => [
                'waiting_operator' => (int) ($statsRow->waiting_operator ?? 0),
                'waiting_visitor' => (int) ($statsRow->waiting_visitor ?? 0),
                'closed' => (int) ($statsRow->closed ?? 0),
                'total' => (int) ($statsRow->total ?? 0),
            ],
            'wait_summary' => [
                'waiting_now' => (int) ($waitStatsRow->waiting_now ?? 0),
                'over_15' => (int) ($waitStatsRow->over_15 ?? 0),
                'over_30' => (int) ($waitStatsRow->over_30 ?? 0),
                'over_60' => (int) ($waitStatsRow->over_60 ?? 0),
            ],
        ]);
    }

    public function show(
        SupportConversation $conversation,
        SupportChatSchemaReadinessService $schemaReadiness,
        SupportTelegramDeliveryStatusService $telegramDeliveryStatus,
        SupportVisitorContextService $visitorContext,
    ): View {
        $conversation->load(['messages' => function ($q): void {
            $q->orderBy('id');
        }]);

        $deliveryBadges = [];
        foreach ($conversation->messages as $msg) {
            $deliveryBadges[$msg->id] = $telegramDeliveryStatus->presentation($msg);
        }

        return view('admin.support.conversations.show', [
            'conversation' => $conversation,
            'supportChatDiagnosticsAvailable' => $schemaReadiness->isDiagnosticsAvailable(),
            'visitorContext' => $visitorContext->resolve($conversation),
            'deliveryBadges' => $deliveryBadges,
        ]);
    }

    public function close(Request $request, SupportConversation $conversation, SupportConversationLifecycleService $lifecycle): RedirectResponse
    {
        if ($conversation->isClosed()) {
            return redirect()
                ->route('admin.support-conversations.show', $conversation)
                ->with('status', 'Already closed.');
        }

        $lifecycle->closeByOperator($conversation, 'admin');

        return redirect()
            ->route('admin.support-conversations.show', $conversation->fresh())
            ->with('status', 'Conversation closed.');
    }

    public function reopen(Request $request, SupportConversation $conversation, SupportConversationLifecycleService $lifecycle): RedirectResponse
    {
        if (! $conversation->isClosed()) {
            return redirect()
                ->route('admin.support-conversations.show', $conversation)
                ->with('status', 'Conversation is not closed.');
        }

        $waiting = (string) $request->input('waiting', 'operator');
        if (! in_array(strtolower($waiting), ['operator', 'visitor'], true)) {
            $waiting = 'operator';
        }

        $lifecycle->reopenByOperator($conversation, $waiting, 'admin');

        return redirect()
            ->route('admin.support-conversations.show', $conversation->fresh())
            ->with('status', 'Conversation reopened.');
    }
}
