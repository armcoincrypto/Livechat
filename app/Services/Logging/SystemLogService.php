<?php

namespace App\Services\Logging;

use App\Models\SystemLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * SystemLogService — финальная масштабируемая версия
 *
 * Цели:
 *  1) История (append-only): recordEvent() пишет любые события без проверок.
 *  2) Инциденты (stateful): одна АКТИВНАЯ строка на ключ проблемы.
 *     - При повторном срабатывании инцидента в активной фазе: НЕ создаём новую строку,
 *       а обновляем счетчик times_seen, last_seen_at, last_message/last_context.
 *     - При нормализации: переводим state -> 'resolved' (и active_flag -> 0).
 *     - При новом появлении после resolved: создаём НОВУЮ строку (новый эпизод).
 *
 * Анти-дубли:
 *  - На уровне БД вводится уникальный индекс по (module,name,code,entity_type,entity_id,active_flag),
 *    что исключает >1 активной строки на ключ. active_flag=1 для active, 0 для resolved.
 *  - В коде — транзакции + update-first стратегия.
 */
class SystemLogService
{
    /* ===================== Уровни ===================== */

    public const LEVEL_INFO     = 'info';
    public const LEVEL_WARNING  = 'warning';
    public const LEVEL_ERROR    = 'error';
    public const LEVEL_CRITICAL = 'critical';

    public static function levels(): array
    {
        return [self::LEVEL_INFO, self::LEVEL_WARNING, self::LEVEL_ERROR, self::LEVEL_CRITICAL];
    }

    /* ===================== Ограничения ===================== */

    private const MODULE_MAX = 100;
    private const NAME_MAX   = 100;
    private const CODE_MAX   = 128;

    private array $allowedLevels = [
        self::LEVEL_INFO,
        self::LEVEL_WARNING,
        self::LEVEL_ERROR,
        self::LEVEL_CRITICAL,
    ];

    /* ===================== Кэш состояния (опционально) ===================== */

    private function useStateCache(): bool
    {
        $val = env('SYSTEM_LOGS_CACHE_STATE', true);
        if (is_string($val)) {
            $val = strtolower($val);
            return in_array($val, ['1','true','yes','on'], true);
        }
        return (bool)$val;
    }

    private function stateCacheKey(string $module, ?string $name, string $code, ?string $etype, ?string $eid): string
    {
        return sprintf('system_logs:state:%s:%s:%s:%s:%s', $module, $name ?? '', $code, $etype ?? '', $eid ?? '');
    }

    private function cacheStatePut(string $module, ?string $name, string $code, ?string $etype, ?string $eid, string $state): void
    {
        if (! $this->useStateCache()) return;
        Cache::put($this->stateCacheKey($module,$name,$code,$etype,$eid), $state, now()->addHours(12));
    }

    private function cacheStateGet(string $module, ?string $name, string $code, ?string $etype, ?string $eid): ?string
    {
        if (! $this->useStateCache()) return null;
        $cached = Cache::get($this->stateCacheKey($module,$name,$code,$etype,$eid));
        return in_array($cached, ['active','resolved'], true) ? $cached : null;
    }

    public function forgetState(string $module, ?string $name, string $code, ?string $etype, ?string $eid): void
    {
        Cache::forget($this->stateCacheKey($module,$name,$code,$etype,$eid));
    }

    /* ===================== История (append-only) ===================== */

