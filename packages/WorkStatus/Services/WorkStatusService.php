<?php

declare(strict_types=1);

namespace iEXPackages\WorkStatus\Services;

use App\Settings\WorkStatusConfig;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use iEXPackages\WorkStatus\Contracts\WorkStatusResolverInterface;
use iEXPackages\WorkStatus\DTO\WorkStatusResult;
use iEXPackages\WorkStatus\Enums\BaseMode;
use iEXPackages\WorkStatus\Enums\ManualState;
use iEXPackages\WorkStatus\Enums\OverrideMode;

/**
 * WorkStatusService
 *
 * Единая точка входа для получения фактического статуса работы обменника.
 *
 * Этот сервис:
 * - последовательно запускает резолверы (pipeline) и берёт первый не-null результат;
 * - предоставляет методы управления режимами (manual/schedule) и override, чтобы внешний код
 *   не модифицировал настройки напрямую.
 *
 * Приоритеты (задаются порядком резолверов в ServiceProvider):
 * - manual → override → schedule
 *
 * Рекомендации по использованию:
 * - В HTTP-запросах вычисляй статус один раз через middleware и используй `work_status()`/`work_is_online()`.\n * - В CLI/очередях можно напрямую вызывать `getStatus()`.
 */
final class WorkStatusService
{
    /**
     * Список резолверов в порядке приоритета.
     *
     * @var list<WorkStatusResolverInterface>
     */
    private array $resolvers = [];

    /**
     * @param iterable<WorkStatusResolverInterface> $resolvers Резолверы в порядке приоритета (сверху вниз).
     * @param WorkStatusConfig $config Настройки режима работы (DynamicConfig).
     */
    public function __construct(
        iterable $resolvers,
        private readonly WorkStatusConfig $config,
    ) {
        foreach ($resolvers as $resolver) {
            $this->resolvers[] = $resolver;
        }
    }

    /**
     * Получить фактический статус работы на момент времени.
     *
     * Как работает:
     * - берём момент времени `$now` (если не передан — текущее время);
     * - последовательно вызываем резолверы;
     * - возвращаем первый `WorkStatusResult`.
     *
     * Почему это безопасно:
     * - если резолвер не может принять решение, он возвращает `null`;
     * - порядок приоритета задаётся централизованно в WorkStatusServiceProvider.
     *
     * @param CarbonInterface|null $now Момент времени для расчёта. Если null — текущее.
     *
     * @return WorkStatusResult Итоговый статус с источником (source) и причиной (reason).
     *
     * @throws \LogicException Если ни один резолвер не вернул результат (ошибка конфигурации).
     */
    public function getStatus(?CarbonInterface $now = null): WorkStatusResult
    {
        // Время берём в текущей дефолтной timezone PHP.
        // Предполагается, что timezone приложения синхронизирован на уровне bootstrap/AppServiceProvider.
        $now = $now ? Carbon::instance($now) : Carbon::now();

        foreach ($this->resolvers as $resolver) {
            $result = $resolver->resolve($now);
            if ($result !== null) {
                return $result;
            }
        }

        throw new \LogicException(
            'WorkStatusService: резолверы не вернули результат. Проверь регистрацию резолверов в ServiceProvider.'
        );
    }

    /**
     * Быстрая проверка: обменник работает (online).
     *
     * @param CarbonInterface|null $now Момент времени для проверки.
     */
    public function isOnline(?CarbonInterface $now = null): bool
    {
        return $this->getStatus($now)->isOnline;
    }

    /**
     * Быстрая проверка: обменник остановлен (offline).
     *
     * @param CarbonInterface|null $now Момент времени для проверки.
     */
    public function isOffline(?CarbonInterface $now = null): bool
    {
        return ! $this->isOnline($now);
    }

    /**
     * Переключить систему в ручной режим и установить состояние.
     *
     * Поведение:
     * - очищаем override (в manual он не имеет смысла);
     * - base_mode = manual;
     * - manual_state = online/offline.
     *
     * @param ManualState $state Состояние ручного режима.
     * @param string|null $reason Причина (опционально), сохраняется для UI/логов.
     */
    public function switchToManual(ManualState $state, ?string $reason = null): void
    {
        $this->config->clearOverride();
        $this->config->setBaseMode(BaseMode::Manual);
        $this->config->setManualState($state, $reason);
    }

    /**
     * Переключить систему в режим расписания.
     *
     * Поведение:
     * - base_mode = schedule;
     * - allow_override настраивается параметром `$allowOverride`;
     * - override очищается (возвращаем управление расписанию).
     *
     * @param bool $allowOverride Разрешать ли ручной override поверх расписания.
     */
    public function switchToSchedule(bool $allowOverride = true): void
    {
        $this->config->setBaseMode(BaseMode::Schedule);
        $this->config->setAllowOverride($allowOverride);
        $this->config->clearOverride();
    }

    /**
     * Установить override поверх расписания.
     *
     * Требования:
     * - base_mode должен быть schedule;
     * - allow_override должен быть включён.
     *
     * Рекомендация:
     * - Для режима "schedule + ручной клик" всегда передавай `$until` (TTL), чтобы
     *   управление автоматически возвращалось расписанию.
     *
     * @param OverrideMode $mode Режим override (force_online/force_offline).
     * @param CarbonInterface|null $until До какого момента действует override (null = бессрочно).
     * @param string|null $reason Причина (опционально).
     *
     * @throws \LogicException Если override недопустим в текущем режиме.
     */
    public function setScheduleOverride(
        OverrideMode $mode,
        ?CarbonInterface $until = null,
        ?string $reason = null,
    ): void {
        if ($this->config->baseMode() !== BaseMode::Schedule) {
            throw new \LogicException('Override можно установить только в режиме schedule.');
        }

        if (! $this->config->allowOverride()) {
            throw new \LogicException('Override запрещён: allow_override выключен.');
        }

        if ($mode === OverrideMode::None) {
            $this->config->clearOverride();
            return;
        }

        $this->config->setOverride($mode, $until, $reason);
    }

    /**
     * Сбросить override и вернуть управление чистому расписанию.
     */
    public function clearScheduleOverride(): void
    {
        $this->config->clearOverride();
    }
}
