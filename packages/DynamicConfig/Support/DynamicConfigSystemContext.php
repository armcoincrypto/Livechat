<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Support;

/**
 * DynamicConfigSystemContext
 *
 * Контекст “системной записи” в настройки DynamicConfig.
 *
 * Зачем нужен:
 * - В web-контексте без Auth::user() записи по ACL обычно запрещены.
 * - Но есть легальные сценарии автоматической записи:
 *   - ServiceProvider / bootstrap;
 *   - внутренние сервисы без пользователя;
 *   - jobs, которые выполняются синхронно в web-процессе;
 *   - внутренние обработчики событий.
 *
 * Этот контекст даёт безопасный механизм:
 * - запись разрешается только если:
 *   - мы в CLI (artisan/cron/queue), ИЛИ
 *   - включён системный контекст (this->isEnabled()).
 *
 * Важно:
 * - сам по себе контекст НЕ даёт доступ к любым ключам.
 * - фактическое разрешение должно дополнительно ограничиваться whitelist’ом ключей
 *   (dynamic_config.access_control.console_write_whitelist).
 *
 * Реентерабельность:
 * - контекст поддерживает вложенные вызовы run(), корректно восстанавливает состояние.
 */
final class DynamicConfigSystemContext
{
    /**
     * Глубина вложенности (сколько раз контекст включён).
     * Если depth > 0 — контекст активен.
     */
    private int $depth = 0;

    /**
     * Причина включения контекста (для отладки).
     * Не влияет на поведение, только для диагностики.
     */
    private ?string $reason = null;

    /**
     * Включить системный контекст.
     *
     * @param string|null $reason Причина (например: "service-provider", "internal-cron", "bootstrap").
     */
    public function enable(?string $reason = null): void
    {
        $this->depth++;

        // Причину сохраняем только для верхнего уровня (первого enable)
        if ($this->depth === 1) {
            $this->reason = $reason;
        }
    }

    /**
     * Выключить системный контекст.
     * Корректно работает при вложенных enable().
     */
    public function disable(): void
    {
        if ($this->depth <= 0) {
            $this->depth = 0;
            $this->reason = null;
            return;
        }

        $this->depth--;

        if ($this->depth === 0) {
            $this->reason = null;
        }
    }

    /**
     * Активен ли системный контекст.
     */
    public function isEnabled(): bool
    {
        return $this->depth > 0;
    }

    /**
     * Текущая причина (если включён), иначе null.
     */
    public function reason(): ?string
    {
        return $this->reason;
    }

    /**
     * Запустить callback внутри системного контекста.
     *
     * Гарантирует:
     * - enable() перед выполнением
     * - disable() после выполнения (даже при исключении)
     *
     * @template T
     * @param callable():T $callback
     * @param string|null  $reason
     * @return T
     */
    public function run(callable $callback, ?string $reason = null): mixed
    {
        $this->enable($reason);

        try {
            return $callback();
        } finally {
            $this->disable();
        }
    }
}