    /**
     * Пишет любое событие в историю без проверок.
     */
    public function recordEvent(string $module, string $level, string $message, array $opts = []): SystemLog
    {
        [$etype, $eid] = $opts['entity'] ?? [null, null];
        $name       = $this->normalize($opts['name'] ?? null, self::NAME_MAX);
        $code       = $this->normalize($opts['code'] ?? null, self::CODE_MAX);
        $context    = $opts['context'] ?? [];
        $occurredAt = $opts['occurred_at'] ?? now();
        $dedupTTL   = $opts['dedup_ttl'] ?? null;

        $module     = $this->normalize($module, self::MODULE_MAX) ?? 'system';
        $level      = in_array($level, $this->allowedLevels, true) ? $level : self::LEVEL_INFO;
        $source     = $this->captureSource($opts['source'] ?? []);

        if (!app()->runningInConsole()) {
            $req = request();
            $context += array_filter([
                'request_id' => $req->headers->get('X-Request-Id') ?? null,
                'ip'         => $req->ip(),
                'url'        => $req->fullUrl(),
                'user_id'    => optional(auth()->user())->getAuthIdentifier(),
            ], static fn($v) => $v !== null);
        }

        $hash = hash('xxh128', json_encode([$module,$name,$level,$etype,(string)$eid,$code,$message,$context,$source], JSON_UNESCAPED_UNICODE));
        if ($dedupTTL) {
            $key = "system_logs:d:$hash";
            if (!Cache::add($key, 1, $dedupTTL)) {
                return SystemLog::query()->where('dedup_hash', $hash)->orderByDesc('id')->first() ?? new SystemLog();
            }
        }

        return SystemLog::create([
            'module'      => $module,
            'name'        => $name,
            'level'       => $level,
            'entity_type' => $etype,
            'entity_id'   => isset($eid) ? (string)$eid : null,
            'code'        => $code,
            'message'     => $message,
            'context'     => $context ?: null,
            'source'      => $source ?: null,
            'occurred_at' => $occurredAt,
            'dedup_hash'  => $hash,
            // state не задаём — история не влияет на инциденты
        ]);
    }

    public function log(string $module, string $level, string $message, array $opts = []): SystemLog
    { return $this->recordEvent($module, $level, $message, $opts); }

    /* ===================== Инциденты: ключ/состояние ===================== */

    /** Возвращает текущее состояние ('active'|'resolved'|null) по ключу. */
    public function getCurrentProblemState(string $module, ?string $name, string $code, ?string $etype, ?string $eid): ?string
    {
        // быстрый путь — кэш
        if ($cached = $this->cacheStateGet($module,$name,$code,$etype,$eid)) {
            // валидируем наличие любого ряда по ключу (защита от ручных удалений)
            $exists = SystemLog::query()
                ->where('module',$module)->where('name',$name)->where('code',$code)
                ->where('entity_type',$etype)->where('entity_id',$eid)
                ->exists();
            if ($exists) return $cached;
            $this->forgetState($module,$name,$code,$etype,$eid);
        }

        $latest = SystemLog::query()
            ->where('module',$module)->where('name',$name)->where('code',$code)
            ->where('entity_type',$etype)->where('entity_id',$eid)
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->value('state');

        if (in_array($latest, ['active','resolved'], true)) {
            $this->cacheStatePut($module,$name,$code,$etype,$eid,$latest);
        }
        return $latest;
    }

    public function isProblemActive(string $module, ?string $name, string $code, array $entity): bool
    {
        [$etype,$eid] = $entity;
        return $this->getCurrentProblemState(
            $this->normalize($module,self::MODULE_MAX) ?? 'system',
            $this->normalize($name ?? null,self::NAME_MAX),
            $this->normalize($code,self::CODE_MAX),
            $etype ?: null,
            isset($eid) ? (string)$eid : null
        ) === 'active';
    }

    /* ===================== Инциденты: EPISODE (одна активная строка) ===================== */

