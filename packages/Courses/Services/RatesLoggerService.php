<?php

declare(strict_types=1);

namespace iEXPackages\Courses\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * RatesLoggerService
 *
 * Сервис логирования изменений курсов в таблицу `rates_history_logs`.
 *
 * Ключевые возможности:
 * - Быстрое пакетное логирование (buffer + flush).
 * - Возможность принудительно отключить логирование на текущий запуск (override),
 *   даже если глобальная настройка включена.
 * - Фильтрация "пустых" логов (old == new), чтобы не раздувать таблицу.
 * - Безопасный flush: ошибки записи логов не должны ломать обновление курсов.
 */
final class RatesLoggerService
{
    /**
     * Буфер пакетного логирования.
     *
     * @var array<int, array{
     *   id_source:int,
     *   source:string,
     *   name:string,
     *   old_value: string|null,
     *   new_value: string,
     *   created_at: string,
     *   updated_at: string
     * }>
     */
    private array $batchLogs = [];

    /**
     * Глобальный флаг включённости логирования (настройка системы).
     */
    private bool $enabled;

    /**
     * Override флаг на текущий запуск.
     * - null: использовать $enabled
     * - true/false: принудительно включить/выключить логирование
     */
    private ?bool $enabledOverride = null;

    /**
     * Размер батча для вставки.
     * Можно увеличить до 1000–2000 для скорости, но не делай слишком большим,
     * чтобы не упереться в max_allowed_packet / лимиты драйвера.
     */
    private int $chunkSize = 1000;

    public function __construct()
    {
        $this->enabled = (int) iEXSetting('is_enabled_rates_log') === 1;
    }

    /**
     * Принудительно включить/выключить логирование на текущий запуск.
     * Полезно для cron, тестов или ручного запуска.
     */
    public function setEnabledOverride(?bool $enabled): self
    {
        $this->enabledOverride = $enabled;
        return $this;
    }

    /**
     * Изменить размер чанка вставки.
     */
    public function setChunkSize(int $size): self
    {
        $this->chunkSize = max(100, min(5000, $size));
        return $this;
    }

    /**
     * Включено ли логирование сейчас (с учётом override).
     */
    public function isEnabled(): bool
    {
        return $this->enabledOverride ?? $this->enabled;
    }

    /**
     * Добавляет запись в пакет логов изменений.
     *
     * Оптимизация:
     * - Если oldValue === newValue (с учётом trim), лог не пишем.
     *
     * @param string      $source   Источник изменения (например, 'default', 'competitor', 'file', 'formula')
     * @param int         $idSource Идентификатор сущности-источника
     * @param string      $name     Название/пара/имя
     * @param string|null $oldValue Старое значение
     * @param string      $newValue Новое значение
     */
    public function batchLog(
        string $source,
        int $idSource,
        string $name,
        ?string $oldValue,
        string $newValue
    ): void {
        if (!$this->isEnabled()) {
            return;
        }

        $oldNorm = $oldValue !== null ? trim($oldValue) : null;
        $newNorm = trim($newValue);

        // Не логируем "пустые" изменения
        if ($oldNorm !== null && $oldNorm === $newNorm) {
            return;
        }

        // Используем строковый timestamp для меньшей нагрузки на память (чем Carbon-объекты)
        $now = now()->toDateTimeString();

        $this->batchLogs[] = [
            'id_source'  => $idSource,
            'source'     => $source,
            'name'       => $name,
            'old_value'  => $oldNorm,
            'new_value'  => $newNorm,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Одиночное логирование без буфера.
     * По умолчанию лучше использовать batchLog + flush.
     */
    public function log(
        string $source,
        int $idSource,
        string $name,
        ?string $oldValue,
        string $newValue
    ): void {
        if (!$this->isEnabled()) {
            return;
        }

        $oldNorm = $oldValue !== null ? trim($oldValue) : null;
        $newNorm = trim($newValue);

        if ($oldNorm !== null && $oldNorm === $newNorm) {
            return;
        }

        $now = now()->toDateTimeString();

        try {
            DB::table('rates_history_logs')->insert([
                'id_source'  => $idSource,
                'source'     => $source,
                'name'       => $name,
                'old_value'  => $oldNorm,
                'new_value'  => $newNorm,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable $e) {
            // Логи не должны ломать обновление курсов
            Log::warning('RatesLoggerService: insert failed', [
                'source' => $source,
                'id_source' => $idSource,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Пакетная запись накопленных логов.
     * Ошибка вставки логов не должна ломать основной процесс.
     */
    public function flush(): void
    {
        if (!$this->isEnabled() || $this->batchLogs === []) {
            return;
        }

        $chunkSize = $this->chunkSize;

        try {
            foreach (array_chunk($this->batchLogs, $chunkSize) as $chunk) {
                DB::table('rates_history_logs')->insert($chunk);
            }
        } catch (Throwable $e) {
            Log::warning('RatesLoggerService: flush failed', [
                'error' => $e->getMessage(),
                'count' => count($this->batchLogs),
            ]);
        } finally {
            // Очищаем буфер в любом случае, иначе он будет накапливаться и может съесть память.
            $this->batchLogs = [];
        }
    }
}
