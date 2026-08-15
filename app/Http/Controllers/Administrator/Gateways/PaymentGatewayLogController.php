<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Gateways;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use iEXPackages\Payments\Logging\Services\GatewayLogFormatter;
use iEXPackages\Payments\Logging\Services\GatewayLogReader;

final class PaymentGatewayLogController extends Controller
{
    public function task(
        int $taskId,
        Request $request,
        GatewayLogReader $reader,
        GatewayLogFormatter $formatter
    ): JsonResponse {
        return $this->respond(
            logs: $reader->forTask($taskId, (int) $request->input('per_page', 20)),
            formatter: $formatter
        );
    }

    public function merchant(
        int $merchantId,
        Request $request,
        GatewayLogReader $reader,
        GatewayLogFormatter $formatter
    ): JsonResponse {
        return $this->respond(
            logs: $reader->forMerchant($merchantId, (int) $request->input('per_page', 20)),
            formatter: $formatter
        );
    }

    public function payment(
        int $paymentId,
        Request $request,
        GatewayLogReader $reader,
        GatewayLogFormatter $formatter
    ): JsonResponse {
        return $this->respond(
            logs: $reader->forPayment($paymentId, (int) $request->input('per_page', 20)),
            formatter: $formatter
        );
    }

    public function merchants(
        Request $request,
        GatewayLogReader $reader,
        GatewayLogFormatter $formatter
    ): JsonResponse {
        if ($request->boolean('clear_logs')) {
            iex_assert_writable();

            $q = DB::table('payment_gateway_logs')->where('direction', 'incoming');

            if ($request->filled('merchant_id')) {
                $q->where('merchant_id', (int) $request->input('merchant_id'));
            }
            if ($request->filled('task_id')) {
                $q->where('task_id', (int) $request->input('task_id'));
            }

            $deleted = (int) $q->delete();

            return response()->json([
                'ok'      => true,
                'deleted' => $deleted,
                'message' => 'Логи приёма очищены.',
            ]);
        }

        $filters = $this->filtersFromRequest($request);

        $logs = $reader->merchantAll((int) $request->input('per_page', 20), $filters);

        $extra = [];
        if ($request->boolean('with_dictionaries')) {
            $extra['dictionaries'] = $this->getDictionaries('incoming');
        }

        return $this->respond($logs, $formatter, $extra);
    }

    public function payouts(
        Request $request,
        GatewayLogReader $reader,
        GatewayLogFormatter $formatter
    ): JsonResponse {
        if ($request->boolean('clear_logs')) {
            iex_assert_writable();

            $q = DB::table('payment_gateway_logs')->where('direction', 'outgoing');

            if ($request->filled('payment_id')) {
                $q->where('payment_id', (int) $request->input('payment_id'));
            }
            if ($request->filled('task_id')) {
                $q->where('task_id', (int) $request->input('task_id'));
            }

            $deleted = (int) $q->delete();

            return response()->json([
                'ok'      => true,
                'deleted' => $deleted,
                'message' => 'Логи выплат очищены.',
            ]);
        }

        $filters = $this->filtersFromRequest($request);

        $logs = $reader->payoutAll((int) $request->input('per_page', 20), $filters);

        $extra = [];
        if ($request->boolean('with_dictionaries')) {
            $extra['dictionaries'] = $this->getDictionaries('outgoing');
        }

        return $this->respond($logs, $formatter, $extra);
    }

    private function respond($logs, GatewayLogFormatter $formatter, array $extra = []): JsonResponse
    {
        $payload = [
            'data' => $logs->getCollection()
                ->map(fn ($log) => $formatter->format($log))
                ->values(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'total'        => $logs->total(),
                'per_page'     => $logs->perPage(),
            ],
        ];

        if ($extra !== []) {
            $payload = array_merge($payload, $extra);
        }

        return response()->json($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersFromRequest(Request $request): array
    {
        // сортировка: либо sort/order, либо useTableControls sorting_*
        $sort  = (string) $request->input('sort', '');
        $order = (string) $request->input('order', '');

        if ($sort === '' && $request->filled('sorting_order')) {
            $sort = (string) $request->input('sorting_order', '');
        }
        if ($order === '' && $request->filled('sorting_type')) {
            $order = (string) $request->input('sorting_type', '');
        }

        $filters = [
            'search' => (string) $request->input('search', ''),

            'taskId'     => $request->filled('task_id') ? (int) $request->input('task_id') : null,
            'merchantId' => $request->filled('merchant_id') ? (int) $request->input('merchant_id') : null,
            'paymentId'  => $request->filled('payment_id') ? (int) $request->input('payment_id') : null,

            // массивы допускаются
            'gatewayAlias' => $request->input('gateway_alias', ''),
            'direction'    => $request->input('direction', ''),
            'operation'    => $request->input('operation', ''),
            'status'       => $request->input('status', ''),

            'transactionId'  => (string) $request->input('transaction_id', ''),
            'externalId'     => (string) $request->input('external_id', ''),
            'idempotencyKey' => (string) $request->input('idempotency_key', ''),
            'replayKey'      => (string) $request->input('replay_key', ''),

            'responseStatus' => $request->has('response_status') ? $request->input('response_status') : null,
            'isSandbox'      => $request->has('is_sandbox') ? $request->input('is_sandbox') : null,

            'dateFrom' => (string) $request->input('date_from', ''),
            'dateTo'   => (string) $request->input('date_to', ''),

            'sort'  => $sort !== '' ? $sort : 'id',
            'order' => $order !== '' ? $order : 'desc',
        ];

        return array_filter($filters, static fn($v) => $v !== null && $v !== '');
    }

    /**
     * Возвращает справочники для фильтров (distinct-значения).
     *
     * Важно:
     * - Без отдельного роутинга: отдаём по флагу with_dictionaries=1 в существующих ответах.
     * - Кэшируем на короткое время, чтобы не нагружать БД.
     *
     * @return array<string, array<int, string|int>>
     */
    private function getDictionaries(string $direction): array
    {
        $direction = $direction === 'outgoing' ? 'outgoing' : 'incoming';

        $cacheKey = 'iex:gateway_logs:dictionaries:' . $direction;

        /** @var array<string, array<int, string|int>> $data */
        $data = Cache::remember($cacheKey, 60, function () use ($direction): array {
            $base = DB::table('payment_gateway_logs')->where('direction', $direction);

            $gatewayAliases = $base->clone()
                ->select('gateway_alias')
                ->distinct()
                ->orderBy('gateway_alias')
                ->pluck('gateway_alias')
                ->filter()
                ->values()
                ->all();

            $operations = $base->clone()
                ->select('operation')
                ->distinct()
                ->orderBy('operation')
                ->pluck('operation')
                ->filter()
                ->values()
                ->all();

            $statuses = $base->clone()
                ->whereNotNull('status')
                ->where('status', '<>', '')
                ->select('status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status')
                ->filter()
                ->values()
                ->all();

            $responseStatuses = $base->clone()
                ->whereNotNull('response_status')
                ->select('response_status')
                ->distinct()
                ->orderBy('response_status')
                ->pluck('response_status')
                ->filter()
                ->values()
                ->all();

            return [
                'gateway_alias'   => $gatewayAliases,
                'operation'       => $operations,
                'direction'       => [$direction],
                'status'          => $statuses,
                'response_status' => $responseStatuses,
            ];
        });

        return $data;
    }
}