    /**
     * Активировать/продлить эпизод БЕЗ дублей: одна активная строка на ключ.
     * Если уже active — только обновляет счётчики/last_* и возвращает ту же строку.
     */
    public function activateProblemEpisode(
        string $module,
        ?string $name,
        string $code,
        array $entity,
        string $message,
        array $opts = []
    ): SystemLog {
        $module = $this->normalize($module, self::MODULE_MAX) ?? 'system';
        $name   = $this->normalize($name ?? null, self::NAME_MAX);
        [$etype,$eid] = $entity; $etype = $etype ?: null; $eid = isset($eid) ? (string)$eid : null;
        $level   = $opts['level'] ?? self::LEVEL_ERROR;
        $context = $opts['context'] ?? [];
        $source  = $this->captureSource($opts['source'] ?? []);
        $now     = $opts['occurred_at'] ?? now();

        return DB::transaction(function () use ($module,$name,$code,$etype,$eid,$level,$message,$context,$source,$now) {
            // Лочим ПОСЛЕДНЮЮ строку по ключу (любой state) — гонки исключены
            $latest = SystemLog::query()
                ->where('module',$module)->where('name',$name)->where('code',$code)
                ->where('entity_type',$etype)->where('entity_id',$eid)
                ->orderByDesc('occurred_at')->orderByDesc('id')
                ->lockForUpdate()->first();

            if ($latest && $latest->state === 'active') {
                // Продление активного эпизода — БЕЗ вставки новой строки
                $latest->forceFill([
                    'last_seen_at' => $now,
                    'times_seen'   => (int)($latest->times_seen ?? 0) + 1,
                    'last_message' => $message,
                    'last_context' => $context ?: null,
                ])->save();

                $this->cacheStatePut($module,$name,$code,$etype,$eid,'active');
                return $latest;
            }

            // Новый эпизод (или раньше был resolved)
            $row = SystemLog::create([
                'module'       => $module,
                'name'         => $name,
                'level'        => $level,
                'state'        => 'active',
                'active_flag'  => 1,
                'entity_type'  => $etype,
                'entity_id'    => $eid,
                'code'         => $code,
                'message'      => $message,
                'context'      => $context ?: null,
                'source'       => $source ?: null,
                'occurred_at'  => $now,
                'first_seen_at'=> $now,
                'last_seen_at' => $now,
                'times_seen'   => 1,
                'last_message' => $message,
                'last_context' => $context ?: null,
            ]);

            $this->cacheStatePut($module,$name,$code,$etype,$eid,'active');
            return $row;
        });
    }

    /**
     * Закрыть активный эпизод (state='resolved', active_flag=0). Без вставки новых строк.
     */
    public function resolveProblemEpisode(
        string $module,
        ?string $name,
        string $code,
        array $entity,
        string $message = 'Проблема устранена',
        array $opts = []
    ): ?SystemLog {
        $module = $this->normalize($module, self::MODULE_MAX) ?? 'system';
        $name   = $this->normalize($name ?? null, self::NAME_MAX);
        [$etype,$eid] = $entity; $etype = $etype ?: null; $eid = isset($eid) ? (string)$eid : null;
        $context = $opts['context'] ?? [];
        $source  = $this->captureSource($opts['source'] ?? []);
        $now     = $opts['occurred_at'] ?? now();

        return DB::transaction(function () use ($module,$name,$code,$etype,$eid,$message,$context,$source,$now) {
            $latest = SystemLog::query()
                ->where('module',$module)->where('name',$name)->where('code',$code)
                ->where('entity_type',$etype)->where('entity_id',$eid)
                ->orderByDesc('occurred_at')->orderByDesc('id')
                ->lockForUpdate()->first();

            if (! $latest || $latest->state !== 'active') {
                return null; // нечего закрывать
            }

            $latest->forceFill([
                'state'        => 'resolved',
                'active_flag'  => 0,
                'resolved_at'  => $now,
                'last_message' => $message,
                'last_context' => $context ?: null,
            ])->save();

            $this->cacheStatePut($module,$name,$code,$etype,$eid,'resolved');
            return $latest;
        });
    }

