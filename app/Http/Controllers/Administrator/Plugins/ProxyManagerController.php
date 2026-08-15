<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Plugins;

use App\Http\Controllers\Controller;
use iEXPackages\DynamicConfig\Models\DynamicConfigSetting;
use iEXPackages\Proxy\DTO\ProxyContext;
use iEXPackages\Proxy\Facades\ProxyFacade;
use iEXPackages\Proxy\Models\Proxy;
use iEXPackages\Proxy\Models\ProxyHealthLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class ProxyManagerController extends Controller
{
    /**
     * Список прокси (пагинация).
     *
     * Важно:
     * - password НЕ отдаём (только has_password)
     * - дополнительно отдаём auto_disabled_until / is_auto_disabled (для UX)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(200, (int) $request->input('per_page', 50)));

        $paginator = Proxy::query()
            ->orderByDesc('id')
            ->paginate($perPage);

        $now = now();

        $data = collect($paginator->items())->map(static function (Proxy $item) use ($now): array {
            $autoUntil = $item->auto_disabled_until;
            $isAutoDisabled = $autoUntil !== null && $autoUntil->isFuture();

            return [
                'id' => (int) $item->id,
                'attributes' => [
                    'host' => (string) $item->host,
                    'port' => (int) $item->port,
                    'login' => $item->login !== null ? (string) $item->login : null,

                    // пароль НЕ отдаём
                    'has_password' => trim((string) $item->password) !== '',

                    'type' => (string) $item->type,

                    // manual enable/disable
                    'status' => (bool) $item->status,

                    // health
                    'fail_count' => (int) $item->fail_count,
                    'last_checked_at' => $item->last_checked_at?->format('Y-m-d H:i:s'),

                    // auto cooldown
                    'auto_disabled_until' => $autoUntil?->toIso8601String(),
                    'is_auto_disabled' => $isAutoDisabled,

                    'created_at' => $item->created_at?->toIso8601String(),
                    'updated_at' => $item->updated_at?->toIso8601String(),
                ],
            ];
        })->values();

        return response()->json([
            'status' => 0,
            'data' => $data,
            'meta' => [
                'total' => (int) $paginator->total(),
                'per_page' => (int) $paginator->perPage(),
                'current_page' => (int) $paginator->currentPage(),
                'last_page' => (int) $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Добавление прокси.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'login' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:http,https,socks4,socks5'],
            'status' => ['required', 'boolean'],
        ]);

        $payload = [
            'host' => trim((string) $validated['host']),
            'port' => (int) $validated['port'],
            'login' => isset($validated['login']) ? trim((string) $validated['login']) : null,
            'password' => isset($validated['password']) ? (string) $validated['password'] : null,
            'type' => strtolower(trim((string) $validated['type'])),

            // manual
            'status' => (bool) $validated['status'],

            // health defaults
            'fail_count' => 0,
            'last_checked_at' => null,

            // cooldown (auto)
            'auto_disabled_until' => null,
        ];

        $proxy = Proxy::create($payload);

        return response()->json([
            'status' => 0,
            'message' => __('Proxy успешно добавлен'),
            'id' => (int) $proxy->id,
        ]);
    }

    /**
     * Форма редактирования прокси.
     *
     * Важно:
     * - password не возвращаем (только has_password)
     */
    public function edit(int $id): JsonResponse
    {
        /** @var Proxy $item */
        $item = Proxy::query()->findOrFail($id);

        return response()->json([
            'status' => 0,
            'id' => (int) $item->id,
            'attributes' => [
                'host' => (string) $item->host,
                'port' => (int) $item->port,
                'login' => $item->login !== null ? (string) $item->login : null,
                'has_password' => trim((string) $item->password) !== '',
                'type' => (string) $item->type,

                // manual
                'status' => (bool) $item->status,

                // health
                'last_checked_at' => $item->last_checked_at?->format('Y-m-d H:i:s'),
                'fail_count' => (int) $item->fail_count,

                // auto cooldown
                'auto_disabled_until' => $item->auto_disabled_until?->toIso8601String(),
                'is_auto_disabled' => $item->auto_disabled_until !== null && $item->auto_disabled_until->isFuture(),
            ],
        ]);
    }

    /**
     * Обновление прокси.
     *
     * Правила:
     * - если password не передан или пустой — оставляем старый
     * - login можно очистить (передать пустую строку -> null)
     * - manual status меняется только руками админа (поле status)
     */
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var Proxy $item */
        $item = Proxy::query()->findOrFail($id);

        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'login' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:http,https,socks4,socks5'],
            'status' => ['required', 'boolean'],
        ]);

        $update = [
            'host' => trim((string) $validated['host']),
            'port' => (int) $validated['port'],
            'type' => strtolower(trim((string) $validated['type'])),
            'status' => (bool) $validated['status'],
        ];

        // login: пустая строка -> null
        $login = array_key_exists('login', $validated) ? trim((string) $validated['login']) : null;
        $update['login'] = ($login === '') ? null : $login;

        // password: если пустой -> не трогаем
        if (array_key_exists('password', $validated)) {
            $pwd = (string) $validated['password'];
            if (trim($pwd) !== '') {
                $update['password'] = $pwd;
            }
        }

        $item->update($update);

        return response()->json([
            'status' => 0,
            'message' => __('Proxy успешно обновлен'),
        ]);
    }

    /**
     * Сбросить fail_count + снять auto cooldown.
     *
     * Важно:
     * - НЕ включаем прокси вручную через status (manual) без явного действия админа,
     *   поэтому status тут не трогаем.
     */
    public function resetFailCount(int $id): JsonResponse
    {
        /** @var Proxy $item */
        $item = Proxy::query()->findOrFail($id);

        $item->update([
            'fail_count' => 0,
            'auto_disabled_until' => null,
            'last_checked_at' => now(),
        ]);

        return response()->json([
            'status' => 0,
            'message' => __('Статус прокси обновлен: fail_count сброшен, cooldown снят'),
        ]);
    }

    /**
     * Удаление прокси.
     *
     * Важно:
     * - нельзя удалить, если прокси где-то используется
     */
    public function destroy(int $id): JsonResponse
    {
        /** @var Proxy $item */
        $item = Proxy::query()->findOrFail($id);

        $usage = $this->detectProxyUsage($id);
        if ($usage['is_used']) {
            return response()->json([
                'status' => 1,
                'message' => __('Нельзя удалить прокси: она используется в настройках/источниках.'),
                'details' => $usage,
            ]);
        }

        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => __('Proxy успешно удален'),
        ]);
    }

    /**
     * Используется ли прокси в системе.
     *
     * @return array{is_used:bool, usages:array<int,array{source:string,count:int}>}
     */
    private function detectProxyUsage(int $proxyId): array
    {
        $usages = [];

        // 1) Группы парсеров
        $cntParsers = (int) DB::table('group_parser_exchange')
            ->where('proxy_id', $proxyId)
            ->count();

        if ($cntParsers > 0) {
            $usages[] = ['source' => 'group_parser_exchange.proxy_id', 'count' => $cntParsers];
        }

        // 2) Мерчанты/шлюзы
        $cntGateways = (int) DB::table('gateways_payments')
            ->where('id_proxy', $proxyId)
            ->count();

        if ($cntGateways > 0) {
            $usages[] = ['source' => 'gateways_payments.id_proxy', 'count' => $cntGateways];
        }

        // 3) BestChange DynamicConfig (через модель, без хардкода таблицы)
        $cntBestchange = (int) DynamicConfigSetting::query()
            ->where('scope_type', 'plugins')
            ->whereNull('scope_id')
            ->where('key', 'bestchange.proxy_id')
            ->where('value', (string) $proxyId)
            ->count();

        if ($cntBestchange > 0) {
            $usages[] = ['source' => 'DynamicConfig bestchange.proxy_id', 'count' => $cntBestchange];
        }

        return [
            'is_used' => $usages !== [],
            'usages' => $usages,
        ];
    }

    /**
     * Ручная проверка прокси (ping/test).
     *
     * URL: POST /plugins/proxy-manager/{id}/test
     */
    public function test(int $id): JsonResponse
    {
        /** @var Proxy $proxy */
        $proxy = Proxy::query()->findOrFail($id);

        $testUrl = 'https://api.ipify.org';

        // Контекст ручной проверки
        $ctx = new ProxyContext('manual_test', null, 'test');

        $startedAt = microtime(true);

        try {
            $http = ProxyFacade::applyToHttp(
                Http::timeout(8)->acceptJson(),
                (int) $proxy->id,
                $ctx
            );

            $response = $http->get($testUrl);

            $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);

            ProxyFacade::markResultById(
                (int) $proxy->id,
                $ctx,
                $response->successful(),
                $response->status(),
                null,
                $latencyMs
            );

            return response()->json([
                'status' => $response->successful() ? 0 : 1,
                'message' => $response->successful()
                    ? __('Прокси работает. Проверка выполнена успешно.')
                    : __('Прокси не прошла проверку.'),
                'data' => [
                    'proxy_id' => (int) $proxy->id,
                    'latency_ms' => $latencyMs,
                    'http_status' => $response->status(),
                    'ip' => trim((string) $response->body()),
                    'url' => $testUrl,
                ],
            ]);

        } catch (ConnectionException $e) {
            $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);

            ProxyFacade::markResultById(
                (int) $proxy->id,
                $ctx,
                false,
                null,
                $e,
                $latencyMs
            );

            return response()->json([
                'status' => 1,
                'message' => __('Ошибка соединения при проверке прокси.'),
                'data' => [
                    'proxy_id' => (int) $proxy->id,
                    'latency_ms' => $latencyMs,
                    'error' => $e->getMessage(),
                    'url' => $testUrl,
                ],
            ], 200);

        } catch (\Throwable $e) {
            $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);

            ProxyFacade::markResultById(
                (int) $proxy->id,
                $ctx,
                false,
                null,
                $e,
                $latencyMs
            );

            return response()->json([
                'status' => 1,
                'message' => __('Не удалось выполнить проверку прокси.'),
                'data' => [
                    'proxy_id' => (int) $proxy->id,
                    'latency_ms' => $latencyMs,
                    'error' => $e->getMessage(),
                    'url' => $testUrl,
                ],
            ], 200);
        }
    }

    /**
     * История здоровья прокси.
     */
    public function history(Request $request, int $id): JsonResponse
    {
        Proxy::query()->findOrFail($id);

        $perPage = max(1, min(200, (int) $request->input('per_page', 50)));

        $q = ProxyHealthLog::query()->where('proxy_id', $id);

        if ($request->filled('context')) {
            $q->where('context', (string) $request->input('context'));
        }

        if ($request->has('success')) {
            $q->where('success', (bool) $request->boolean('success'));
        }

        $p = $q->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'status' => 0,
            'data' => collect($p->items())->map(static function (ProxyHealthLog $row): array {
                return [
                    'id' => (int) $row->id,
                    'attributes' => [
                        'context' => (string) $row->context,
                        'success' => (bool) $row->success,
                        'http_status' => $row->http_status !== null ? (int) $row->http_status : null,
                        'latency_ms' => $row->latency_ms !== null ? (int) $row->latency_ms : null,
                        'error' => $row->error !== null ? (string) $row->error : null,
                        'created_at' => $row->created_at?->toIso8601String(),
                    ],
                ];
            })->values(),
            'meta' => [
                'total' => (int) $p->total(),
                'per_page' => (int) $p->perPage(),
                'current_page' => (int) $p->currentPage(),
                'last_page' => (int) $p->lastPage(),
            ],
        ]);
    }

    /**
     * Статистика по часам (последние N часов).
     */
    public function stats(Request $request, int $id): JsonResponse
    {
        Proxy::query()->findOrFail($id);

        $hours = max(1, min(168, (int) $request->input('hours', 24)));
        $from = now()->subHours($hours);

        $rows = ProxyHealthLog::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as bucket")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN success=1 THEN 1 ELSE 0 END) as ok')
            ->selectRaw('AVG(latency_ms) as avg_latency')
            ->where('proxy_id', $id)
            ->where('created_at', '>=', $from)
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->map(static function ($row): array {
                $total = (int) ($row->total ?? 0);
                $ok = (int) ($row->ok ?? 0);

                return [
                    'bucket' => (string) ($row->bucket ?? ''),
                    'total' => $total,
                    'ok' => $ok,
                    'success_rate' => $total > 0 ? round(($ok / $total) * 100, 2) : 0.0,
                    'avg_latency' => $row->avg_latency !== null ? (float) $row->avg_latency : null,
                ];
            })
            ->values();

        return response()->json([
            'status' => 0,
            'data' => $rows,
        ]);
    }
}
