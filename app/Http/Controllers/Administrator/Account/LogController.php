<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Account;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use iEXPackages\AuthAudit\Models\AuthEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LogController extends Controller
{
    /**
     * Логи авторизаций (новая система аудита).
     *
     * Поведение:
     * - Если передан clear_logs=1: очищаем таблицу auth_audit_events (если не read-only режим).
     * - Иначе: возвращаем JSON со списком событий и meta пагинации.
     *
     * Поддерживаем фильтры (query params):
     * - user_id, email, ip, event, result, channel, guard
     * - country, city, iso_code, is_new_device
     * - session_id, session_prev_id
     * - request_id (по meta.request.request_id)
     * - date_from, date_to (по created_at)
     * - q (быстрый поиск: email/ip/country/city/event/result/channel/guard)
     * - per_page (1..100)
     */
    public function index(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->boolean('clear_logs')) {
            if ((bool) config('iexexchanger.is_reading_mode')) {
                return redirect()->back();
            }

            // delete быстрее и безопаснее для таблиц без FK; при необходимости можно заменить на truncate
            DB::table('auth_audit_events')->delete();

            return redirect()->back();
        }

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage < 1) {
            $perPage = 20;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $query = AuthEvent::query()->orderByDesc('id');
        $this->applyAuthEventFilters($request, $query);

        $paginator = $query->paginate($perPage);

        // Возвращаем предсказуемую структуру, чтобы фронт не зависел от внутренних деталей модели.
        $items = collect($paginator->items())->map(static function (AuthEvent $e): array {
            return [
                'id' => (int) $e->id,
                'user' => $e->user_id !== null ? [
                    'id' => (int) $e->user_id,
                    'email' => $e->email,
                    'name' => $e->user?->name ?? null,
                ] : null,
                'guard' => $e->guard,
                'channel' => $e->channel,
                'event' => $e->event,
                'result' => $e->result,
                'reason_code' => $e->reason_code,
                'message' => $e->message,
                'ip' => $e->ip,
                'ip_prev' => $e->ip_prev,
                'session_id' => $e->session_id ?? null,
                'session_prev_id' => $e->session_prev_id ?? null,
                'device_id' => $e->device_id,
                'is_new_device' => (bool) $e->is_new_device,
                'browser' => $e->browser,
                'os' => $e->os,
                'device' => $e->device,
                'country' => $e->country,
                'city' => $e->city,
                'iso_code' => $e->iso_code,
                'meta' => $e->meta,
                'created_at' => $e->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }


    /**
     * Применение фильтров к запросу по AuthEvent.
     */
    private function applyAuthEventFilters(Request $request, Builder $q): void
    {
        // Точные фильтры
        $this->whereIfNotEmpty($q, 'user_id', $request->query('user_id'));
        $this->whereIfNotEmpty($q, 'event', $request->query('event'));
        $this->whereIfNotEmpty($q, 'result', $request->query('result'));
        $this->whereIfNotEmpty($q, 'channel', $request->query('channel'));
        $this->whereIfNotEmpty($q, 'guard', $request->query('guard'));
        $this->whereIfNotEmpty($q, 'iso_code', $request->query('iso_code'));
        $this->whereIfNotEmpty($q, 'session_id', $request->query('session_id'));
        $this->whereIfNotEmpty($q, 'session_prev_id', $request->query('session_prev_id'));

        // Фильтры по строкам (LIKE)
        $this->likeIfNotEmpty($q, 'email', $request->query('email'));
        $this->likeIfNotEmpty($q, 'ip', $request->query('ip'));
        $this->likeIfNotEmpty($q, 'country', $request->query('country'));
        $this->likeIfNotEmpty($q, 'city', $request->query('city'));

        // Флаг нового устройства
        $isNewDevice = $request->query('is_new_device');
        if ($isNewDevice !== null && $isNewDevice !== '') {
            $q->where('is_new_device', (bool) (int) $isNewDevice);
        }

        // request_id в meta.request.request_id (JSON)
        $requestId = $request->query('request_id');
        if (is_string($requestId) && trim($requestId) !== '') {
            $q->where('meta->request->request_id', trim($requestId));
        }

        // Диапазон дат
        $dateFrom = $this->parseDate($request->query('date_from'));
        if ($dateFrom) {
            $q->where('created_at', '>=', $dateFrom);
        }

        $dateTo = $this->parseDate($request->query('date_to'));
        if ($dateTo) {
            $q->where('created_at', '<=', $dateTo);
        }

        // Быстрый поиск: одно поле q ищет по нескольким колонкам
        $qSearch = $request->query('q');
        if (is_string($qSearch) && trim($qSearch) !== '') {
            $needle = trim($qSearch);
            $q->where(function (Builder $sub) use ($needle) {
                $sub->where('email', 'like', "%{$needle}%")
                    ->orWhere('ip', 'like', "%{$needle}%")
                    ->orWhere('country', 'like', "%{$needle}%")
                    ->orWhere('city', 'like', "%{$needle}%")
                    ->orWhere('event', 'like', "%{$needle}%")
                    ->orWhere('result', 'like', "%{$needle}%")
                    ->orWhere('channel', 'like', "%{$needle}%")
                    ->orWhere('guard', 'like', "%{$needle}%");
            });
        }
    }

    /**
     * @param mixed $value
     */
    private function whereIfNotEmpty(Builder $q, string $field, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return;
            }
        }

        $q->where($field, $value);
    }

    /**
     * @param mixed $value
     */
    private function likeIfNotEmpty(Builder $q, string $field, mixed $value): void
    {
        if (!is_string($value)) {
            return;
        }

        $value = trim($value);
        if ($value === '') {
            return;
        }

        $q->where($field, 'like', "%{$value}%");
    }

    /**
     * @param mixed $value
     */
    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