    /**
     * Универсальный синхронизатор статуса: true => activate, false => resolve (episode-режим).
     */
    public function syncProblem(
        string $module,
        ?string $name,
        string $code,
        array $entity,
        bool $isActive,
        ?string $activateMessage = null,
        ?string $resolveMessage = null,
        array $opts = []
    ): ?SystemLog {
        $module = $this->normalize($module, self::MODULE_MAX) ?? 'system';
        $name   = $this->normalize($name ?? null, self::NAME_MAX);
        [$etype,$eid] = $entity; $etype = $etype ?: null; $eid = isset($eid) ? (string)$eid : null;

        $current = $this->getCurrentProblemState($module,$name,$code,$etype,$eid);

        if ($isActive) {
            if ($current === 'active') {
                // продление: увеличим счётчики без вставки
                return $this->activateProblemEpisode($module,$name,$code,[$etype,$eid], $activateMessage ?? 'Проблема зафиксирована', $opts);
            }
            return $this->activateProblemEpisode($module,$name,$code,[$etype,$eid], $activateMessage ?? 'Проблема зафиксирована', $opts);
        }

        if ($current === 'active') {
            return $this->resolveProblemEpisode($module,$name,$code,[$etype,$eid], $resolveMessage ?? 'Проблема устранена', $opts);
        }
        return null;
    }

    /* ===================== Выборки активных ===================== */

    public function getActiveProblemsViaLogs(string $module, ?string $name = null, int $limit = 200): array
    {
        $module = $this->normalize($module, self::MODULE_MAX) ?? 'system';
        $bindings = [$module]; $nameSql = '';
        if ($name !== null) { $name = $this->normalize($name, self::NAME_MAX); $nameSql = ' AND l.name = ?'; $bindings[] = $name; }
        $bindings[] = $limit;

        $sql = "
            SELECT * FROM (
              SELECT l.*,
                     ROW_NUMBER() OVER (
                       PARTITION BY module, name, code, entity_type, entity_id
                       ORDER BY occurred_at DESC, id DESC
                     ) AS rn
              FROM system_logs l
              WHERE l.module = ?" . $nameSql . "
            ) t
            WHERE t.rn = 1 AND t.state = 'active'
            LIMIT ?
        ";

        return DB::select($sql, $bindings);
    }

    public function getActiveProblemsFallback(string $module, ?string $name = null, int $limit = 200): Collection
    {
        $module = $this->normalize($module, self::MODULE_MAX) ?? 'system';
        $markerExpr = DB::raw("CONCAT_WS('#', DATE_FORMAT(occurred_at, '%Y-%m-%d %H:%i:%s'), LPAD(id, 12, '0'))");

        $sub = SystemLog::query()
            ->select(['module','name','code','entity_type','entity_id', DB::raw("MAX(".$markerExpr.") AS marker")])
            ->where('module', $module)
            ->when($name !== null, fn (Builder $q) => $q->where('name', $this->normalize($name, self::NAME_MAX)))
            ->groupBy('module','name','code','entity_type','entity_id');

        return SystemLog::query()->from('system_logs as l')
            ->joinSub($sub, 'last', function ($join) use ($markerExpr) {
                $join->on('l.module','=','last.module')
                     ->on('l.name','=','last.name')
                     ->on('l.code','=','last.code')
                     ->on('l.entity_type','=','last.entity_type')
                     ->on('l.entity_id','=','last.entity_id')
                     ->whereRaw("CONCAT_WS('#', DATE_FORMAT(l.occurred_at, '%Y-%m-%d %H:%i:%s'), LPAD(l.id, 12, '0')) = last.marker");
            })
            ->where('l.state','=','active')
            ->orderByDesc('l.occurred_at')->orderByDesc('l.id')
            ->limit($limit)->get();
    }

    /* ===================== Утилиты ===================== */

    private function normalize(?string $value, int $max): ?string
    {
        if ($value === null) return null;
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        if ($value === '') return null;
        return mb_substr($value, 0, $max);
    }

    private function captureSource(array $source = []): array
    {
        if ($source) return $source;
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12);
        foreach ($bt as $f) {
            $cls = $f['class'] ?? '';
            if ($cls === __CLASS__) continue;
            if (str_starts_with($cls, 'Illuminate\\')) continue;
            if (isset($f['file']) && str_contains($f['file'], DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) continue;
            return [
                'file'     => isset($f['file']) ? basename($f['file']) : null,
                'class'    => $cls ?: null,
                'function' => $f['function'] ?? null,
                'line'     => $f['line'] ?? null,
            ];
        }
        return $source;
    }
}
